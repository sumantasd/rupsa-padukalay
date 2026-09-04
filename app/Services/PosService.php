<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\SaleType;
use App\Enums\StockMovementType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Product;
use App\Models\ProductVariantSize;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosService
{
    public function __construct(
        protected InventoryService $inventoryService,
        protected TaxService $taxService,
        protected CustomerService $customerService
    ) {}

    /**
     * Item-Number-First POS Search: Returns Article details, color variations, sizes, prices, and available stock.
     */
    public function searchByArticleNumber(string $articleNumber, int $storeId = 0): array
    {
        $products = Product::with([
            'brand',
            'category',
            'hsnCode',
            'variants.color',
            'variants.sizes.size',
            'variants.sizes.inventoryStocks' => function ($q) use ($storeId) {
                $q->where('store_id', $storeId);
            },
        ])
        ->where('article_number', 'LIKE', trim($articleNumber).'%')
        ->where('is_active', true)
        ->get();

        return $products->map(function ($product) use ($storeId) {
            return [
                'product_id' => $product->id,
                'article_number' => $product->article_number,
                'name' => $product->name,
                'brand_name' => $product->brand?->name,
                'category_name' => $product->category?->name,
                'gender' => $product->gender,
                'variants' => $product->variants->map(function ($variant) use ($storeId) {
                    return [
                        'variant_id' => $variant->id,
                        'color_id' => $variant->color_id,
                        'color_name' => $variant->color?->name,
                        'hex_code' => $variant->color?->hex_code,
                        'available_sizes' => $variant->sizes->map(function ($pvs) use ($storeId) {
                            $stockRec = $pvs->inventoryStocks->where('store_id', $storeId)->first();
                            return [
                                'product_variant_size_id' => $pvs->id,
                                'size_number' => $pvs->size?->size_number,
                                'sku' => $pvs->sku,
                                'barcode' => $pvs->barcode,
                                'mrp' => (float) $pvs->mrp,
                                'selling_price' => (float) $pvs->selling_price,
                                'stock_quantity' => $stockRec ? (int) $stockRec->stock_quantity : 0,
                            ];
                        }),
                    ];
                }),
            ];
        })->toArray();
    }

    /**
     * Process high-speed POS sale in an atomic database transaction.
     */
    public function checkout(array $data, User $cashier): Invoice
    {
        $clientUuid = $data['client_trans_uuid'] ?? (string) Str::uuid();

        // Idempotency check for offline sync or duplicate posting
        $existingInvoice = Invoice::where('client_trans_uuid', $clientUuid)->first();
        if ($existingInvoice) {
            return $existingInvoice;
        }

        return DB::transaction(function () use ($data, $cashier, $clientUuid) {
            $storeId = (int) ($data['store_id'] ?? 1);
            $posSessionId = isset($data['pos_session_id']) ? (int) $data['pos_session_id'] : null;

            // Resolve Customer (optional mobile for guest sales)
            $customer = $this->customerService->resolveCustomer(
                $data['customer_mobile'] ?? null,
                $data['customer_name'] ?? null,
                $data['customer_email'] ?? null
            );

            $isGstEnabled = $this->taxService->isGstEnabled();
            $invoiceNumber = 'INV-'.date('Ymd').'-'.str_pad((string) (Invoice::count() + 1), 5, '0', STR_PAD_LEFT);

            // Create Master Invoice Record
            $invoice = Invoice::create([
                'invoice_number' => $invoiceNumber,
                'client_trans_uuid' => $clientUuid,
                'store_id' => $storeId,
                'pos_session_id' => $posSessionId,
                'customer_id' => $customer?->id,
                'subtotal' => 0.00,
                'discount_amount' => (float) ($data['discount_amount'] ?? 0.00),
                'is_gst_enabled' => $isGstEnabled,
                'taxable_amount' => 0.00,
                'total_cgst' => 0.00,
                'total_sgst' => 0.00,
                'total_igst' => 0.00,
                'total_tax' => 0.00,
                'grand_total' => 0.00,
                'paid_amount' => 0.00,
                'change_returned' => 0.00,
                'payment_status' => 'paid',
                'sale_type' => $data['sale_type'] ?? SaleType::POS_COUNTER->value,
                'status' => InvoiceStatus::COMPLETED->value,
                'created_by' => $cashier->id,
            ]);

            $runningSubtotal = 0.00;
            $runningTaxable = 0.00;
            $runningCgst = 0.00;
            $runningSgst = 0.00;
            $runningIgst = 0.00;
            $runningTaxTotal = 0.00;

            // Process Line Items
            foreach ($data['items'] as $itemData) {
                $pvsId = (int) $itemData['product_variant_size_id'];
                $quantity = (int) $itemData['quantity'];
                $itemDiscount = (float) ($itemData['discount_amount'] ?? 0.00);

                // Fetch Product Variant Size with Product & Master Data
                $pvs = ProductVariantSize::with([
                    'variant.product.brand',
                    'variant.product.hsnCode',
                    'variant.color',
                    'size',
                ])->findOrFail($pvsId);

                $product = $pvs->variant->product;
                $unitPrice = isset($itemData['unit_price']) ? (float) $itemData['unit_price'] : (float) $pvs->selling_price;

                // Calculate Tax Breakdown for this item
                $taxCalc = $this->taxService->calculateItemTax(
                    $unitPrice,
                    $quantity,
                    $itemDiscount,
                    $product->hsnCode,
                    isset($itemData['tax_rate_percentage']) ? (float) $itemData['tax_rate_percentage'] : null
                );

                $lineSubtotal = $taxCalc['line_total'];
                $runningSubtotal += $lineSubtotal;
                $runningTaxable += $taxCalc['taxable_value'];
                $runningCgst += $taxCalc['cgst_amount'];
                $runningSgst += $taxCalc['sgst_amount'];
                $runningIgst += $taxCalc['igst_amount'];
                $runningTaxTotal += $taxCalc['total_tax_amount'];

                // Create Snapshot Invoice Item
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_variant_size_id' => $pvs->id,
                    'sku_snapshot' => $pvs->sku,
                    'article_number_snapshot' => $product->article_number,
                    'product_name_snapshot' => $product->name,
                    'color_name_snapshot' => $pvs->variant->color?->name ?? 'N/A',
                    'size_number_snapshot' => $pvs->size?->size_number ?? 'N/A',
                    'hsn_code_snapshot' => $product->hsnCode?->code,
                    'cost_price' => (float) $pvs->cost_price,
                    'mrp' => (float) $pvs->mrp,
                    'unit_price' => $unitPrice,
                    'quantity' => $quantity,
                    'discount_amount' => $itemDiscount,
                    'tax_rate_percentage' => $taxCalc['tax_rate_percentage'],
                    'taxable_value' => $taxCalc['taxable_value'],
                    'cgst_amount' => $taxCalc['cgst_amount'],
                    'sgst_amount' => $taxCalc['sgst_amount'],
                    'igst_amount' => $taxCalc['igst_amount'],
                    'total_tax_amount' => $taxCalc['total_tax_amount'],
                    'subtotal' => $lineSubtotal,
                ]);

                // Deduct Inventory Stock with Lock & Audit Movement
                $allowOverride = (bool) ($data['allow_negative_override'] ?? false);
                $this->inventoryService->deductStock(
                    $pvs->id,
                    $quantity,
                    StockMovementType::SALE_POS,
                    Invoice::class,
                    $invoice->id,
                    $storeId,
                    0,
                    0,
                    $cashier,
                    $allowOverride,
                    "POS Sale Invoice {$invoiceNumber}"
                );
            }

            // Calculate Grand Totals
            $overallDiscount = (float) ($data['discount_amount'] ?? 0.00);
            $grandTotal = max(0.00, round($runningSubtotal - $overallDiscount, 2));

            // Process Payments (Split Payments Supported)
            $totalPaid = 0.00;
            if (! empty($data['payments'])) {
                foreach ($data['payments'] as $pay) {
                    $payAmount = (float) $pay['amount'];
                    InvoicePayment::create([
                        'invoice_id' => $invoice->id,
                        'payment_method' => $pay['payment_method'],
                        'amount' => $payAmount,
                        'transaction_reference' => $pay['transaction_reference'] ?? null,
                        'notes' => $pay['notes'] ?? null,
                        'payment_time' => now(),
                    ]);
                    $totalPaid += $payAmount;
                }
            } else {
                // Default Single Cash Payment
                InvoicePayment::create([
                    'invoice_id' => $invoice->id,
                    'payment_method' => 'cash',
                    'amount' => $grandTotal,
                    'payment_time' => now(),
                ]);
                $totalPaid = $grandTotal;
            }

            $changeReturned = max(0.00, round($totalPaid - $grandTotal, 2));

            // Final Invoice Update
            $invoice->update([
                'subtotal' => round($runningSubtotal, 2),
                'taxable_amount' => round($runningTaxable, 2),
                'total_cgst' => round($runningCgst, 2),
                'total_sgst' => round($runningSgst, 2),
                'total_igst' => round($runningIgst, 2),
                'total_tax' => round($runningTaxTotal, 2),
                'grand_total' => $grandTotal,
                'paid_amount' => $totalPaid,
                'change_returned' => $changeReturned,
            ]);

            // Update Customer purchase stats if customer associated
            if ($customer) {
                $customer->increment('total_purchases_count');
                $customer->increment('total_spent_amount', $grandTotal);
            }

            return $invoice->load(['items', 'payments', 'customer', 'store']);
        });
    }
}
