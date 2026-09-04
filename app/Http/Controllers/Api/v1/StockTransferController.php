<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreStockTransferRequest;
use App\Http\Resources\StockTransferResource;
use App\Models\ProductVariantSize;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Services\InventoryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockTransferController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = StockTransfer::with([
            'fromStore',
            'fromWarehouse',
            'toStore',
            'toWarehouse',
            'transferredBy',
            'receivedBy',
            'items.variantSize.variant.product.brand',
            'items.variantSize.variant.product.category',
            'items.variantSize.variant.color',
            'items.variantSize.size',
        ]);

        // Store Access Isolation for Non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');

            if ($request->has('source_store_id')) {
                $srcStoreId = (int) $request->input('source_store_id');
                if (! $userStoreIds->contains($srcStoreId)) {
                    return $this->errorResponse('Forbidden: You are not authorized to view stock transfers for this source store.', 403);
                }
            }

            if ($request->has('destination_store_id')) {
                $dstStoreId = (int) $request->input('destination_store_id');
                if (! $userStoreIds->contains($dstStoreId)) {
                    return $this->errorResponse('Forbidden: You are not authorized to view stock transfers for this destination store.', 403);
                }
            }

            $query->where(function ($q) use ($userStoreIds) {
                $q->whereIn('from_store_id', $userStoreIds)
                  ->orWhereIn('to_store_id', $userStoreIds)
                  ->orWhereHas('fromWarehouse.stores', function ($sq) use ($userStoreIds) {
                      $sq->whereIn('stores.id', $userStoreIds);
                  })
                  ->orWhereHas('toWarehouse.stores', function ($sq) use ($userStoreIds) {
                      $sq->whereIn('stores.id', $userStoreIds);
                  });
            });
        } else {
            if ($request->has('source_store_id')) {
                $query->where('from_store_id', (int) $request->input('source_store_id'));
            }
            if ($request->has('destination_store_id')) {
                $query->where('to_store_id', (int) $request->input('destination_store_id'));
            }
        }

        if ($request->has('source_warehouse_id')) {
            $query->where('from_warehouse_id', (int) $request->input('source_warehouse_id'));
        }

        if ($request->has('destination_warehouse_id')) {
            $query->where('to_warehouse_id', (int) $request->input('destination_warehouse_id'));
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
                $q->where('transfer_number', 'LIKE', "%{$search}%")
                  ->orWhere('notes', 'LIKE', "%{$search}%")
                  ->orWhereHas('items.variantSize', function ($sq) use ($search) {
                      $sq->where('sku', 'LIKE', "%{$search}%");
                  });
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginated = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->successResponse([
            'items' => StockTransferResource::collection($paginated->items()),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 'Stock transfers retrieved successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $transfer = StockTransfer::with([
            'fromStore',
            'fromWarehouse',
            'toStore',
            'toWarehouse',
            'transferredBy',
            'receivedBy',
            'items.variantSize.variant.product.brand',
            'items.variantSize.variant.product.category',
            'items.variantSize.variant.color',
            'items.variantSize.size',
        ])->find($id);

        if (! $transfer) {
            return $this->errorResponse('Stock transfer record not found.', 404);
        }

        $user = $request->user();
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $hasAccess = false;

            if ($transfer->from_store_id && $userStoreIds->contains($transfer->from_store_id)) {
                $hasAccess = true;
            } elseif ($transfer->to_store_id && $userStoreIds->contains($transfer->to_store_id)) {
                $hasAccess = true;
            }

            if (! $hasAccess) {
                return $this->errorResponse('Forbidden: You are not authorized to view stock transfers for this store.', 403);
            }
        }

        return $this->successResponse(
            new StockTransferResource($transfer),
            'Stock transfer details retrieved successfully.'
        );
    }

    public function store(StoreStockTransferRequest $request): JsonResponse
    {
        $user = $request->user();
        $fromStoreId = (int) $request->input('from_store_id', 0);
        $fromWarehouseId = (int) $request->input('from_warehouse_id', 0);
        $toStoreId = (int) $request->input('to_store_id', 0);
        $toWarehouseId = (int) $request->input('to_warehouse_id', 0);

        // Store Access Control Validation
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');

            if ($fromStoreId > 0 && ! $userStoreIds->contains($fromStoreId)) {
                return $this->errorResponse('Forbidden: You are not authorized to transfer stock from this source store.', 403);
            }

            if ($toStoreId > 0 && ! $userStoreIds->contains($toStoreId)) {
                return $this->errorResponse('Forbidden: You are not authorized to transfer stock to this destination store.', 403);
            }
        }

        try {
            $transfer = DB::transaction(function () use ($request, $user, $fromStoreId, $fromWarehouseId, $toStoreId, $toWarehouseId) {
                $trfNumber = 'TRF-'.date('Ymd').'-'.strtoupper(Str::random(6));

                $transferRecord = StockTransfer::create([
                    'transfer_number' => $trfNumber,
                    'from_store_id' => $fromStoreId > 0 ? $fromStoreId : null,
                    'from_warehouse_id' => $fromWarehouseId > 0 ? $fromWarehouseId : null,
                    'to_store_id' => $toStoreId > 0 ? $toStoreId : null,
                    'to_warehouse_id' => $toWarehouseId > 0 ? $toWarehouseId : null,
                    'status' => 'completed',
                    'transfer_date' => now(),
                    'received_date' => now(),
                    'transferred_by' => $user->id,
                    'received_by' => $user->id,
                    'notes' => $request->input('notes'),
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

                    $quantity = (int) $item['quantity'];
                    if ($quantity <= 0) {
                        throw new \InvalidArgumentException('Transfer quantity must be greater than zero.');
                    }

                    $fromLocId = (int) ($item['from_stock_location_id'] ?? 0);
                    $toLocId = (int) ($item['to_stock_location_id'] ?? 0);

                    // Row-locking check on source stock
                    $sourceStock = $this->inventoryService->getStockRecord(
                        $variantSize->id,
                        $fromStoreId,
                        $fromWarehouseId,
                        $fromLocId,
                        true
                    );

                    if ($sourceStock->stock_quantity < $quantity) {
                        throw new \RuntimeException(
                            "Insufficient stock for SKU {$variantSize->sku} at source location. Available: {$sourceStock->stock_quantity}, Requested: {$quantity}."
                        );
                    }

                    // Atomic Deduction at Source -> Transfer Out Movement
                    $this->inventoryService->deductStock(
                        $variantSize->id,
                        $quantity,
                        StockMovementType::TRANSFER_OUT,
                        StockTransfer::class,
                        $transferRecord->id,
                        $fromStoreId,
                        $fromWarehouseId,
                        $fromLocId,
                        $user,
                        false,
                        $request->input('notes') ?? "Stock transfer out to destination"
                    );

                    // Atomic Addition at Destination -> Transfer In Movement
                    $this->inventoryService->addStock(
                        $variantSize->id,
                        $quantity,
                        StockMovementType::TRANSFER_IN,
                        StockTransfer::class,
                        $transferRecord->id,
                        $toStoreId,
                        $toWarehouseId,
                        $toLocId,
                        $user,
                        $request->input('notes') ?? "Stock transfer in from source"
                    );

                    // Record Stock Transfer Item
                    StockTransferItem::create([
                        'stock_transfer_id' => $transferRecord->id,
                        'product_variant_size_id' => $variantSize->id,
                        'quantity_sent' => $quantity,
                        'quantity_received' => $quantity,
                    ]);
                }

                return $transferRecord->load([
                    'fromStore',
                    'fromWarehouse',
                    'toStore',
                    'toWarehouse',
                    'transferredBy',
                    'receivedBy',
                    'items.variantSize.variant.product.brand',
                    'items.variantSize.variant.product.category',
                    'items.variantSize.variant.color',
                    'items.variantSize.size',
                ]);
            });

            return $this->successResponse(
                new StockTransferResource($transfer),
                'Stock transfer completed successfully.',
                201
            );

        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to process stock transfer: '.$e->getMessage(), 500);
        }
    }
}
