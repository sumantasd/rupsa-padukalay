<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreStockAdjustmentRequest;
use App\Http\Resources\StockAdjustmentResource;
use App\Models\ProductVariantSize;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Services\InventoryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockAdjustmentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = StockAdjustment::with([
            'store',
            'warehouse',
            'creator',
            'items.variantSize.variant.product.brand',
            'items.variantSize.variant.product.category',
            'items.variantSize.variant.color',
            'items.variantSize.size',
        ]);

        // Store Access Isolation for Non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');

            if ($request->has('store_id')) {
                $requestedStoreId = (int) $request->input('store_id');
                if (! $userStoreIds->contains($requestedStoreId)) {
                    return $this->errorResponse('Forbidden: You are not authorized to view stock adjustments for this store.', 403);
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

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', (int) $request->input('warehouse_id'));
        }

        if ($request->has('reason')) {
            $query->where('reason', trim((string) $request->input('reason')));
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
                $q->where('adjustment_number', 'LIKE', "%{$search}%")
                  ->orWhere('notes', 'LIKE', "%{$search}%")
                  ->orWhereHas('items.variantSize', function ($sq) use ($search) {
                      $sq->where('sku', 'LIKE', "%{$search}%");
                  });
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginated = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->successResponse([
            'items' => StockAdjustmentResource::collection($paginated->items()),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 'Stock adjustments retrieved successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $adjustment = StockAdjustment::with([
            'store',
            'warehouse',
            'creator',
            'items.variantSize.variant.product.brand',
            'items.variantSize.variant.product.category',
            'items.variantSize.variant.color',
            'items.variantSize.size',
        ])->find($id);

        if (! $adjustment) {
            return $this->errorResponse('Stock adjustment record not found.', 404);
        }

        $user = $request->user();
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $hasAccess = false;

            if ($adjustment->store_id && $userStoreIds->contains($adjustment->store_id)) {
                $hasAccess = true;
            } elseif ($adjustment->warehouse_id && $adjustment->warehouse->stores()->whereIn('stores.id', $userStoreIds)->exists()) {
                $hasAccess = true;
            }

            if (! $hasAccess) {
                return $this->errorResponse('Forbidden: You are not authorized to view stock adjustments for this store.', 403);
            }
        }

        return $this->successResponse(
            new StockAdjustmentResource($adjustment),
            'Stock adjustment details retrieved successfully.'
        );
    }

    public function store(StoreStockAdjustmentRequest $request): JsonResponse
    {
        $user = $request->user();
        $storeId = (int) $request->input('store_id', 0);
        $warehouseId = (int) $request->input('warehouse_id', 0);

        // Store Access Control Validation
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if ($storeId > 0 && ! $userStoreIds->contains($storeId)) {
                return $this->errorResponse('Forbidden: You are not authorized to perform adjustments for this store.', 403);
            }
        }

        try {
            $adjustment = DB::transaction(function () use ($request, $user, $storeId, $warehouseId) {
                $adjNumber = 'ADJ-'.date('Ymd').'-'.strtoupper(Str::random(6));

                $adjRecord = StockAdjustment::create([
                    'adjustment_number' => $adjNumber,
                    'store_id' => $storeId > 0 ? $storeId : null,
                    'warehouse_id' => $warehouseId > 0 ? $warehouseId : null,
                    'reason' => $request->input('reason'),
                    'notes' => $request->input('notes'),
                    'created_by' => $user->id,
                ]);

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

                    $stockLocationId = (int) ($item['stock_location_id'] ?? 0);

                    // Fetch current stock with pessimistic row locking
                    $currentStockObj = $this->inventoryService->getStockRecord(
                        $variantSize->id,
                        $storeId,
                        $warehouseId,
                        $stockLocationId,
                        true
                    );
                    $oldQty = $currentStockObj->stock_quantity;

                    // Calculate new_quantity and quantity_adjusted
                    $qtyAdjusted = 0;
                    $newQty = $oldQty;

                    if (isset($item['type']) && $item['type'] === 'add') {
                        $qtyAdjusted = (int) ($item['quantity'] ?? 0);
                        $newQty = $oldQty + $qtyAdjusted;
                    } elseif (isset($item['type']) && $item['type'] === 'deduct') {
                        $qtyAdjusted = -((int) ($item['quantity'] ?? 0));
                        $newQty = $oldQty + $qtyAdjusted;
                    } elseif (isset($item['quantity_adjusted'])) {
                        $qtyAdjusted = (int) $item['quantity_adjusted'];
                        $newQty = $oldQty + $qtyAdjusted;
                    } elseif (isset($item['new_quantity'])) {
                        $newQty = (int) $item['new_quantity'];
                        $qtyAdjusted = $newQty - $oldQty;
                    }

                    if ($qtyAdjusted === 0) {
                        continue;
                    }

                    // Enforce Zero Stock policy
                    if ($newQty < 0) {
                        throw new \RuntimeException(
                            "Insufficient stock for SKU {$variantSize->sku}. Current stock: {$oldQty}, Attempted deduction: ".abs($qtyAdjusted)."."
                        );
                    }

                    // Apply Inventory Change & Log Stock Movement Ledger
                    if ($qtyAdjusted > 0) {
                        $this->inventoryService->addStock(
                            $variantSize->id,
                            $qtyAdjusted,
                            StockMovementType::ADJUSTMENT_ADD,
                            StockAdjustment::class,
                            $adjRecord->id,
                            $storeId,
                            $warehouseId,
                            $stockLocationId,
                            $user,
                            $request->input('notes') ?? "Stock adjustment ({$request->input('reason')})"
                        );
                    } else {
                        $this->inventoryService->deductStock(
                            $variantSize->id,
                            abs($qtyAdjusted),
                            StockMovementType::ADJUSTMENT_DEDUCT,
                            StockAdjustment::class,
                            $adjRecord->id,
                            $storeId,
                            $warehouseId,
                            $stockLocationId,
                            $user,
                            false,
                            $request->input('notes') ?? "Stock adjustment ({$request->input('reason')})"
                        );
                    }

                    // Record Adjustment Item Detail
                    StockAdjustmentItem::create([
                        'stock_adjustment_id' => $adjRecord->id,
                        'product_variant_size_id' => $variantSize->id,
                        'old_quantity' => $oldQty,
                        'new_quantity' => $newQty,
                        'quantity_adjusted' => $qtyAdjusted,
                    ]);
                }

                return $adjRecord->load([
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
                new StockAdjustmentResource($adjustment),
                'Stock adjustment processed successfully.',
                201
            );

        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to process stock adjustment: '.$e->getMessage(), 500);
        }
    }
}
