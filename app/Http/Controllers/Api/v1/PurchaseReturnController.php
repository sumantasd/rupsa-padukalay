<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StorePurchaseReturnRequest;
use App\Http\Resources\PurchaseReturnResource;
use App\Models\ProductVariantSize;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Services\InventoryService;
use App\Services\PurchaseService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseReturnController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected InventoryService $inventoryService,
        protected PurchaseService $purchaseService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = PurchaseReturn::with([
            'purchaseOrder',
            'supplier',
            'store',
            'warehouse',
            'processor',
            'items.variantSize.variant.product.brand',
            'items.variantSize.variant.product.category',
            'items.variantSize.variant.color',
            'items.variantSize.size',
        ]);

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');

            if ($request->has('store_id')) {
                $requestedStoreId = (int) $request->input('store_id');
                if (! $userStoreIds->contains($requestedStoreId)) {
                    return $this->errorResponse('Forbidden: You are not authorized to view purchase returns for this store.', 403);
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

        if ($request->has('purchase_order_id')) {
            $query->where('purchase_order_id', (int) $request->input('purchase_order_id'));
        }

        // SKU Filter
        if ($request->has('sku')) {
            $sku = trim((string) $request->input('sku'));
            $query->whereHas('items.variantSize', function ($q) use ($sku) {
                $q->where('sku', 'LIKE', "%{$sku}%");
            });
        }

        // Article Number Filter
        if ($request->has('article_number')) {
            $article = trim((string) $request->input('article_number'));
            $query->whereHas('items.variantSize.variant.product', function ($q) use ($article) {
                $q->where('article_number', 'LIKE', "%{$article}%");
            });
        }

        // Date Range Filters
        if ($request->has('date_from')) {
            $dateFrom = trim((string) $request->input('date_from'));
            $query->where('created_at', '>=', "{$dateFrom} 00:00:00");
        }

        if ($request->has('date_to')) {
            $dateTo = trim((string) $request->input('date_to'));
            $query->where('created_at', '<=', "{$dateTo} 23:59:59");
        }

        // Search Filter
        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'LIKE', "%{$search}%")
                  ->orWhere('reason', 'LIKE', "%{$search}%")
                  ->orWhereHas('purchaseOrder', function ($pq) use ($search) {
                      $pq->where('po_number', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('name', 'LIKE', "%{$search}%");
                  });
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginated = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->successResponse([
            'items' => PurchaseReturnResource::collection($paginated->items()),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 'Purchase returns retrieved successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $returnRecord = PurchaseReturn::with([
            'purchaseOrder',
            'supplier',
            'store',
            'warehouse',
            'processor',
            'items.variantSize.variant.product.brand',
            'items.variantSize.variant.product.category',
            'items.variantSize.variant.color',
            'items.variantSize.size',
        ])->find($id);

        if (! $returnRecord) {
            return $this->errorResponse('Purchase return record not found.', 404);
        }

        $user = $request->user();
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $hasAccess = false;

            if ($returnRecord->store_id && $userStoreIds->contains($returnRecord->store_id)) {
                $hasAccess = true;
            } elseif ($returnRecord->warehouse_id && $returnRecord->warehouse->stores()->whereIn('stores.id', $userStoreIds)->exists()) {
                $hasAccess = true;
            }

            if (! $hasAccess) {
                return $this->errorResponse('Forbidden: You are not authorized to view purchase returns for this store.', 403);
            }
        }

        return $this->successResponse(
            new PurchaseReturnResource($returnRecord),
            'Purchase return details retrieved successfully.'
        );
    }

    public function store(StorePurchaseReturnRequest $request, ?int $id = null): JsonResponse
    {
        $poId = $id ?? (int) $request->input('purchase_order_id', 0);
        $po = null;
        if ($poId > 0) {
            $po = PurchaseOrder::with(['items.variantSize'])->find($poId);
            if (! $po) {
                return $this->errorResponse('Purchase order not found.', 404);
            }
        }

        $supplierId = (int) ($request->input('supplier_id') ?? $po?->supplier_id ?? 0);
        if ($supplierId <= 0) {
            return $this->errorResponse('Supplier is required for every purchase transaction.', 422);
        }

        if (! $po && $request->filled('items')) {
            try {
                $returnRecord = $this->purchaseService->createPurchaseReturn($request->validated(), $request->user());
                return $this->successResponse(
                    new PurchaseReturnResource($returnRecord),
                    'Purchase return processed successfully.',
                    201
                );
            } catch (\InvalidArgumentException $e) {
                return $this->errorResponse($e->getMessage(), 422);
            } catch (\RuntimeException $e) {
                return $this->errorResponse($e->getMessage(), 422);
            }
        }

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
                return $this->errorResponse('Forbidden: You are not authorized to process returns for this store.', 403);
            }
        }

        $statusValue = is_object($po->status) && property_exists($po->status, 'value') ? $po->status->value : (string) $po->status;
        if (in_array($statusValue, ['draft', 'cancelled'])) {
            return $this->errorResponse('Purchase order is in draft or cancelled status and not eligible for return.', 422);
        }

        try {
            $returnRecord = DB::transaction(function () use ($request, $user, $po, $storeId, $warehouseId, $stockLocationId) {
                $returnNumber = 'PR-'.date('Ymd').'-'.strtoupper(Str::random(6));

                $existingReturnIds = $po->returns()->pluck('id');

                $preparedItems = [];
                $totalReturnAmount = 0.0;

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

                    $returnQty = (int) $item['quantity'];

                    // Already returned quantity calculation
                    $alreadyReturnedQty = (int) PurchaseReturnItem::whereIn('purchase_return_id', $existingReturnIds)
                        ->where('product_variant_size_id', $poItem->product_variant_size_id)
                        ->sum('quantity');

                    $maxReturnable = $poItem->quantity_received - $alreadyReturnedQty;

                    if ($maxReturnable <= 0) {
                        throw new \RuntimeException("Item SKU {$poItem->variantSize->sku} has no remaining received quantity available to return.");
                    }

                    if ($returnQty > $maxReturnable) {
                        throw new \RuntimeException("Cannot return {$returnQty} units of SKU {$poItem->variantSize->sku}. Maximum returnable: {$maxReturnable}.");
                    }

                    // Physical Inventory Row Locking check
                    $stockRecord = $this->inventoryService->getStockRecord(
                        $poItem->product_variant_size_id,
                        $storeId,
                        $warehouseId,
                        $stockLocationId,
                        true
                    );

                    if ($stockRecord->stock_quantity < $returnQty) {
                        throw new \RuntimeException("Insufficient physical stock available for return of SKU {$poItem->variantSize->sku}. Available physical stock: {$stockRecord->stock_quantity}, Requested return: {$returnQty}.");
                    }

                    $costPrice = (float) $poItem->cost_price;
                    $subtotal = round($costPrice * $returnQty, 2);
                    $totalReturnAmount += $subtotal;

                    $preparedItems[] = [
                        'poItem' => $poItem,
                        'returnQty' => $returnQty,
                        'costPrice' => $costPrice,
                        'subtotal' => $subtotal,
                    ];
                }

                // Create PurchaseReturn Header
                $prHeader = PurchaseReturn::create([
                    'return_number' => $returnNumber,
                    'purchase_order_id' => $po->id,
                    'supplier_id' => $po->supplier_id,
                    'store_id' => $storeId > 0 ? $storeId : null,
                    'warehouse_id' => $warehouseId > 0 ? $warehouseId : null,
                    'total_return_amount' => $totalReturnAmount,
                    'reason' => $request->input('reason'),
                    'processed_by' => $user->id,
                ]);

                // Process Physical Inventory Deduction & Movement Logging
                foreach ($preparedItems as $prep) {
                    $this->inventoryService->deductStock(
                        $prep['poItem']->product_variant_size_id,
                        $prep['returnQty'],
                        StockMovementType::PURCHASE_RETURN,
                        PurchaseReturn::class,
                        $prHeader->id,
                        $storeId,
                        $warehouseId,
                        $stockLocationId,
                        $user,
                        false,
                        $request->input('reason') ?? "Purchase return against PO #{$po->po_number}"
                    );

                    PurchaseReturnItem::create([
                        'purchase_return_id' => $prHeader->id,
                        'product_variant_size_id' => $prep['poItem']->product_variant_size_id,
                        'quantity' => $prep['returnQty'],
                        'cost_price' => $prep['costPrice'],
                        'subtotal' => $prep['subtotal'],
                    ]);
                }

                return $prHeader->load([
                    'purchaseOrder',
                    'supplier',
                    'store',
                    'warehouse',
                    'processor',
                    'items.variantSize.variant.product.brand',
                    'items.variantSize.variant.product.category',
                    'items.variantSize.variant.color',
                    'items.variantSize.size',
                ]);
            });

            return $this->successResponse(
                new PurchaseReturnResource($returnRecord),
                'Purchase return processed successfully.',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to process purchase return: '.$e->getMessage(), 500);
        }
    }
}
