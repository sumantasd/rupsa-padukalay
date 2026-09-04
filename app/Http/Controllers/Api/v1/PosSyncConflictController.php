<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ResolvePosSyncConflictRequest;
use App\Http\Resources\PosSyncConflictResource;
use App\Models\InventoryStock;
use App\Models\PosSyncConflict;
use App\Models\ProductVariantSize;
use App\Services\OfflineSyncService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosSyncConflictController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected OfflineSyncService $syncService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = PosSyncConflict::with(['store', 'posSession', 'user', 'resolver', 'invoice']);

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $query->whereIn('store_id', $userStoreIds);
        } else {
            if ($request->has('store_id')) {
                $query->where('store_id', (int) $request->input('store_id'));
            }
        }

        if ($request->has('status')) {
            $query->where('status', trim((string) $request->input('status')));
        }

        if ($request->has('conflict_type')) {
            $query->where('conflict_type', trim((string) $request->input('conflict_type')));
        }

        if ($request->has('client_trans_uuid')) {
            $query->where('client_trans_uuid', trim((string) $request->input('client_trans_uuid')));
        }

        if ($request->has('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('client_trans_uuid', 'LIKE', "%{$search}%")
                    ->orWhere('conflict_reason', 'LIKE', "%{$search}%")
                    ->orWhere('conflict_type', 'LIKE', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $conflicts = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->successResponse(
            [
                'items' => PosSyncConflictResource::collection($conflicts->items()),
                'pagination' => [
                    'current_page' => $conflicts->currentPage(),
                    'per_page' => $conflicts->perPage(),
                    'total' => $conflicts->total(),
                    'last_page' => $conflicts->lastPage(),
                ],
            ],
            'POS sync conflicts retrieved successfully.'
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $conflict = PosSyncConflict::with(['store', 'posSession', 'user', 'resolver', 'invoice'])->find($id);

        if (! $conflict) {
            return $this->errorResponse('POS sync conflict record not found.', 404);
        }

        $user = $request->user();

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($conflict->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to view sync conflicts for this store.', 403);
            }
        }

        return $this->successResponse(
            new PosSyncConflictResource($conflict),
            'POS sync conflict details retrieved successfully.'
        );
    }

    public function resolve(ResolvePosSyncConflictRequest $request, int $id): JsonResponse
    {
        $conflictCheck = PosSyncConflict::find($id);

        if (! $conflictCheck) {
            return $this->errorResponse('POS sync conflict record not found.', 404);
        }

        $user = $request->user();

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($conflictCheck->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to resolve sync conflicts for this store.', 403);
            }
        }

        $action = trim((string) $request->input('action'));
        $notes = $request->input('resolution_notes', 'Manual reconciliation resolution');

        try {
            $conflict = DB::transaction(function () use ($id, $user, $action, $notes, $request) {
                $c = PosSyncConflict::lockForUpdate()->find($id);

                if (! $c) {
                    throw new \InvalidArgumentException('POS sync conflict record not found.');
                }

                if ($c->status !== 'unresolved') {
                    throw new \RuntimeException('Conflict has already been resolved or dismissed.');
                }

                if ($action === 'dismiss') {
                    $c->status = 'dismissed';
                    $c->resolution_action = 'dismissed_duplicate';
                    $c->resolution_notes = $notes;
                    $c->resolved_by = $user->id;
                    $c->resolved_at = now();
                    $c->save();

                    return $c;
                }

                $payload = $c->payload_snapshot ?? [];

                if ($request->has('store_id') && $request->input('store_id')) {
                    $payload['store_id'] = (int) $request->input('store_id');
                }

                if ($request->has('pos_session_id') && $request->input('pos_session_id')) {
                    $payload['pos_session_id'] = (int) $request->input('pos_session_id');
                }

                if ($action === 'force_override') {
                    // Force stock replenishment if stock is low
                    $storeId = (int) ($payload['store_id'] ?? $c->store_id);
                    foreach ($payload['items'] ?? [] as $item) {
                        $variantSizeId = $item['product_variant_size_id'] ?? null;
                        if (! $variantSizeId && ! empty($item['sku'])) {
                            $vs = ProductVariantSize::where('sku', trim($item['sku']))->first();
                            $variantSizeId = $vs?->id;
                        }

                        if ($variantSizeId) {
                            $reqQty = (int) ($item['quantity'] ?? 1);
                            $stock = InventoryStock::where('product_variant_size_id', $variantSizeId)
                                ->where('store_id', $storeId)
                                ->first();
                            if (! $stock) {
                                InventoryStock::create([
                                    'product_variant_size_id' => $variantSizeId,
                                    'store_id' => $storeId,
                                    'warehouse_id' => 0,
                                    'stock_location_id' => 0,
                                    'stock_quantity' => $reqQty + 5,
                                ]);
                            } elseif ($stock->stock_quantity < $reqQty) {
                                $stock->update(['stock_quantity' => $reqQty + 5]);
                            }
                        }
                    }
                }

                // Attempt to reprocess sale transaction
                $syncSummary = $this->syncService->syncSales($user, [$payload]);

                $resItem = $syncSummary['results'][0] ?? null;

                if ($resItem && in_array($resItem['status'], ['synced', 'already_synced'])) {
                    $c->status = 'resolved';
                    $c->resolution_action = ($action === 'force_override') ? 'force_stock_override' : 'reprocessed_sync';
                    $c->invoice_id = $resItem['invoice_id'] ?? null;
                    $c->resolution_notes = $notes;
                    $c->resolved_by = $user->id;
                    $c->resolved_at = now();
                    $c->save();

                    return $c;
                }

                $errMsg = $resItem['message'] ?? 'Failed to reprocess sale transaction during reconciliation.';
                throw new \RuntimeException('Reconciliation reprocess failed: '.$errMsg);
            });

            return $this->successResponse(
                new PosSyncConflictResource($conflict->load(['store', 'posSession', 'user', 'resolver', 'invoice'])),
                'POS sync conflict resolved successfully.'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to resolve sync conflict: '.$e->getMessage(), 500);
        }
    }
}
