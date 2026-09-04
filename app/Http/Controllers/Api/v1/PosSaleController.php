<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\InvoiceStatus;
use App\Enums\PosSessionStatus;
use App\Enums\SaleType;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StorePosSalePaymentRequest;
use App\Http\Requests\Master\StorePosSaleRequest;
use App\Http\Resources\PosInvoiceReceiptResource;
use App\Http\Resources\PosSalePaymentSummaryResource;
use App\Http\Resources\PosSaleResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\PosSession;
use App\Models\ProductVariantSize;
use App\Services\InventoryService;
use App\Services\TaxService;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosSaleController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected InventoryService $inventoryService,
        protected TaxService $taxService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Invoice::with([
            'store',
            'posSession',
            'customer',
            'creator',
            'items.variantSize.variant.product.brand',
            'items.variantSize.variant.product.category',
            'items.variantSize.variant.color',
            'items.variantSize.size',
            'payments',
        ]);

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $query->whereIn('store_id', $userStoreIds);
        } else {
            if ($request->filled('store_id')) {
                $query->where('store_id', (int) $request->input('store_id'));
            }
        }

        if ($request->has('pos_session_id')) {
            $query->where('pos_session_id', (int) $request->input('pos_session_id'));
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', (int) $request->input('customer_id'));
        }

        if ($request->has('payment_status') && $request->input('payment_status') !== '') {
            $query->where('payment_status', trim((string) $request->input('payment_status')));
        }

        if ($request->has('status') && $request->input('status') !== '') {
            $query->where('status', trim((string) $request->input('status')));
        }

        if ($request->has('payment_method') && $request->input('payment_method') !== '') {
            $method = trim((string) $request->input('payment_method'));
            $query->whereHas('payments', function ($pq) use ($method) {
                $pq->where('payment_method', $method);
            });
        }

        if ($request->has('min_amount') && is_numeric($request->input('min_amount'))) {
            $query->where('grand_total', '>=', (float) $request->input('min_amount'));
        }

        if ($request->has('max_amount') && is_numeric($request->input('max_amount'))) {
            $query->where('grand_total', '<=', (float) $request->input('max_amount'));
        }

        if ($request->has('search') && $request->input('search') !== '') {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'LIKE', "%{$search}%")
                    ->orWhere('client_trans_uuid', 'LIKE', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('mobile_number', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('items', function ($iq) use ($search) {
                        $iq->where('article_number_snapshot', 'LIKE', "%{$search}%")
                            ->orWhere('product_name_snapshot', 'LIKE', "%{$search}%")
                            ->orWhere('sku_snapshot', 'LIKE', "%{$search}%");
                    });
            });
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', \Illuminate\Support\Carbon::parse($request->input('date_from'))->startOfDay());
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', \Illuminate\Support\Carbon::parse($request->input('date_to'))->endOfDay());
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $sales = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->successResponse(
            [
                'items' => PosSaleResource::collection($sales->items()),
                'pagination' => [
                    'current_page' => $sales->currentPage(),
                    'per_page' => $sales->perPage(),
                    'total' => $sales->total(),
                    'last_page' => $sales->lastPage(),
                ],
            ],
            'POS sales retrieved successfully.'
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $sale = Invoice::with([
            'store',
            'posSession',
            'customer',
            'creator',
            'items.variantSize.variant.product.brand',
            'items.variantSize.variant.product.category',
            'items.variantSize.variant.color',
            'items.variantSize.size',
            'payments',
        ])->find($id);

        if (! $sale) {
            return $this->errorResponse('POS sale transaction not found.', 404);
        }

        $user = $request->user();

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($sale->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to view sales for this store.', 403);
            }
        }

        return $this->successResponse(
            new PosSaleResource($sale),
            'POS sale details retrieved successfully.'
        );
    }

    public function store(StorePosSaleRequest $request): JsonResponse
    {
        $user = $request->user();

        // Check Active POS Session
        $session = PosSession::where('user_id', $user->id)
            ->where('status', PosSessionStatus::OPEN->value);

        if ($request->has('pos_session_id')) {
            $session->where('id', (int) $request->input('pos_session_id'));
        }

        $activeSession = $session->latest('opened_at')->first();

        if (! $activeSession) {
            return $this->errorResponse('Active POS session required to process sale. Please open a POS session first.', 422);
        }

        $storeId = (int) ($request->input('store_id') ?? $activeSession->store_id);

        if ($storeId !== $activeSession->store_id) {
            return $this->errorResponse('Forbidden: Sale store does not match active POS session store.', 422);
        }

        // Store Access Control Validation for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($storeId)) {
                return $this->errorResponse('Forbidden: You are not authorized to process sales for this store.', 403);
            }
        }

        try {
            $invoice = DB::transaction(function () use ($request, $user, $storeId, $activeSession) {
                $invoiceNumber = 'INV-'.date('Ymd').'-'.strtoupper(Str::random(6));
                $clientUuid = $request->input('client_trans_uuid') ?? (string) Str::uuid();
                $customerId = $request->input('customer_id');
                $customer = $customerId ? Customer::find($customerId) : null;

                $couponCode = $request->input('coupon_code');
                $promoEngine = app(\App\Services\PromotionEngineService::class);
                $promoEvaluation = null;

                if (! empty($couponCode)) {
                    $promoEvaluation = $promoEngine->evaluateCart(
                        $request->input('items', []),
                        $couponCode,
                        $storeId,
                        $customerId
                    );
                }

                $preparedItems = [];
                $subtotal = 0.0;
                $totalDiscount = $promoEvaluation ? $promoEvaluation['total_discount_amount'] : (float) $request->input('discount_amount', 0.0);
                $totalCgst = 0.0;
                $totalSgst = 0.0;
                $totalIgst = 0.0;
                $totalTax = 0.0;
                $taxableAmount = 0.0;

                $isGstEnabled = $this->taxService->isGstEnabled();

                foreach ($request->input('items', []) as $itemInput) {
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

                    $qty = (int) $itemInput['quantity'];

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

                // Process Payment entries (multi-tender or single tender)
                $paymentEntries = [];
                if ($request->has('payments') && is_array($request->input('payments')) && count($request->input('payments')) > 0) {
                    $paymentEntries = $request->input('payments');
                } else {
                    $paymentEntries = [
                        [
                            'payment_method' => $request->input('payment_method', 'cash'),
                            'amount' => $grandTotal,
                            'transaction_reference' => $request->input('transaction_reference'),
                            'notes' => 'POS Sale Payment',
                        ],
                    ];
                }

                $totalPaidIncoming = 0.0;
                foreach ($paymentEntries as $pEntry) {
                    $method = strtolower(trim((string) ($pEntry['payment_method'] ?? 'cash')));
                    $pAmt = (float) ($pEntry['amount'] ?? 0.0);
                    if ($pAmt <= 0) {
                        throw new \InvalidArgumentException('Payment amount must be greater than zero.');
                    }
                    $totalPaidIncoming += $pAmt;
                }

                // Check payment methods like store_credit
                foreach ($paymentEntries as $pEntry) {
                    $method = strtolower(trim((string) ($pEntry['payment_method'] ?? 'cash')));
                    $pAmt = (float) ($pEntry['amount'] ?? 0.0);
                    if ($method === 'store_credit') {
                        if (! $customer) {
                            throw new \RuntimeException('Store credit payment requires a registered customer.');
                        }
                        app(\App\Services\StoreCreditService::class)->issueOrAdjustCredit(
                            $customer,
                            -$pAmt,
                            'payment_used',
                            $user,
                            $storeId,
                            'invoice',
                            null,
                            $clientUuid,
                            'Used store credit for POS sale'
                        );
                    }
                }

                $totalPaidIncoming = round($totalPaidIncoming, 2);

                if (abs($totalPaidIncoming - $grandTotal) > 0.01) {
                    throw new \InvalidArgumentException("Total payments sum ({$totalPaidIncoming}) must equal invoice grand total ({$grandTotal}).");
                }

                // Create Invoice Header
                $inv = Invoice::create([
                    'invoice_number' => $invoiceNumber,
                    'client_trans_uuid' => $request->input('client_trans_uuid') ?? (string) Str::uuid(),
                    'store_id' => $storeId,
                    'pos_session_id' => $activeSession->id,
                    'customer_id' => $request->input('customer_id'),
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

                // Create Line Items and Deduct Inventory
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
                        "POS sale #{$inv->invoice_number}"
                    );
                }

                // Record Payments in invoice_payments table
                foreach ($paymentEntries as $pEntry) {
                    InvoicePayment::create([
                        'invoice_id' => $inv->id,
                        'payment_method' => strtolower(trim((string) ($pEntry['payment_method'] ?? 'cash'))),
                        'amount' => (float) ($pEntry['amount'] ?? 0.0),
                        'transaction_reference' => $pEntry['transaction_reference'] ?? null,
                        'notes' => $pEntry['notes'] ?? 'POS Sale Payment',
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

                // Log Audit Event
                app(\App\Services\AuditService::class)->logEvent([
                    'user_id' => $user->id,
                    'store_id' => $storeId,
                    'pos_session_id' => $activeSession->id,
                    'module' => 'pos_sale',
                    'event_type' => 'invoice_created',
                    'auditable_type' => Invoice::class,
                    'auditable_id' => $inv->id,
                    'client_trans_uuid' => $inv->client_trans_uuid,
                    'after_state' => $inv->toArray(),
                    'reason_notes' => "POS sale invoice #{$inv->invoice_number} created",
                ]);

                return $inv->load([
                    'store',
                    'posSession',
                    'customer',
                    'creator',
                    'items.variantSize.variant.product.brand',
                    'items.variantSize.variant.product.category',
                    'items.variantSize.variant.color',
                    'items.variantSize.size',
                    'payments',
                ]);
            });

            return $this->successResponse(
                new PosSaleResource($invoice),
                'POS sale completed successfully.',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to process POS sale: '.$e->getMessage(), 500);
        }
    }

    public function storePayments(int $id, StorePosSalePaymentRequest $request): JsonResponse
    {
        $user = $request->user();

        // Check Invoice existence first
        $invoiceCheck = Invoice::find($id);
        if (! $invoiceCheck) {
            return $this->errorResponse('POS sale transaction not found.', 404);
        }

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($invoiceCheck->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to process payments for this store.', 403);
            }
        }

        // Check Active POS Session for cashier
        $sessionQuery = PosSession::where('user_id', $user->id)
            ->where('status', PosSessionStatus::OPEN->value);

        if ($request->has('pos_session_id')) {
            $sessionQuery->where('id', (int) $request->input('pos_session_id'));
        }

        $activeSession = $sessionQuery->latest('opened_at')->first();

        if (! $activeSession) {
            return $this->errorResponse('Active POS session required to process payment. Please open a POS session first.', 422);
        }

        try {
            $invoice = DB::transaction(function () use ($id, $request) {
                // Lock Invoice record for update to prevent concurrent double payment
                $inv = Invoice::with(['payments'])->lockForUpdate()->find($id);

                if (! $inv) {
                    throw new ModelNotFoundException('POS sale transaction not found.');
                }

                // Check Sale status
                $statusVal = is_object($inv->status) ? $inv->status->value : $inv->status;
                if ($statusVal === InvoiceStatus::CANCELLED->value || $statusVal === 'cancelled') {
                    throw new \RuntimeException('Cannot process payment for a cancelled sale.');
                }

                // Check Payment status
                $paymentStatusVal = is_object($inv->payment_status) ? $inv->payment_status->value : $inv->payment_status;
                $currentPaid = (float) $inv->paid_amount;
                $grandTotal = (float) $inv->grand_total;

                if ($paymentStatusVal === 'paid' || $currentPaid >= $grandTotal) {
                    throw new \RuntimeException('Invoice is already fully paid.');
                }

                $remainingBalance = max(0.0, round($grandTotal - $currentPaid, 2));

                // Prepare payment entries
                $entries = [];
                if ($request->has('payments') && is_array($request->input('payments')) && count($request->input('payments')) > 0) {
                    $entries = $request->input('payments');
                } else {
                    $entries = [
                        [
                            'payment_method' => $request->input('payment_method', 'cash'),
                            'amount' => (float) $request->input('amount'),
                            'transaction_reference' => $request->input('transaction_reference'),
                            'notes' => $request->input('notes', 'Split Payment Entry'),
                        ],
                    ];
                }

                $incomingTotal = 0.0;
                foreach ($entries as $entry) {
                    $method = strtolower(trim((string) ($entry['payment_method'] ?? 'cash')));
                    if ($method === 'store_credit') {
                        throw new \RuntimeException('Store credit balance functionality is outside current scope.');
                    }
                    $amt = (float) ($entry['amount'] ?? 0.0);
                    if ($amt <= 0) {
                        throw new \InvalidArgumentException('Payment amount must be greater than zero.');
                    }
                    $incomingTotal += $amt;
                }

                $incomingTotal = round($incomingTotal, 2);

                if ($incomingTotal <= 0) {
                    throw new \InvalidArgumentException('Total payment amount must be greater than zero.');
                }

                if ($incomingTotal > $remainingBalance) {
                    throw new \RuntimeException("Payment amount ({$incomingTotal}) exceeds remaining payable balance ({$remainingBalance}).");
                }

                // Create InvoicePayment records
                foreach ($entries as $entry) {
                    InvoicePayment::create([
                        'invoice_id' => $inv->id,
                        'payment_method' => strtolower(trim((string) ($entry['payment_method'] ?? 'cash'))),
                        'amount' => (float) ($entry['amount'] ?? 0.0),
                        'transaction_reference' => $entry['transaction_reference'] ?? null,
                        'notes' => $entry['notes'] ?? 'Split Payment Entry',
                        'payment_time' => now(),
                    ]);
                }

                // Update Invoice paid_amount & payment_status
                $newPaid = round($currentPaid + $incomingTotal, 2);
                $inv->paid_amount = $newPaid;

                if ($newPaid >= $grandTotal) {
                    $inv->payment_status = 'paid';
                } else {
                    $inv->payment_status = 'partial';
                }

                $inv->save();

                return $inv->load(['payments']);
            });

            return $this->successResponse(
                new PosSalePaymentSummaryResource($invoice),
                'Payment recorded successfully.',
                201
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('POS sale transaction not found.', 404);
        } catch (\UnauthorizedException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to record payment: '.$e->getMessage(), 500);
        }
    }

    public function getPayments(Request $request, int $id): JsonResponse
    {
        $sale = Invoice::with(['payments'])->find($id);

        if (! $sale) {
            return $this->errorResponse('POS sale transaction not found.', 404);
        }

        $user = $request->user();

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($sale->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to view payments for this store.', 403);
            }
        }

        return $this->successResponse(
            new PosSalePaymentSummaryResource($sale),
            'Payment summary retrieved successfully.'
        );
    }

    public function getInvoice(Request $request, int $id): JsonResponse
    {
        $sale = Invoice::with([
            'store',
            'posSession',
            'customer',
            'creator',
            'items',
            'payments',
        ])->find($id);

        if (! $sale) {
            return $this->errorResponse('POS sale transaction not found.', 404);
        }

        $user = $request->user();

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($sale->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to view invoices for this store.', 403);
            }
        }

        return $this->successResponse(
            new PosInvoiceReceiptResource($sale),
            'Invoice receipt details retrieved successfully.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // RBAC check: user must have permission to delete sales
        if (! $user->hasPermissionTo('sales.delete') && ! $user->hasPermissionTo('products.delete')) {
            return $this->errorResponse('Forbidden: You do not have permission to delete sale transactions.', 403);
        }

        $sale = Invoice::with(['items', 'payments', 'customer', 'returns'])->find($id);

        if (! $sale) {
            return $this->errorResponse('POS sale transaction not found.', 404);
        }

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($sale->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to delete sales for this store.', 403);
            }
        }

        // Pre-deletion Blocker 1: Sales Returns / Exchanges
        $hasReturns = \App\Models\ReturnSale::where('original_invoice_id', $sale->id)->exists();
        if ($hasReturns || ($sale->returns && $sale->returns->count() > 0)) {
            return $this->errorResponse('This sale has return/refund transactions and cannot be deleted. Reverse/cancel the return first.', 422);
        }

        // Pre-deletion Blocker 2: Day Closing check
        $saleDate = $sale->created_at ? $sale->created_at->format('Y-m-d') : now()->format('Y-m-d');
        $isClosed = \App\Models\DayClosing::where('store_id', $sale->store_id)
            ->whereDate('closing_date', $saleDate)
            ->whereIn('status', ['closed', 'completed'])
            ->exists();

        if ($isClosed && ! $user->hasPermissionTo('day_closing.reopen')) {
            return $this->errorResponse("Cannot delete a transaction from a closed day (Date: {$saleDate}). Reopen the day closing first.", 422);
        }

        try {
            DB::transaction(function () use ($sale, $user) {
                // 1. Reverse Inventory Stock for each invoice item
                foreach ($sale->items as $item) {
                    if ($item->product_variant_size_id && $item->quantity > 0) {
                        $this->inventoryService->addStock(
                            (int) $item->product_variant_size_id,
                            (int) $item->quantity,
                            StockMovementType::STOCK_CORRECTION,
                            Invoice::class,
                            $sale->id,
                            (int) $sale->store_id,
                            0,
                            0,
                            $user,
                            "Reversal: Deletion of POS sale #{$sale->invoice_number}"
                        );
                    }
                }

                // 2. Reverse Store Credit payments (if store_credit was used)
                foreach ($sale->payments as $payment) {
                    $pmMethodStr = is_string($payment->payment_method) ? $payment->payment_method : ($payment->payment_method->value ?? (string) $payment->payment_method);
                    if (strtolower(trim($pmMethodStr)) === 'store_credit' && $sale->customer_id) {
                        $cust = Customer::find($sale->customer_id);
                        if ($cust) {
                            app(\App\Services\StoreCreditService::class)->issueOrAdjustCredit(
                                $cust,
                                (float) $payment->amount,
                                'issue_adjustment',
                                $user,
                                $sale->store_id,
                                'invoice_reversal',
                                $sale->id,
                                null,
                                "Restored store credit from deleted invoice #{$sale->invoice_number}"
                            );
                        }
                    }
                }

                // 3. Delete Payment Records
                InvoicePayment::where('invoice_id', $sale->id)->delete();

                // 4. Reverse Loyalty Points & Customer Totals
                if ($sale->customer_id) {
                    $customer = Customer::find($sale->customer_id);
                    if ($customer && $customer->name !== 'Walk-in Customer') {
                        // Adjust Customer total spent & purchase count
                        $newCount = max(0, (int) $customer->total_purchases_count - 1);
                        $newSpent = max(0.00, round((float) $customer->total_spent_amount - (float) $sale->grand_total, 2));
                        $customer->update([
                            'total_purchases_count' => $newCount,
                            'total_spent_amount' => $newSpent,
                        ]);

                        // Reverse Loyalty Transactions
                        $loyaltyTxs = \App\Models\LoyaltyTransaction::where('reference_type', 'invoice')
                            ->where('reference_id', $sale->id)
                            ->get();

                        foreach ($loyaltyTxs as $ltyTx) {
                            $account = \App\Models\LoyaltyAccount::where('customer_id', $customer->id)->lockForUpdate()->first();
                            if ($account) {
                                if ($ltyTx->transaction_type === 'earn') {
                                    $earned = abs((int) $ltyTx->points);
                                    $account->available_points = max(0, (int) $account->available_points - $earned);
                                    $account->lifetime_earned_points = max(0, (int) $account->lifetime_earned_points - $earned);
                                    $account->save();
                                    $customer->reward_points = $account->available_points;
                                    $customer->save();
                                } elseif ($ltyTx->transaction_type === 'redeem') {
                                    $redeemed = abs((int) $ltyTx->points);
                                    $account->available_points = (int) $account->available_points + $redeemed;
                                    $account->lifetime_redeemed_points = max(0, (int) $account->lifetime_redeemed_points - $redeemed);
                                    $account->save();
                                    $customer->reward_points = $account->available_points;
                                    $customer->save();
                                }
                            }
                            $ltyTx->delete();
                        }
                    }
                }

                // 5. Update status and Soft Delete Invoice
                $sale->status = InvoiceStatus::CANCELLED->value;
                $sale->payment_status = 'unpaid';
                $sale->save();
                $sale->delete();

                // 6. Log Audit Event
                app(\App\Services\AuditService::class)->logEvent([
                    'user_id' => $user->id,
                    'store_id' => $sale->store_id,
                    'pos_session_id' => $sale->pos_session_id,
                    'module' => 'pos_sale',
                    'event_type' => 'invoice_deleted',
                    'auditable_type' => Invoice::class,
                    'auditable_id' => $sale->id,
                    'client_trans_uuid' => $sale->client_trans_uuid,
                    'before_state' => $sale->toArray(),
                    'reason_notes' => "POS sale #{$sale->invoice_number} safely deleted and reversed by user #{$user->id}",
                ]);
            });

            return $this->successResponse(null, "POS sale transaction #{$sale->invoice_number} has been safely deleted and all stock/payment effects reversed.");
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete sale transaction: '.$e->getMessage(), 500);
        }
    }
}

