<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\StockDamageTransaction;
use App\Services\StockDamageService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockDamageController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected StockDamageService $damageService
    ) {}

    /**
     * Display paginated stock damage transactions history.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockDamageTransaction::with([
            'store',
            'creator',
            'items.productVariantSize.productVariant.product',
            'items.productVariantSize.size',
        ]);

        if ($request->filled('store_id')) {
            $query->where('store_id', (int) $request->input('store_id'));
        }

        if ($request->filled('reason')) {
            $query->where('reason', strtolower($request->input('reason')));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('damage_number', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%")
                    ->orWhereHas('items.productVariantSize.productVariant.product', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%")
                            ->orWhere('article_number', 'like', "%{$search}%")
                            ->orWhere('brand', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginated = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->successResponse([
            'items' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 'Stock damage history retrieved successfully.');
    }

    /**
     * Get details of a single stock damage transaction.
     */
    public function show(int $id): JsonResponse
    {
        $damageTx = StockDamageTransaction::with([
            'store',
            'creator',
            'items.productVariantSize.productVariant.product',
            'items.productVariantSize.size',
        ])->find($id);

        if (! $damageTx) {
            return $this->errorResponse('Stock damage transaction not found.', 404);
        }

        return $this->successResponse($damageTx, 'Stock damage details retrieved successfully.');
    }

    /**
     * Record a new Stock Out – Damage transaction.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'nullable|exists:stores,id',
            'reason' => 'required|string|in:damaged,torn,broken,manufacturing_defect,lost,unusable,other',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_variant_size_id' => 'required|exists:product_variant_sizes,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            $damageTx = $this->damageService->processDamageTransaction($validated, $request->user());

            return $this->successResponse($damageTx, 'Stock Out – Damage transaction confirmed successfully.', 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to record stock damage: '.$e->getMessage(), 500);
        }
    }

    /**
     * Search products for Stock Out - Damage with size-wise stock availability.
     */
    public function productSearch(Request $request): JsonResponse
    {
        $term = trim($request->input('q', ''));
        $storeId = (int) $request->input('store_id', 1);

        if (strlen($term) < 1) {
            return $this->successResponse([], 'Search query too short.');
        }

        $products = Product::with([
            'brand',
            'category',
            'variants.color',
            'variants.sizes.size',
        ])
        ->where('is_active', true)
        ->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('article_number', 'like', "%{$term}%")
                ->orWhereHas('brand', function ($bq) use ($term) {
                    $bq->where('name', 'like', "%{$term}%");
                })
                ->orWhereHas('category', function ($cq) use ($term) {
                    $cq->where('name', 'like', "%{$term}%");
                })
                ->orWhereHas('variants', function ($vq) use ($term) {
                    $vq->whereHas('color', function ($colq) use ($term) {
                        $colq->where('name', 'like', "%{$term}%")
                            ->orWhere('code', 'like', "%{$term}%");
                    })
                    ->orWhereHas('sizes', function ($sq) use ($term) {
                        $sq->where('sku', 'like', "%{$term}%")
                            ->orWhere('barcode', 'like', "%{$term}%");
                    });
                });
        })
        ->limit(30)
        ->get();

        $formatted = $products->map(function ($product) use ($storeId) {
            $sizeMatrix = [];
            foreach ($product->variants as $variant) {
                $colorName = $variant->color?->name ?? 'Default Color';
                foreach ($variant->sizes as $variantSize) {
                    $stockRecord = InventoryStock::where('product_variant_size_id', $variantSize->id)
                        ->where('store_id', $storeId)
                        ->first();

                    $sizeName = $variantSize->size?->name ?? ($variantSize->size?->size_number ? "Size {$variantSize->size?->size_number}" : "Size #{$variantSize->size_id}");

                    $sizeMatrix[] = [
                        'product_variant_size_id' => $variantSize->id,
                        'variant_id' => $variant->id,
                        'color_name' => $colorName,
                        'sku' => $variantSize->sku,
                        'barcode' => $variantSize->barcode,
                        'size_name' => $sizeName,
                        'available_stock' => (int) ($stockRecord?->stock_quantity ?? 0),
                        'damage_quantity' => 0,
                    ];
                }
            }

            return [
                'id' => $product->id,
                'name' => $product->name,
                'article_number' => $product->article_number,
                'brand' => $product->brand?->name ?? 'N/A',
                'category' => $product->category?->name ?? 'N/A',
                'sizes' => $sizeMatrix,
            ];
        });

        return $this->successResponse($formatted, 'Product search results retrieved.');
    }
}
