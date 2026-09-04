<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ReceivePurchaseOrderRequest;
use App\Http\Requests\Master\StorePurchaseOrderRequest;
use App\Http\Requests\Master\UpdatePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\ProductVariantSize;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\InventoryService;
use App\Services\TaxService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseOrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TaxService $taxService,
        protected InventoryService $inventoryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = PurchaseOrder::with([
            'supplier',
            'store',
            'warehouse',
            'creator',
            'items.variantSize.variant.product.brand',
            'items.variantSize.variant.product.category',
            'items.variantSize.variant.product.images',
            'items.variantSize.variant.color',
            'items.variantSize.size',
        ]);

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');

            if ($request->has('store_id')) {
                $requestedStoreId = (int) $request->input('store_id');
                if (! $userStoreIds->contains($requestedStoreId)) {
                    return $this->errorResponse('Forbidden: You are not authorized to view purchase orders for this store.', 403);
                }
            }

            $query->where(function ($q) use ($userStoreIds) {
                $q->whereIn('store_id', $userStoreIds)
                  ->orWhereHas('warehouse.stores', function ($sq) use ($userStoreIds) {
                      $sq->whereIn('stores.id', $userStoreIds);
                  });
            });
        } else {
            if ($request->has('store_id')) {
                $query->where('store_id', (int) $request->input('store_id'));
            }
        }

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', (int) $request->input('supplier_id'));
        }

        if ($request->has('status')) {
            $query->where('status', trim((string) $request->input('status')));
        }

        // SKU Filter
        if ($request->has('sku')) {
            $sku = trim((string) $request->input('sku'));
            $query->whereHas('items.variantSize', function ($q) use ($sku) {
                $q->where('sku', 'LIKE', "%{$sku}%");
            });
        }

        // Search Filter
        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'LIKE', "%{$search}%")
                  ->orWhere('supplier_invoice_number', 'LIKE', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('company_name', 'LIKE', "%{$search}%");
                  });
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginated = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->successResponse([
            'items' => PurchaseOrderResource::collection($paginated->items()),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 'Purchase orders retrieved successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $po = PurchaseOrder::with([
            'supplier',
            'store',
            'warehouse',
            'creator',
            'items.variantSize.variant.product.brand',
            'items.variantSize.variant.product.category',
            'items.variantSize.variant.product.images',
            'items.variantSize.variant.color',
            'items.variantSize.size',
        ])->find($id);

        if (! $po) {
            return $this->errorResponse('Purchase order not found.', 404);
        }

        $user = $request->user();
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $hasAccess = false;

            if ($po->store_id && $userStoreIds->contains($po->store_id)) {
                $hasAccess = true;
            } elseif ($po->warehouse_id && $po->warehouse->stores()->whereIn('stores.id', $userStoreIds)->exists()) {
                $hasAccess = true;
            }

            if (! $hasAccess) {
                return $this->errorResponse('Forbidden: You are not authorized to view purchase orders for this store.', 403);
            }
        }

        return $this->successResponse(
            new PurchaseOrderResource($po),
            'Purchase order details retrieved successfully.'
        );
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $user = $request->user();
        $storeId = (int) $request->input('store_id', 0);

        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if ($storeId > 0 && ! $userStoreIds->contains($storeId)) {
                return $this->errorResponse('Forbidden: You are not authorized to create purchase orders for this store.', 403);
            }
        }

        if (! $request->filled('supplier_id') || (int) $request->input('supplier_id') <= 0) {
            return $this->errorResponse('Supplier is required for every purchase transaction.', 422);
        }

        try {
            $po = DB::transaction(function () use ($request, $user) {
                $poNumber = 'PO-'.date('Ymd').'-'.strtoupper(Str::random(6));

                $subtotal = 0.0;
                $itemsData = [];

                foreach ($request->input('items', []) as $item) {
                    $variantSize = null;
                    if (! empty($item['product_variant_size_id'])) {
                        $variantSize = ProductVariantSize::find((int) $item['product_variant_size_id']);
                    } elseif (! empty($item['sku'])) {
                        $variantSize = ProductVariantSize::where('sku', trim($item['sku']))->first();
                    }

                    if (! $variantSize) {
                        throw new \InvalidArgumentException('Invalid SKU or product variant size ID.');
                    }

                    $qty = (int) $item['quantity_ordered'];
                    $costPrice = (float) $item['cost_price'];
                    $mrp = isset($item['mrp']) ? (float) $item['mrp'] : (float) ($variantSize->mrp ?? 0.0);
                    $sellingPrice = isset($item['selling_price']) ? (float) $item['selling_price'] : (float) ($variantSize->selling_price ?? 0.0);

                    $lineTotal = round($costPrice * $qty, 2);
                    $subtotal += $lineTotal;

                    $itemsData[] = [
                        'variantSize' => $variantSize,
                        'qty' => $qty,
                        'cost_price' => $costPrice,
                        'mrp' => $mrp,
                        'selling_price' => $sellingPrice,
                        'line_total' => $lineTotal,
                    ];
                }

                $discount = (float) $request->input('discount_amount', 0.0);
                $taxAmount = 0.0;

                if ($this->taxService->isGstEnabled()) {
                    // Overall tax calculation based on GST setting
                    $taxRes = $this->taxService->calculateItemTax($subtotal, 1, $discount);
                    $taxAmount = $taxRes['total_tax_amount'];
                }

                $grandTotal = max(0.0, round($subtotal + $taxAmount - $discount, 2));

                $purchaseOrder = PurchaseOrder::create([
                    'po_number' => $poNumber,
                    'supplier_id' => $request->input('supplier_id'),
                    'store_id' => $request->input('store_id'),
                    'warehouse_id' => $request->input('warehouse_id'),
                    'order_date' => $request->input('order_date', now()->format('Y-m-d')),
                    'supplier_invoice_number' => $request->input('supplier_invoice_number'),
                    'status' => 'draft',
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'discount_amount' => $discount,
                    'grand_total' => $grandTotal,
                    'paid_amount' => 0.00,
                    'due_amount' => $grandTotal,
                    'notes' => $request->input('notes'),
                    'created_by' => $user->id,
                ]);

                foreach ($itemsData as $item) {
                    PurchaseOrderItem::create([
                        'purchase_order_id' => $purchaseOrder->id,
                        'product_variant_size_id' => $item['variantSize']->id,
                        'quantity_ordered' => $item['qty'],
                        'quantity_received' => 0,
                        'cost_price' => $item['cost_price'],
                        'mrp' => $item['mrp'],
                        'selling_price' => $item['selling_price'],
                        'total_cost' => $item['line_total'],
                    ]);
                }

                return $purchaseOrder->load([
                    'supplier',
                    'store',
                    'warehouse',
                    'creator',
                    'items.variantSize.variant.product.brand',
                    'items.variantSize.variant.product.category',
                    'items.variantSize.variant.color',
                    'items.variantSize.size',
                ]);
            });

            return $this->successResponse(
                new PurchaseOrderResource($po),
                'Purchase order created successfully.',
                201
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create purchase order: '.$e->getMessage(), 500);
        }
    }

    public function update(UpdatePurchaseOrderRequest $request, int $id): JsonResponse
    {
        $po = PurchaseOrder::find($id);

        if (! $po) {
            return $this->errorResponse('Purchase order not found.', 404);
        }

        $user = $request->user();
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if ($po->store_id && ! $userStoreIds->contains($po->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to update purchase orders for this store.', 403);
            }
        }

        $statusValue = is_object($po->status) && property_exists($po->status, 'value') ? $po->status->value : (string) $po->status;
        if ($statusValue !== 'draft') {
            return $this->errorResponse('Only purchase orders in draft status can be updated.', 422);
        }

        try {
            $updatedPo = DB::transaction(function () use ($request, $po) {
                if ($request->has('supplier_id')) {
                    $po->supplier_id = $request->input('supplier_id');
                }
                if ($request->has('store_id')) {
                    $po->store_id = $request->input('store_id');
                }
                if ($request->has('warehouse_id')) {
                    $po->warehouse_id = $request->input('warehouse_id');
                }
                if ($request->has('order_date')) {
                    $po->order_date = $request->input('order_date');
                }
                if ($request->has('supplier_invoice_number')) {
                    $po->supplier_invoice_number = $request->input('supplier_invoice_number');
                }
                if ($request->has('notes')) {
                    $po->notes = $request->input('notes');
                }
                if ($request->has('discount_amount')) {
                    $po->discount_amount = (float) $request->input('discount_amount');
                }

                if ($request->has('items')) {
                    $po->items()->delete();

                    $subtotal = 0.0;
                    foreach ($request->input('items', []) as $item) {
                        $variantSize = null;
                        if (! empty($item['product_variant_size_id'])) {
                            $variantSize = ProductVariantSize::find((int) $item['product_variant_size_id']);
                        } elseif (! empty($item['sku'])) {
                            $variantSize = ProductVariantSize::where('sku', trim($item['sku']))->first();
                        }

                        if (! $variantSize) {
                            throw new \InvalidArgumentException('Invalid SKU or product variant size ID.');
                        }

                        $qty = (int) $item['quantity_ordered'];
                        $costPrice = (float) $item['cost_price'];
                        $mrp = isset($item['mrp']) ? (float) $item['mrp'] : (float) ($variantSize->mrp ?? 0.0);
                        $sellingPrice = isset($item['selling_price']) ? (float) $item['selling_price'] : (float) ($variantSize->selling_price ?? 0.0);

                        $lineTotal = round($costPrice * $qty, 2);
                        $subtotal += $lineTotal;

                        PurchaseOrderItem::create([
                            'purchase_order_id' => $po->id,
                            'product_variant_size_id' => $variantSize->id,
                            'quantity_ordered' => $qty,
                            'quantity_received' => 0,
                            'cost_price' => $costPrice,
                            'mrp' => $mrp,
                            'selling_price' => $sellingPrice,
                            'total_cost' => $lineTotal,
                        ]);
                    }

                    $po->subtotal = $subtotal;
                    $taxAmount = 0.0;
                    if ($this->taxService->isGstEnabled()) {
                        $taxRes = $this->taxService->calculateItemTax($subtotal, 1, $po->discount_amount);
                        $taxAmount = $taxRes['total_tax_amount'];
                    }
                    $po->tax_amount = $taxAmount;
                    $po->grand_total = max(0.0, round($subtotal + $taxAmount - $po->discount_amount, 2));
                    $po->due_amount = $po->grand_total;
                }

                $po->save();

                return $po->load([
                    'supplier',
                    'store',
                    'warehouse',
                    'creator',
                    'items.variantSize.variant.product.brand',
                    'items.variantSize.variant.product.category',
                    'items.variantSize.variant.color',
                    'items.variantSize.size',
                ]);
            });

            return $this->successResponse(
                new PurchaseOrderResource($updatedPo),
                'Purchase order updated successfully.'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update purchase order: '.$e->getMessage(), 500);
        }
    }

    public function receive(ReceivePurchaseOrderRequest $request, int $id): JsonResponse
    {
        $po = PurchaseOrder::with(['items.variantSize'])->find($id);

        if (! $po) {
            return $this->errorResponse('Purchase order not found.', 404);
        }

        $user = $request->user();
        $storeId = (int) ($request->input('store_id') ?? $po->store_id ?? 0);
        $warehouseId = (int) ($request->input('warehouse_id') ?? $po->warehouse_id ?? 0);
        $stockLocationId = (int) ($request->input('stock_location_id', 0));

        // Store Access Control Validation
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if ($storeId > 0 && ! $userStoreIds->contains($storeId)) {
                return $this->errorResponse('Forbidden: You are not authorized to receive goods for this store.', 403);
            }
        }

        $statusValue = is_object($po->status) && property_exists($po->status, 'value') ? $po->status->value : (string) $po->status;
        if (in_array($statusValue, ['received', 'cancelled'])) {
            return $this->errorResponse('Purchase order is already fully received or cancelled.', 422);
        }

        try {
            $updatedPo = DB::transaction(function () use ($request, $user, $po, $storeId, $warehouseId, $stockLocationId) {
                foreach ($request->input('items', []) as $item) {
                    $poItem = null;

                    if (! empty($item['purchase_order_item_id'])) {
                        $poItem = $po->items->firstWhere('id', (int) $item['purchase_order_item_id']);
                    } elseif (! empty($item['product_variant_size_id'])) {
                        $poItem = $po->items->firstWhere('product_variant_size_id', (int) $item['product_variant_size_id']);
                    } elseif (! empty($item['sku'])) {
                        $poItem = $po->items->first(function ($pi) use ($item) {
                            return $pi->variantSize && $pi->variantSize->sku === trim($item['sku']);
                        });
                    }

                    if (! $poItem) {
                        throw new \InvalidArgumentException("Item does not belong to Purchase Order #{$po->po_number}.");
                    }

                    $remaining = $poItem->quantity_ordered - $poItem->quantity_received;
                    if ($remaining <= 0) {
                        throw new \RuntimeException("Item SKU {$poItem->variantSize->sku} is already fully received.");
                    }

                    $receivedQty = (int) $item['quantity_received'];
                    if ($receivedQty > $remaining) {
                        throw new \RuntimeException("Cannot receive {$receivedQty} units of SKU {$poItem->variantSize->sku}. Only {$remaining} units remaining.");
                    }

                    // INCREASE PHYSICAL INVENTORY STOCK atomically and log purchase_receive movement
                    $this->inventoryService->addStock(
                        $poItem->product_variant_size_id,
                        $receivedQty,
                        StockMovementType::PURCHASE_RECEIVE,
                        PurchaseOrder::class,
                        $po->id,
                        $storeId,
                        $warehouseId,
                        $stockLocationId,
                        $user,
                        $request->input('notes') ?? "Goods received against PO #{$po->po_number}"
                    );

                    // Update PO item received quantity
                    $poItem->quantity_received += $receivedQty;
                    $poItem->save();
                }

                // Refresh PO items status
                $po->load('items');
                $totalOrdered = $po->items->sum('quantity_ordered');
                $totalReceived = $po->items->sum('quantity_received');

                if ($totalReceived >= $totalOrdered) {
                    $po->status = PurchaseStatus::RECEIVED->value;
                } else {
                    $po->status = PurchaseStatus::PARTIAL->value;
                }

                $po->received_date = now()->format('Y-m-d');
                $po->save();

                return $po->load([
                    'supplier',
                    'store',
                    'warehouse',
                    'creator',
                    'items.variantSize.variant.product.brand',
                    'items.variantSize.variant.product.category',
                    'items.variantSize.variant.color',
                    'items.variantSize.size',
                ]);
            });

            return $this->successResponse(
                new PurchaseOrderResource($updatedPo),
                'Goods received successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to process goods receiving: '.$e->getMessage(), 500);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasPermissionTo('purchases.delete') && ! $user->hasPermissionTo('products.delete') && ! $user->hasPermissionTo('procurement.create')) {
            return $this->errorResponse('Forbidden: You do not have permission to delete purchase orders.', 403);
        }

        $po = PurchaseOrder::with(['items'])->find($id);

        if (! $po) {
            return $this->errorResponse('Purchase order not found.', 404);
        }

        // Check store access for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if ($po->store_id > 0 && ! $userStoreIds->contains($po->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to delete purchase orders for this store.', 403);
            }
        }

        // Check GRN / Bill dependencies
        $hasGrn = \App\Models\GoodsReceive::where('purchase_order_id', $po->id)->exists();
        $hasBill = \App\Models\PurchaseBill::where('purchase_order_id', $po->id)->exists();

        if ($hasGrn || $hasBill) {
            return $this->errorResponse('Cannot delete purchase order with existing GRN or Purchase Bill records. Delete dependent bills/GRNs first.', 422);
        }

        try {
            DB::transaction(function () use ($po, $user) {
                PurchaseOrderItem::where('purchase_order_id', $po->id)->delete();
                $po->delete();

                app(\App\Services\AuditService::class)->logEvent([
                    'user_id' => $user->id,
                    'store_id' => $po->store_id,
                    'module' => 'procurement',
                    'event_type' => 'purchase_order_deleted',
                    'auditable_type' => PurchaseOrder::class,
                    'auditable_id' => $po->id,
                    'before_state' => $po->toArray(),
                    'reason_notes' => "Purchase Order #{$po->po_number} deleted by user #{$user->id}",
                ]);
            });

            return $this->successResponse(null, "Purchase order #{$po->po_number} has been deleted successfully.");
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete purchase order: '.$e->getMessage(), 500);
        }
    }
}

