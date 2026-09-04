<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PosSessionStatus;
use App\Enums\SaleType;
use App\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\PosSession;
use App\Models\PosSyncConflict;
use App\Models\ProductVariantSize;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OfflineSyncService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected TaxService $taxService
    ) {}

    public function syncSales(User $user, array $salesPayloads): array
    {
        $results = [];
        $totalProcessed = 0;
        $syncedCount = 0;
        $alreadySyncedCount = 0;
        $rejectedCount = 0;

        foreach ($salesPayloads as $payload) {
            $totalProcessed++;
            $clientUuid = $payload['client_trans_uuid'] ?? null;

            if (! $clientUuid) {
                $rejectedCount++;
                $results[] = [
                    'client_trans_uuid' => null,
                    'status' => 'rejected',
                    'message' => 'Missing client_trans_uuid idempotency key.',
                ];
                continue;
            }

            // IDEMPOTENCY CHECK
            $existingInvoice = Invoice::where('client_trans_uuid', $clientUuid)->first();
            if ($existingInvoice) {
                $alreadySyncedCount++;
                $results[] = [
                    'client_trans_uuid' => $clientUuid,
                    'status' => 'already_synced',
                    'invoice_id' => $existingInvoice->id,
                    'invoice_number' => $existingInvoice->invoice_number,
                    'grand_total' => (float) $existingInvoice->grand_total,
                    'message' => 'Transaction was already synchronized.',
                ];
                continue;
            }

            // PROCESS SINGLE TRANSACTION ATOMICALLY
            try {
                $resultData = DB::transaction(function () use ($user, $payload, $clientUuid) {
                    return $this->processSingleSale($user, $payload, $clientUuid);
                });

                $syncedCount++;
                $results[] = [
                    'client_trans_uuid' => $clientUuid,
                    'status' => 'synced',
                    'invoice_id' => $resultData['invoice_id'],
                    'invoice_number' => $resultData['invoice_number'],
                    'grand_total' => $resultData['grand_total'],
                    'message' => 'Sale synchronized successfully.',
                ];
            } catch (\InvalidArgumentException $e) {
                $rejectedCount++;
                $this->recordConflict($user, $payload, $clientUuid, $this->detectConflictType($e->getMessage()), $e->getMessage());
                $results[] = [
                    'client_trans_uuid' => $clientUuid,
                    'status' => 'rejected',
                    'message' => $e->getMessage(),
                ];
            } catch (\RuntimeException $e) {
                $rejectedCount++;
                $this->recordConflict($user, $payload, $clientUuid, $this->detectConflictType($e->getMessage()), $e->getMessage());
                $results[] = [
                    'client_trans_uuid' => $clientUuid,
                    'status' => 'rejected',
                    'message' => $e->getMessage(),
                ];
            } catch (\Exception $e) {
                $rejectedCount++;
                $this->recordConflict($user, $payload, $clientUuid, 'other', 'Failed to sync sale: '.$e->getMessage());
                $results[] = [
                    'client_trans_uuid' => $clientUuid,
                    'status' => 'rejected',
                    'message' => 'Failed to sync sale: '.$e->getMessage(),
                ];
            }
        }

        return [
            'total_processed' => $totalProcessed,
            'synced_count' => $syncedCount,
            'already_synced_count' => $alreadySyncedCount,
            'rejected_count' => $rejectedCount,
            'results' => $results,
        ];
    }

    protected function processSingleSale(User $user, array $payload, string $clientUuid): array
    {
        // 1. POS Session & Store Access Validation
        $posSessionId = $payload['pos_session_id'] ?? null;
        $session = null;

        if ($posSessionId) {
            $session = PosSession::find((int) $posSessionId);
            if (! $session || (is_object($session->status) ? $session->status->value : $session->status) !== 'open') {
                throw new \RuntimeException('Invalid or closed POS session.');
            }
        } else {
            $session = PosSession::where('user_id', $user->id)
                ->where('status', PosSessionStatus::OPEN->value)
                ->latest('opened_at')
                ->first();
            if (! $session) {
                throw new \RuntimeException('Active POS session required to process sale. Please open a POS session first.');
            }
        }

        $storeId = (int) ($payload['store_id'] ?? $session->store_id);

        $customerId = isset($payload['customer_id']) ? (int) $payload['customer_id'] : null;
        $customer = $customerId ? Customer::find($customerId) : null;

        if ($storeId !== (int) $session->store_id) {
            throw new \RuntimeException('Forbidden: Sale store does not match POS session store.');
        }

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($storeId)) {
                throw new \RuntimeException('Forbidden: You are not authorized to process sales for this store.');
            }
        }

        $couponCode = $payload['coupon_code'] ?? null;
        $promoEngine = app(\App\Services\PromotionEngineService::class);
        $promoEvaluation = null;
        if (! empty($couponCode)) {
            $promoEvaluation = $promoEngine->evaluateCart($payload['items'] ?? [], $couponCode, $storeId, $customerId);
        }

        $invoiceNumber = 'INV-'.date('Ymd').'-'.strtoupper(Str::random(6));
        $preparedItems = [];
        $subtotal = 0.0;
        $totalDiscount = $promoEvaluation ? $promoEvaluation['total_discount_amount'] : (float) ($payload['discount_amount'] ?? 0.0);
        $totalCgst = 0.0;
        $totalSgst = 0.0;
        $totalIgst = 0.0;
        $totalTax = 0.0;
        $taxableAmount = 0.0;

        $isGstEnabled = $this->taxService->isGstEnabled();

        // 2. Validate Items & Stock
        foreach ($payload['items'] ?? [] as $itemInput) {
            $variantSize = null;
            if (! empty($itemInput['product_variant_size_id'])) {
                $variantSize = ProductVariantSize::with([
                    'variant.product.brand',
                    'variant.product.category',
                    'variant.product.hsnCode',
                    'variant.color',
                    'size',
                ])->find((int) $itemInput['product_variant_size_id']);
            } elseif (! empty($itemInput['sku'])) {
                $variantSize = ProductVariantSize::with([
                    'variant.product.brand',
                    'variant.product.category',
                    'variant.product.hsnCode',
                    'variant.color',
                    'size',
                ])->where('sku', trim($itemInput['sku']))->first();
            }

            if (! $variantSize) {
                throw new \InvalidArgumentException('Invalid SKU or product variant size ID.');
            }

            $qty = (int) ($itemInput['quantity'] ?? 0);
            if ($qty <= 0) {
                throw new \InvalidArgumentException("Invalid quantity for SKU {$variantSize->sku}.");
            }

            // Lock physical inventory row and check stock
            $stockRecord = $this->inventoryService->getStockRecord(
                $variantSize->id,
                $storeId,
                0,
                0,
                true
            );

            if ($stockRecord->stock_quantity < $qty) {
                throw new \RuntimeException("Insufficient physical stock for SKU {$variantSize->sku}. Available: {$stockRecord->stock_quantity}, Requested: {$qty}.");
            }

            $costPrice = (float) ($variantSize->cost_price ?? 0.00);
            $mrp = (float) ($variantSize->mrp ?? 0.00);
            $unitPrice = isset($itemInput['unit_price']) ? (float) $itemInput['unit_price'] : (float) ($variantSize->selling_price ?? 0.00);
            $lineDiscount = isset($itemInput['discount_amount']) ? (float) $itemInput['discount_amount'] : 0.00;

            $lineGross = round($unitPrice * $qty, 2);
            $lineNet = max(0.0, round($lineGross - $lineDiscount, 2));

            $subtotal += $lineGross;
            $totalDiscount += $lineDiscount;

            $itemTaxable = $lineNet;
            $itemCgst = 0.0;
            $itemSgst = 0.0;
            $itemIgst = 0.0;
            $itemTotalTax = 0.0;
            $taxRatePercentage = 0.0;

            if ($isGstEnabled) {
                $taxResult = $this->taxService->calculateItemTax($lineNet, $qty, 0.0);
                $itemTaxable = $taxResult['taxable_value'];
                $itemCgst = $taxResult['cgst_amount'];
                $itemSgst = $taxResult['sgst_amount'];
                $itemIgst = $taxResult['igst_amount'];
                $itemTotalTax = $taxResult['total_tax_amount'];
                $taxRatePercentage = $taxResult['tax_rate_percentage'];
            }

            $taxableAmount += $itemTaxable;
            $totalCgst += $itemCgst;
            $totalSgst += $itemSgst;
            $totalIgst += $itemIgst;
            $totalTax += $itemTotalTax;

            $product = $variantSize->variant?->product;
            $hsnCode = $product?->hsnCode?->hsn_code ?? $product?->category?->hsnCode?->hsn_code ?? null;

            $preparedItems[] = [
                'variantSize' => $variantSize,
                'sku_snapshot' => $variantSize->sku,
                'article_number_snapshot' => $product?->article_number ?? 'N/A',
                'product_name_snapshot' => $product?->name ?? 'N/A',
                'color_name_snapshot' => $variantSize->variant?->color?->name ?? 'N/A',
                'size_number_snapshot' => $variantSize->size?->size_number ?? 'N/A',
                'hsn_code_snapshot' => $hsnCode,
                'cost_price' => $costPrice,
                'mrp' => $mrp,
                'unit_price' => $unitPrice,
                'quantity' => $qty,
                'discount_amount' => $lineDiscount,
                'tax_rate_percentage' => $taxRatePercentage,
                'taxable_value' => $itemTaxable,
                'cgst_amount' => $itemCgst,
                'sgst_amount' => $itemSgst,
                'igst_amount' => $itemIgst,
                'total_tax_amount' => $itemTotalTax,
                'subtotal' => $lineNet,
            ];
        }

        $grandTotal = max(0.0, round($subtotal + $totalTax - $totalDiscount, 2));

        // 3. Process Payment Entries
        $paymentEntries = [];
        if (isset($payload['payments']) && is_array($payload['payments']) && count($payload['payments']) > 0) {
            $paymentEntries = $payload['payments'];
        } else {
            $paymentEntries = [
                [
                    'payment_method' => $payload['payment_method'] ?? 'cash',
                    'amount' => $grandTotal,
                    'transaction_reference' => $payload['transaction_reference'] ?? null,
                    'notes' => 'Offline POS Sale Payment',
                ],
            ];
        }

        $totalPaidIncoming = 0.0;
        foreach ($paymentEntries as $pEntry) {
            $method = strtolower(trim((string) ($pEntry['payment_method'] ?? 'cash')));
            if (! in_array($method, ['cash', 'upi', 'card', 'store_credit', 'other'])) {
                throw new \InvalidArgumentException("Invalid payment method {$method}.");
            }
            if ($method === 'store_credit') {
                throw new \RuntimeException('Store credit balance functionality is outside current scope.');
            }
            $pAmt = (float) ($pEntry['amount'] ?? 0.0);
            if ($pAmt <= 0) {
                throw new \InvalidArgumentException('Payment amount must be greater than zero.');
            }
            $totalPaidIncoming += $pAmt;
        }

        $totalPaidIncoming = round($totalPaidIncoming, 2);
        if (abs($totalPaidIncoming - $grandTotal) > 0.01) {
            throw new \InvalidArgumentException("Total payments sum ({$totalPaidIncoming}) must equal invoice grand total ({$grandTotal}).");
        }

        // 4. Create Invoice Header
        $inv = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'client_trans_uuid' => $clientUuid,
            'store_id' => $storeId,
            'pos_session_id' => $session->id,
            'customer_id' => $payload['customer_id'] ?? null,
            'subtotal' => $subtotal,
            'discount_amount' => $totalDiscount,
            'is_gst_enabled' => $isGstEnabled,
            'taxable_amount' => $taxableAmount,
            'total_cgst' => $totalCgst,
            'total_sgst' => $totalSgst,
            'total_igst' => $totalIgst,
            'total_tax' => $totalTax,
            'grand_total' => $grandTotal,
            'paid_amount' => $grandTotal,
            'change_returned' => 0.00,
            'payment_status' => 'paid',
            'sale_type' => SaleType::POS_COUNTER->value,
            'status' => InvoiceStatus::COMPLETED->value,
            'created_by' => $user->id,
        ]);

        // 5. Create Invoice Items and Deduct Inventory
        foreach ($preparedItems as $prep) {
            InvoiceItem::create([
                'invoice_id' => $inv->id,
                'product_variant_size_id' => $prep['variantSize']->id,
                'sku_snapshot' => $prep['sku_snapshot'],
                'article_number_snapshot' => $prep['article_number_snapshot'],
                'product_name_snapshot' => $prep['product_name_snapshot'],
                'color_name_snapshot' => $prep['color_name_snapshot'],
                'size_number_snapshot' => $prep['size_number_snapshot'],
                'hsn_code_snapshot' => $prep['hsn_code_snapshot'],
                'cost_price' => $prep['cost_price'],
                'mrp' => $prep['mrp'],
                'unit_price' => $prep['unit_price'],
                'quantity' => $prep['quantity'],
                'discount_amount' => $prep['discount_amount'],
                'tax_rate_percentage' => $prep['tax_rate_percentage'],
                'taxable_value' => $prep['taxable_value'],
                'cgst_amount' => $prep['cgst_amount'],
                'sgst_amount' => $prep['sgst_amount'],
                'igst_amount' => $prep['igst_amount'],
                'total_tax_amount' => $prep['total_tax_amount'],
                'subtotal' => $prep['subtotal'],
            ]);

            // Deduct Physical Inventory & Log sale_pos movement
            $this->inventoryService->deductStock(
                $prep['variantSize']->id,
                $prep['quantity'],
                StockMovementType::SALE_POS,
                Invoice::class,
                $inv->id,
                $storeId,
                0,
                0,
                $user,
                false,
                "Offline POS sale #{$inv->invoice_number}"
            );
        }

        // 6. Create Payment Entries
        foreach ($paymentEntries as $pEntry) {
            $pMethod = strtolower(trim((string) ($pEntry['payment_method'] ?? 'cash')));
            $pAmt = (float) ($pEntry['amount'] ?? 0.0);

            if ($pMethod === 'store_credit' && $customer) {
                app(\App\Services\StoreCreditService::class)->issueOrAdjustCredit(
                    $customer,
                    -$pAmt,
                    'payment_used',
                    $user,
                    $storeId,
                    'invoice',
                    $inv->id,
                    $clientUuid,
                    "Used store credit for offline sale #{$inv->invoice_number}"
                );
            }

            InvoicePayment::create([
                'invoice_id' => $inv->id,
                'payment_method' => $pMethod,
                'amount' => $pAmt,
                'transaction_reference' => $pEntry['transaction_reference'] ?? null,
                'notes' => $pEntry['notes'] ?? 'Offline POS Sale Payment',
                'payment_time' => now(),
            ]);
        }

        // Record Promotion Usages
        if ($promoEvaluation && ! empty($promoEvaluation['applied_promotions'])) {
            $promoEngine->recordUsages($promoEvaluation['applied_promotions'], $customerId, $inv->id, $clientUuid);
        }

        // Award Loyalty Points for registered customer
        if ($customer) {
            app(\App\Services\LoyaltyService::class)->earnPoints($customer, $inv, $user);
        }

        return [
            'invoice_id' => $inv->id,
            'invoice_number' => $inv->invoice_number,
            'grand_total' => (float) $inv->grand_total,
        ];
    }

    protected function detectConflictType(string $message): string
    {
        $msg = strtolower($message);
        if (str_contains($msg, 'insufficient physical stock')) {
            return 'insufficient_stock';
        }
        if (str_contains($msg, 'invalid sku') || str_contains($msg, 'variant size')) {
            return 'invalid_sku';
        }
        if (str_contains($msg, 'pos session')) {
            return 'closed_pos_session';
        }
        if (str_contains($msg, 'payment')) {
            return 'invalid_payment';
        }
        if (str_contains($msg, 'forbidden') || str_contains($msg, 'store')) {
            return 'store_mismatch';
        }
        return 'other';
    }

    protected function recordConflict(User $user, array $payload, ?string $clientUuid, string $conflictType, string $reason): void
    {
        try {
            $storeId = $payload['store_id'] ?? null;
            if (! $storeId) {
                $userStore = $user->stores()->first();
                $storeId = $userStore?->id ?? 1;
            }

            PosSyncConflict::create([
                'client_trans_uuid' => $clientUuid ?? (string) Str::uuid(),
                'store_id' => (int) $storeId,
                'pos_session_id' => isset($payload['pos_session_id']) ? (int) $payload['pos_session_id'] : null,
                'user_id' => $user->id,
                'conflict_type' => $conflictType,
                'conflict_reason' => $reason,
                'payload_snapshot' => $payload,
                'status' => 'unresolved',
            ]);
        } catch (\Exception $e) {
            // Silence conflict logging failure if fallback occurs
        }
    }
}
