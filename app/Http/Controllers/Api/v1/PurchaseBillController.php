<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\PurchaseBill;
use App\Services\PurchaseService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseBillController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PurchaseService $purchaseService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = PurchaseBill::with([
            'supplier',
            'purchaseOrder',
            'goodsReceive',
            'store',
            'createdBy',
            'payments',
            'items.variantSize.variant.product.brand',
            'items.variantSize.variant.color',
            'items.variantSize.size',
        ]);

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', (int) $request->input('supplier_id'));
        }

        if ($request->has('payment_status') && $request->input('payment_status') !== '') {
            $query->where('payment_status', trim((string) $request->input('payment_status')));
        }

        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('bill_number', 'LIKE', "%{$search}%")
                  ->orWhere('supplier_invoice_number', 'LIKE', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('name', 'LIKE', "%{$search}%")
                         ->orWhere('company_name', 'LIKE', "%{$search}%");
                  });
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginated = $query->orderBy('id', 'desc')->paginate($perPage);

        $formatted = collect($paginated->items())->map(function ($bill) {
            return [
                'id' => $bill->id,
                'bill_number' => $bill->bill_number,
                'supplier_invoice_number' => $bill->supplier_invoice_number,
                'supplier_id' => $bill->supplier_id,
                'supplier_name' => $bill->supplier?->name ?? 'N/A',
                'supplier_company' => $bill->supplier?->company_name,
                'supplier_phone' => $bill->supplier?->phone,
                'supplier_gstin' => $bill->supplier?->gstin,
                'purchase_order_id' => $bill->purchase_order_id,
                'po_number' => $bill->purchaseOrder?->po_number,
                'goods_receive_id' => $bill->goods_receive_id,
                'grn_number' => $bill->goodsReceive?->grn_number,
                'store_name' => $bill->store?->name ?? 'RUPSA PADUKALAYA - Main Outlet',
                'bill_date' => $bill->bill_date?->format('Y-m-d'),
                'due_date' => $bill->due_date?->format('Y-m-d'),
                'payment_terms' => $bill->payment_terms ?? 'Net 30 Days',
                'subtotal' => (float) $bill->subtotal,
                'discount_amount' => (float) $bill->discount_amount,
                'tax_amount' => (float) $bill->tax_amount,
                'grand_total' => (float) $bill->grand_total,
                'paid_amount' => (float) $bill->paid_amount,
                'due_amount' => (float) $bill->due_amount,
                'payment_status' => $bill->payment_status,
                'notes' => $bill->notes,
                'created_by_name' => $bill->createdBy?->name ?? 'System',
                'created_at' => $bill->created_at?->toIso8601String(),
                'items' => collect($bill->items)->map(function ($item) {
                    $pvs = $item->variantSize;
                    $variant = $pvs?->variant;
                    $product = $variant?->product;
                    $sizeNum = $pvs?->size?->size_number ?? 'N/A';
                    $sizeDisplay = is_numeric($sizeNum) || str_starts_with(strtoupper($sizeNum), 'IND') ? (str_starts_with(strtoupper($sizeNum), 'IND') ? $sizeNum : 'IND ' . $sizeNum) : $sizeNum;

                    return [
                        'id' => $item->id,
                        'product_variant_size_id' => $item->product_variant_size_id,
                        'article_number' => $product?->article_number ?? 'N/A',
                        'product_name' => $product?->name ?? 'Footwear Item',
                        'brand_name' => $product?->brand?->name ?? 'Generic Brand',
                        'color_name' => $variant?->color?->name ?? 'Std',
                        'size_display' => $sizeDisplay,
                        'sku' => $pvs?->sku ?? 'N/A',
                        'quantity' => (int) $item->quantity,
                        'cost_price' => (float) $item->cost_price,
                        'discount_amount' => (float) $item->discount_amount,
                        'tax_amount' => (float) $item->tax_amount,
                        'total_cost' => (float) $item->total_cost,
                    ];
                }),
                'payments' => collect($bill->payments)->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'payment_number' => $p->payment_number,
                        'payment_date' => $p->payment_date?->format('Y-m-d'),
                        'amount' => (float) $p->amount,
                        'payment_method' => $p->payment_method,
                        'transaction_reference' => $p->transaction_reference,
                        'notes' => $p->notes,
                    ];
                }),
            ];
        });

        return $this->successResponse([
            'items' => $formatted,
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 'Purchase Bills retrieved successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $bill = PurchaseBill::with([
            'supplier',
            'purchaseOrder',
            'goodsReceive',
            'store',
            'createdBy',
            'payments',
            'items.variantSize.variant.product.brand',
            'items.variantSize.variant.color',
            'items.variantSize.size',
        ])->find($id);

        if (! $bill) {
            return $this->errorResponse('Purchase Bill not found.', 404);
        }

        return $this->successResponse([
            'id' => $bill->id,
            'bill_number' => $bill->bill_number,
            'supplier_invoice_number' => $bill->supplier_invoice_number,
            'supplier_id' => $bill->supplier_id,
            'supplier_name' => $bill->supplier?->name ?? 'N/A',
            'supplier_company' => $bill->supplier?->company_name,
            'supplier_phone' => $bill->supplier?->phone,
            'supplier_gstin' => $bill->supplier?->gstin,
            'purchase_order_id' => $bill->purchase_order_id,
            'po_number' => $bill->purchaseOrder?->po_number,
            'goods_receive_id' => $bill->goods_receive_id,
            'grn_number' => $bill->goodsReceive?->grn_number,
            'store_name' => $bill->store?->name ?? 'RUPSA PADUKALAYA - Main Outlet',
            'bill_date' => $bill->bill_date?->format('Y-m-d'),
            'due_date' => $bill->due_date?->format('Y-m-d'),
            'payment_terms' => $bill->payment_terms ?? 'Net 30 Days',
            'subtotal' => (float) $bill->subtotal,
            'discount_amount' => (float) $bill->discount_amount,
            'tax_amount' => (float) $bill->tax_amount,
            'grand_total' => (float) $bill->grand_total,
            'paid_amount' => (float) $bill->paid_amount,
            'due_amount' => (float) $bill->due_amount,
            'payment_status' => $bill->payment_status,
            'notes' => $bill->notes,
            'created_by_name' => $bill->createdBy?->name ?? 'System',
            'created_at' => $bill->created_at?->toIso8601String(),
            'items' => collect($bill->items)->map(function ($item) {
                $pvs = $item->variantSize;
                $variant = $pvs?->variant;
                $product = $variant?->product;
                $sizeNum = $pvs?->size?->size_number ?? 'N/A';
                $sizeDisplay = is_numeric($sizeNum) || str_starts_with(strtoupper($sizeNum), 'IND') ? (str_starts_with(strtoupper($sizeNum), 'IND') ? $sizeNum : 'IND ' . $sizeNum) : $sizeNum;

                return [
                    'id' => $item->id,
                    'product_variant_size_id' => $item->product_variant_size_id,
                    'article_number' => $product?->article_number ?? 'N/A',
                    'product_name' => $product?->name ?? 'Footwear Item',
                    'brand_name' => $product?->brand?->name ?? 'Generic Brand',
                    'color_name' => $variant?->color?->name ?? 'Std',
                    'size_display' => $sizeDisplay,
                    'sku' => $pvs?->sku ?? 'N/A',
                    'quantity' => (int) $item->quantity,
                    'cost_price' => (float) $item->cost_price,
                    'discount_amount' => (float) $item->discount_amount,
                    'tax_amount' => (float) $item->tax_amount,
                    'total_cost' => (float) $item->total_cost,
                ];
            }),
            'payments' => collect($bill->payments)->map(function ($p) {
                return [
                    'id' => $p->id,
                    'payment_number' => $p->payment_number,
                    'payment_date' => $p->payment_date?->format('Y-m-d'),
                    'amount' => (float) $p->amount,
                    'payment_method' => $p->payment_method,
                    'transaction_reference' => $p->transaction_reference,
                    'notes' => $p->notes,
                ];
            }),
        ], 'Purchase Bill details retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'supplier_invoice_number' => ['nullable', 'string', 'max:100'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'goods_receive_id' => ['nullable', 'integer', 'exists:goods_receives,id'],
            'bill_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'in:cash,upi,bank_transfer,card,other'],
            'transaction_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_size_id' => ['required', 'integer', 'exists:product_variant_sizes,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.cost_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $bill = $this->purchaseService->createPurchaseBill($validated, $request->user());

            return $this->successResponse(
                $bill,
                'Purchase Bill created successfully! Supplier ledger updated.',
                201
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function pay(Request $request, int $id): JsonResponse
    {
        $bill = PurchaseBill::find($id);

        if (! $bill) {
            return $this->errorResponse('Purchase Bill not found.', 404);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['nullable', 'date'],
            'payment_method' => ['required', 'string', 'in:cash,upi,bank_transfer,card,other'],
            'transaction_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $paymentData = array_merge($validated, [
                'supplier_id' => $bill->supplier_id,
                'purchase_bill_id' => $bill->id,
            ]);

            $payment = $this->purchaseService->recordSupplierPayment($paymentData, $request->user());

            return $this->successResponse(
                $payment,
                'Supplier payment recorded against Purchase Bill successfully.',
                200
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // RBAC check
        if (! $user->hasPermissionTo('purchases.delete') && ! $user->hasPermissionTo('products.delete') && ! $user->hasPermissionTo('procurement.create')) {
            return $this->errorResponse('Forbidden: You do not have permission to delete purchase transactions.', 403);
        }

        $bill = PurchaseBill::with([
            'items.variantSize.variant.product',
            'items.variantSize.variant.color',
            'items.variantSize.size',
            'payments',
            'returns',
        ])->find($id);

        if (! $bill) {
            return $this->errorResponse('Purchase Bill not found.', 404);
        }

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if ($bill->store_id > 0 && ! $userStoreIds->contains($bill->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to delete purchase bills for this store.', 403);
            }
        }

        // Pre-deletion Blocker 1: Purchase Returns
        $hasReturns = \App\Models\PurchaseReturn::where('purchase_bill_id', $bill->id)->exists();
        if ($hasReturns || ($bill->returns && $bill->returns->count() > 0)) {
            return $this->errorResponse('This purchase bill has an associated purchase return and cannot be deleted. Reverse/cancel the purchase return first.', 422);
        }

        // Pre-deletion Blocker 2: Mandatory Stock Consumption Safety Check!
        $storeId = $bill->store_id > 0 ? $bill->store_id : 1;
        $unconsumedBlockers = [];

        foreach ($bill->items as $item) {
            $pvs = $item->variantSize;
            if ($pvs && $item->quantity > 0) {
                $stockRecord = \App\Models\InventoryStock::where('product_variant_size_id', $pvs->id)
                    ->where('store_id', $storeId)
                    ->first();

                $currentQty = (int) ($stockRecord?->stock_quantity ?? 0);
                $purchasedQty = (int) $item->quantity;

                if ($currentQty < $purchasedQty) {
                    $article = $pvs->variant?->product?->article_number ?? 'N/A';
                    $productName = $pvs->variant?->product?->name ?? 'Footwear Item';
                    $color = $pvs->variant?->color?->name ?? 'Std';
                    $size = $pvs->size?->size_number ?? 'N/A';
                    $sku = $pvs->sku ?? 'N/A';

                    $unconsumedBlockers[] = "SKU {$sku} ({$productName}, Article #{$article}, Color: {$color}, Size: {$size}): Purchased {$purchasedQty} pcs, but only {$currentQty} pcs currently available in stock.";
                }
            }
        }

        if (count($unconsumedBlockers) > 0) {
            $details = implode(' | ', $unconsumedBlockers);
            return $this->errorResponse("Cannot delete this purchase because stock from this transaction has already been used in subsequent inventory transactions. {$details}", 422);
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($bill, $user, $storeId) {
                // 1. Reverse Inventory Stock for each purchase item
                foreach ($bill->items as $item) {
                    if ($item->product_variant_size_id && $item->quantity > 0) {
                        app(\App\Services\InventoryService::class)->deductStock(
                            (int) $item->product_variant_size_id,
                            (int) $item->quantity,
                            \App\Enums\StockMovementType::STOCK_CORRECTION,
                            PurchaseBill::class,
                            $bill->id,
                            $storeId,
                            0,
                            0,
                            $user,
                            false,
                            "Reversal: Deletion of Purchase Bill #{$bill->bill_number}"
                        );
                    }
                }

                // 2. Delete Supplier Payment Records linked to this bill
                \App\Models\SupplierPayment::where('purchase_bill_id', $bill->id)->delete();

                // 3. Delete Purchase Bill Items
                \App\Models\PurchaseBillItem::where('purchase_bill_id', $bill->id)->delete();

                // 4. Update linked GoodsReceive (GRN) / PurchaseOrder (PO) status if present
                if ($bill->goods_receive_id) {
                    $grn = \App\Models\GoodsReceive::find($bill->goods_receive_id);
                    if ($grn) {
                        \App\Models\GoodsReceiveItem::where('goods_receive_id', $grn->id)->delete();
                        $grn->delete();
                    }
                }

                if ($bill->purchase_order_id) {
                    $po = \App\Models\PurchaseOrder::find($bill->purchase_order_id);
                    if ($po) {
                        $po->update([
                            'status' => \App\Enums\PurchaseStatus::ORDERED->value,
                        ]);
                    }
                }

                // 5. Delete Purchase Bill
                $bill->delete();

                // 6. Log Audit Event
                app(\App\Services\AuditService::class)->logEvent([
                    'user_id' => $user->id,
                    'store_id' => $bill->store_id,
                    'module' => 'procurement',
                    'event_type' => 'purchase_bill_deleted',
                    'auditable_type' => PurchaseBill::class,
                    'auditable_id' => $bill->id,
                    'before_state' => $bill->toArray(),
                    'reason_notes' => "Purchase Bill #{$bill->bill_number} safely deleted and reversed by user #{$user->id}",
                ]);
            });

            return $this->successResponse(null, "Purchase bill #{$bill->bill_number} has been safely deleted and stock/supplier balances reversed.");
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete purchase bill: '.$e->getMessage(), 500);
        }
    }
}

