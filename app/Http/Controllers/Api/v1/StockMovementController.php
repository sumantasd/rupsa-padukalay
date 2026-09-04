<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockMovementResource;
use App\Models\StockMovement;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = StockMovement::with([
            'variantSize.variant.product.brand',
            'variantSize.variant.product.category',
            'variantSize.variant.color',
            'variantSize.size',
            'store',
            'warehouse',
            'stockLocation',
            'creator',
        ]);

        // Non-Super Admin users: Enforce Store Access Isolation
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');

            if ($request->has('store_id')) {
                $requestedStoreId = (int) $request->input('store_id');
                if (! $userStoreIds->contains($requestedStoreId)) {
                    return $this->errorResponse('Forbidden: You are not authorized to view stock movements for this store.', 403);
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

        // Product ID Filter
        if ($request->has('product_id')) {
            $productId = (int) $request->input('product_id');
            $query->whereHas('variantSize.variant', function ($q) use ($productId) {
                $q->where('product_id', $productId);
            });
        }

        // Movement Type Filter
        if ($request->has('movement_type')) {
            $query->where('movement_type', trim((string) $request->input('movement_type')));
        }

        // Reference Type Filter
        if ($request->has('reference_type')) {
            $query->where('reference_type', trim((string) $request->input('reference_type')));
        }

        // Reference ID Filter
        if ($request->has('reference_id')) {
            $query->where('reference_id', (int) $request->input('reference_id'));
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

        // General Search (SKU, Barcode, Article Number, Product Name, Notes)
        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('notes', 'LIKE', "%{$search}%")
                  ->orWhereHas('variantSize', function ($sq) use ($search) {
                      $sq->where('sku', 'LIKE', "%{$search}%")
                        ->orWhere('barcode', 'LIKE', "%{$search}%");
                  })->orWhereHas('variantSize.variant.product', function ($pq) use ($search) {
                      $pq->where('name', 'LIKE', "%{$search}%")
                         ->orWhere('article_number', 'LIKE', "%{$search}%");
                  });
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginated = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->successResponse([
            'items' => StockMovementResource::collection($paginated->items()),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 'Stock movements retrieved successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $movement = StockMovement::with([
            'variantSize.variant.product.brand',
            'variantSize.variant.product.category',
            'variantSize.variant.color',
            'variantSize.size',
            'store',
            'warehouse',
            'stockLocation',
            'creator',
        ])->find($id);

        if (! $movement) {
            return $this->errorResponse('Stock movement record not found.', 404);
        }

        $user = $request->user();
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $hasAccess = false;

            if ($movement->store_id && $userStoreIds->contains($movement->store_id)) {
                $hasAccess = true;
            } elseif ($movement->warehouse_id && $movement->warehouse->stores()->whereIn('stores.id', $userStoreIds)->exists()) {
                $hasAccess = true;
            }

            if (! $hasAccess) {
                return $this->errorResponse('Forbidden: You are not authorized to view stock movements for this store.', 403);
            }
        }

        return $this->successResponse(
            new StockMovementResource($movement),
            'Stock movement details retrieved successfully.'
        );
    }
}
