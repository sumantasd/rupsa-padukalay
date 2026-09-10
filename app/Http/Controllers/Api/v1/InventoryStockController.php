<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryStockResource;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductVariantSize;
use App\Models\StockMovement;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryStockController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = InventoryStock::with([
            'variantSize.variant.product.brand',
            'variantSize.variant.product.category',
            'variantSize.variant.product.images',
            'variantSize.variant.color',
            'variantSize.size',
            'store',
            'warehouse',
            'stockLocation',
        ]);

        // Non-Super Admin users: Enforce Store Access Isolation
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');

            if ($request->has('store_id')) {
                $requestedStoreId = (int) $request->input('store_id');
                if (! $userStoreIds->contains($requestedStoreId)) {
                    return $this->errorResponse('Forbidden: You are not authorized to view inventory for this store.', 403);
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

        if ($request->has('stock_location_id')) {
            $query->where('stock_location_id', (int) $request->input('stock_location_id'));
        }

        // SKU Filter
        if ($request->has('sku')) {
            $sku = trim((string) $request->input('sku'));
            $query->whereHas('variantSize', function ($q) use ($sku) {
                $q->where('sku', 'LIKE', "%{$sku}%");
            });
        }

        // Article Number Filter
        if ($request->has('article_number')) {
            $article = trim((string) $request->input('article_number'));
            $query->whereHas('variantSize.variant.product', function ($q) use ($article) {
                $q->where('article_number', 'LIKE', "%{$article}%");
            });
        }

        // General Search (Name, Article Number, SKU, Barcode)
        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereHas('variantSize', function ($sq) use ($search) {
                    $sq->where('sku', 'LIKE', "%{$search}%")
                      ->orWhere('barcode', 'LIKE', "%{$search}%");
                })->orWhereHas('variantSize.variant.product', function ($pq) use ($search) {
                    $pq->where('name', 'LIKE', "%{$search}%")
                       ->orWhere('article_number', 'LIKE', "%{$search}%");
                });
            });
        }

        // Brand Filter
        if ($request->has('brand_id')) {
            $brandId = (int) $request->input('brand_id');
            $query->whereHas('variantSize.variant.product', function ($q) use ($brandId) {
                $q->where('brand_id', $brandId);
            });
        }

        // Category Filter
        if ($request->has('category_id')) {
            $categoryId = (int) $request->input('category_id');
            $query->whereHas('variantSize.variant.product', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }

        // Colour Filter
        if ($request->has('color_id')) {
            $colorId = (int) $request->input('color_id');
            $query->whereHas('variantSize.variant', function ($q) use ($colorId) {
                $q->where('color_id', $colorId);
            });
        }

        // Size Filter
        if ($request->has('size_id')) {
            $sizeId = (int) $request->input('size_id');
            $query->whereHas('variantSize', function ($q) use ($sizeId) {
                $q->where('size_id', $sizeId);
            });
        }

        // Stock Status Filter (in_stock, low_stock, out_of_stock)
        if ($request->has('status')) {
            $status = strtolower(trim((string) $request->input('status')));
            if ($status === 'out_of_stock') {
                $query->where('stock_quantity', '<=', 0);
            } elseif ($status === 'low_stock') {
                $query->where('stock_quantity', '>', 0)
                      ->whereColumn('stock_quantity', '<=', 'reorder_level');
            } elseif ($status === 'in_stock') {
                $query->where(function ($q) {
                    $q->where('stock_quantity', '>', 0)
                      ->where(function ($sq) {
                          $sq->whereNull('reorder_level')
                             ->orWhereColumn('stock_quantity', '>', 'reorder_level');
                      });
                });
            }
        }

        // Sorting
        $sortBy = strtolower(trim((string) $request->input('sort_by', 'id')));
        $sortOrder = strtolower(trim((string) $request->input('sort_order', 'desc'))) === 'asc' ? 'asc' : 'desc';

        if (in_array($sortBy, ['stock_quantity', 'id', 'created_at', 'updated_at'])) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginated = $query->paginate($perPage);

        return $this->successResponse([
            'items' => InventoryStockResource::collection($paginated->items()),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 'Inventory stocks retrieved successfully.');
    }

    public function overview(Request $request): JsonResponse
    {
        $stocksRes = $this->index($request);
        $responseData = $stocksRes->getData(true);

        // Real-time KPI summary calculations
        $totalProducts = Product::where('is_active', true)->count();
        $totalSkus = ProductVariantSize::count();
        $totalUnits = (int) InventoryStock::sum('stock_quantity');

        // Stock value calculation (Cost & Retail)
        $allStocks = InventoryStock::with(['variantSize.variant.product'])->get();
        $totalStockValueCost = 0.0;
        $totalRetailValue = 0.0;
        $lowStockCount = 0;
        $outOfStockCount = 0;

        foreach ($allStocks as $st) {
            $qty = max(0, (int) $st->stock_quantity);
            $reorder = (int) ($st->reorder_level ?? 3);
            $sellingPrice = (float) ($st->variantSize?->selling_price ?? 0.0);
            $costPrice = (float) ($st->variantSize?->cost_price ?? ($sellingPrice > 0 ? round($sellingPrice * 0.6, 2) : 0.0));

            $totalStockValueCost += ($qty * $costPrice);
            $totalRetailValue += ($qty * $sellingPrice);

            if ((int) $st->stock_quantity <= 0) {
                $outOfStockCount++;
            } elseif ($reorder > 0 && (int) $st->stock_quantity <= $reorder) {
                $lowStockCount++;
            }
        }

        $todayStockIn = (int) StockMovement::where('created_at', '>=', now()->startOfDay())
            ->where('quantity_change', '>', 0)
            ->sum('quantity_change');

        $todayStockOut = (int) abs((int) StockMovement::where('created_at', '>=', now()->startOfDay())
            ->where('quantity_change', '<', 0)
            ->sum('quantity_change'));

        $kpis = [
            'total_products' => $totalProducts,
            'total_skus' => $totalSkus,
            'total_units' => $totalUnits,
            'total_stock_value' => round($totalStockValueCost, 2),
            'total_retail_value' => round($totalRetailValue, 2),
            'potential_gross_margin' => round($totalRetailValue - $totalStockValueCost, 2),
            'low_stock_items' => $lowStockCount,
            'out_of_stock_items' => $outOfStockCount,
            'today_stock_in' => $todayStockIn,
            'today_stock_out' => $todayStockOut,
        ];

        return $this->successResponse([
            'kpis' => $kpis,
            'items' => $responseData['data']['items'] ?? [],
            'pagination' => $responseData['data']['pagination'] ?? [],
        ], 'Real-time inventory overview retrieved successfully.');
    }

    public function lowStock(Request $request): JsonResponse
    {
        $request->merge(['status' => 'low_stock']);

        $user = $request->user();
        $query = InventoryStock::with([
            'variantSize.variant.product.brand',
            'variantSize.variant.product.category',
            'variantSize.variant.product.images',
            'variantSize.variant.color',
            'variantSize.size',
            'store',
        ])->where(function ($q) {
            $q->where('stock_quantity', '<=', 0)
              ->orWhereColumn('stock_quantity', '<=', 'reorder_level');
        });

        if ($request->has('critical_only') && $request->boolean('critical_only')) {
            $query->where('stock_quantity', '<=', 0);
        }

        if ($request->has('brand_id')) {
            $brandId = (int) $request->input('brand_id');
            $query->whereHas('variantSize.variant.product', function ($q) use ($brandId) {
                $q->where('brand_id', $brandId);
            });
        }

        if ($request->has('category_id')) {
            $categoryId = (int) $request->input('category_id');
            $query->whereHas('variantSize.variant.product', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }

        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->whereHas('variantSize', function ($sq) use ($search) {
                    $sq->where('sku', 'LIKE', "%{$search}%");
                })->orWhereHas('variantSize.variant.product', function ($pq) use ($search) {
                    $pq->where('name', 'LIKE', "%{$search}%")
                       ->orWhere('article_number', 'LIKE', "%{$search}%");
                });
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginated = $query->orderBy('stock_quantity', 'asc')->paginate($perPage);

        return $this->successResponse([
            'items' => InventoryStockResource::collection($paginated->items()),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 'Low stock alerts retrieved successfully.');
    }

    public function reconciliation(Request $request, string $sku): JsonResponse
    {
        $variantSize = ProductVariantSize::with([
            'variant.product.brand',
            'variant.product.category',
            'variant.color',
            'size',
        ])->where('sku', trim($sku))->first();

        if (! $variantSize) {
            // Try searching by article number
            $variantSize = ProductVariantSize::with([
                'variant.product.brand',
                'variant.product.category',
                'variant.color',
                'size',
            ])->whereHas('variant.product', function ($q) use ($sku) {
                $q->where('article_number', trim($sku));
            })->first();
        }

        if (! $variantSize) {
            return $this->errorResponse("Product SKU or Article # '{$sku}' not found.", 404);
        }

        $stockRecord = InventoryStock::where('product_variant_size_id', $variantSize->id)->first();
        $systemStock = $stockRecord ? (int) $stockRecord->stock_quantity : 0;

        $recentMovements = StockMovement::where('product_variant_size_id', $variantSize->id)
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        return $this->successResponse([
            'product_variant_size_id' => $variantSize->id,
            'sku' => $variantSize->sku,
            'article_number' => $variantSize->variant?->product?->article_number,
            'product_name' => $variantSize->variant?->product?->name,
            'brand_name' => $variantSize->variant?->product?->brand?->name,
            'color_name' => $variantSize->variant?->color?->name,
            'size_display' => 'IND ' . ($variantSize->size?->size_number ?? 'N/A'),
            'system_stock' => $systemStock,
            'cost_price' => (float) ($variantSize->selling_price ? round($variantSize->selling_price * 0.6, 2) : 0.0),
            'recent_movements' => \App\Http\Resources\StockMovementResource::collection($recentMovements),
        ], 'Stock reconciliation record retrieved successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $stock = InventoryStock::with([
            'variantSize.variant.product.brand',
            'variantSize.variant.product.category',
            'variantSize.variant.product.images',
            'variantSize.variant.color',
            'variantSize.size',
            'store',
            'warehouse',
            'stockLocation',
        ])->find($id);

        if (! $stock) {
            return $this->errorResponse('Inventory stock record not found.', 404);
        }

        $user = $request->user();
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $hasAccess = false;

            if ($stock->store_id && $userStoreIds->contains($stock->store_id)) {
                $hasAccess = true;
            } elseif ($stock->warehouse_id && $stock->warehouse->stores()->whereIn('stores.id', $userStoreIds)->exists()) {
                $hasAccess = true;
            }

            if (! $hasAccess) {
                return $this->errorResponse('Forbidden: You are not authorized to view inventory for this store.', 403);
            }
        }

        return $this->successResponse(
            new InventoryStockResource($stock),
            'Inventory stock details retrieved successfully.'
        );
    }

    public function bulkAdd(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'store_id' => 'required|integer|exists:stores,id',
            'product_id' => 'required|integer|exists:products,id',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.product_variant_size_id' => 'required|integer|exists:product_variant_sizes,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $storeId = (int) $validated['store_id'];
        $productId = (int) $validated['product_id'];
        $notes = trim((string) ($validated['notes'] ?? 'Direct Stock Add'));
        $items = $validated['items'];

        // Non-Super Admin Store Isolation Check
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($storeId)) {
                return $this->errorResponse('Forbidden: You are not authorized to add stock for this store.', 403);
            }
        }

        $store = \App\Models\Store::find($storeId);
        $product = Product::with(['brand'])->find($productId);

        if (! $product) {
            return $this->errorResponse('Product not found.', 404);
        }

        try {
            $updatedSummary = \Illuminate\Support\Facades\DB::transaction(function () use ($items, $storeId, $product, $user, $notes) {
                $updatedItems = [];
                $totalQuantityAdded = 0;

                /** @var \App\Services\InventoryService $inventoryService */
                $inventoryService = app(\App\Services\InventoryService::class);

                foreach ($items as $item) {
                    $pvsId = (int) $item['product_variant_size_id'];
                    $qtyToAdd = (int) $item['quantity'];

                    if ($qtyToAdd <= 0) {
                        continue;
                    }

                    $pvs = ProductVariantSize::with(['variant', 'size'])->find($pvsId);
                    if (! $pvs || $pvs->variant?->product_id !== $product->id) {
                        throw new \InvalidArgumentException("Variant size ID {$pvsId} does not belong to product ID {$product->id}.");
                    }

                    $stockRecord = $inventoryService->getStockRecord($pvsId, $storeId, 0, 0, true);
                    $previousStock = (int) $stockRecord->stock_quantity;

                    $updatedStockRecord = $inventoryService->addStock(
                        productVariantSizeId: $pvsId,
                        quantity: $qtyToAdd,
                        movementType: \App\Enums\StockMovementType::ADJUSTMENT_ADD,
                        referenceType: 'stock_add',
                        referenceId: null,
                        storeId: $storeId,
                        warehouseId: 0,
                        stockLocationId: 0,
                        user: $user,
                        notes: $notes ?: 'Direct Stock Add'
                    );

                    $newStock = (int) $updatedStockRecord->stock_quantity;
                    $totalQuantityAdded += $qtyToAdd;

                    $sizeNum = $pvs->size?->size_number ?? 'N/A';
                    $sizeDisplay = is_numeric($sizeNum) || str_starts_with(strtoupper($sizeNum), 'IND')
                        ? (str_starts_with(strtoupper($sizeNum), 'IND') ? $sizeNum : 'IND ' . $sizeNum)
                        : $sizeNum;

                    $updatedItems[] = [
                        'product_variant_size_id' => $pvsId,
                        'sku' => $pvs->sku,
                        'size_name' => $sizeDisplay,
                        'previous_stock' => $previousStock,
                        'added_quantity' => $qtyToAdd,
                        'new_stock' => $newStock,
                    ];
                }

                if (count($updatedItems) === 0) {
                    throw new \InvalidArgumentException('No valid positive stock addition quantities were provided.');
                }

                return [
                    'total_items_updated' => count($updatedItems),
                    'total_quantity_added' => $totalQuantityAdded,
                    'updated_items' => $updatedItems,
                ];
            });

            return $this->successResponse([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'article_number' => $product->article_number ?? 'N/A',
                'brand_name' => $product->brand?->name ?? 'RUPSA',
                'store_id' => $store->id,
                'store_name' => $store->name,
                'total_items_updated' => $updatedSummary['total_items_updated'],
                'total_quantity_added' => $updatedSummary['total_quantity_added'],
                'updated_items' => $updatedSummary['updated_items'],
            ], 'Stock updated successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            return $this->errorResponse('Stock update failed: ' . $e->getMessage(), 500);
        }
    }
}

