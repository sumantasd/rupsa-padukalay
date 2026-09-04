<?php

namespace App\Services;

use App\Models\InventoryStock;
use App\Models\ProductVariantSize;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Store;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StoreTransferAnalyticsService
{
    public function getAuthorizedStoreIds(User $user, ?int $requestedStoreId = null): ?array
    {
        if ($user->roles()->where('name', 'Super Admin')->exists()) {
            return $requestedStoreId ? [$requestedStoreId] : null;
        }

        $userStoreIds = $user->stores()->pluck('stores.id')->toArray();

        if ($requestedStoreId !== null) {
            if (! in_array($requestedStoreId, $userStoreIds)) {
                throw new \RuntimeException('Forbidden: You are not authorized to view analytics for this store.', 403);
            }

            return [$requestedStoreId];
        }

        return $userStoreIds;
    }

    public function buildBaseTransferQuery(array $filters, User $user)
    {
        $fromStoreId = isset($filters['from_store_id']) ? (int) $filters['from_store_id'] : null;
        $toStoreId = isset($filters['to_store_id']) ? (int) $filters['to_store_id'] : null;
        $requestedStoreId = isset($filters['store_id']) ? (int) $filters['store_id'] : null;

        // Verify individual requested store permissions
        if ($fromStoreId !== null) {
            $this->getAuthorizedStoreIds($user, $fromStoreId);
        }
        if ($toStoreId !== null) {
            $this->getAuthorizedStoreIds($user, $toStoreId);
        }
        if ($requestedStoreId !== null) {
            $this->getAuthorizedStoreIds($user, $requestedStoreId);
        }

        $isSuperAdmin = $user->roles()->where('name', 'Super Admin')->exists();
        $userStoreIds = $isSuperAdmin ? null : $user->stores()->pluck('stores.id')->toArray();

        $query = StockTransfer::query();

        if (! $isSuperAdmin && $userStoreIds !== null) {
            $query->where(function ($q) use ($userStoreIds) {
                $q->whereIn('from_store_id', $userStoreIds)
                    ->orWhereIn('to_store_id', $userStoreIds);
            });
        }

        if ($requestedStoreId !== null) {
            $query->where(function ($q) use ($requestedStoreId) {
                $q->where('from_store_id', $requestedStoreId)
                    ->orWhere('to_store_id', $requestedStoreId);
            });
        }

        if ($fromStoreId !== null) {
            $query->where('from_store_id', $fromStoreId);
        }

        if ($toStoreId !== null) {
            $query->where('to_store_id', $toStoreId);
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate('transfer_date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('transfer_date', '<=', $filters['end_date']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', trim((string) $filters['status']));
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('transfer_number', 'LIKE', "%{$search}%")
                    ->orWhere('notes', 'LIKE', "%{$search}%");
            });
        }

        return $query;
    }

    public function getTransferSummary(array $filters, User $user): array
    {
        $query = $this->buildBaseTransferQuery($filters, $user);
        $totalTransfers = (clone $query)->count();

        // Non-cancelled transfers for volume and valuation
        $validTransferIds = (clone $query)
            ->whereNotIn('status', ['cancelled', 'CANCELLED'])
            ->pluck('id')
            ->toArray();

        $totalVolumeSent = 0;
        $totalVolumeReceived = 0;
        $totalValuation = 0.00;

        if (count($validTransferIds) > 0) {
            $itemStats = DB::table('stock_transfer_items')
                ->join('product_variant_sizes', 'stock_transfer_items.product_variant_size_id', '=', 'product_variant_sizes.id')
                ->whereIn('stock_transfer_items.stock_transfer_id', $validTransferIds)
                ->select(
                    DB::raw('SUM(stock_transfer_items.quantity_sent) as sent_qty'),
                    DB::raw('SUM(stock_transfer_items.quantity_received) as rec_qty'),
                    DB::raw('SUM(stock_transfer_items.quantity_sent * product_variant_sizes.cost_price) as valuation')
                )
                ->first();

            $totalVolumeSent = (int) ($itemStats?->sent_qty ?? 0);
            $totalVolumeReceived = (int) ($itemStats?->rec_qty ?? 0);
            $totalValuation = (float) ($itemStats?->valuation ?? 0.00);
        }

        // Status Breakdown
        $statusRows = (clone $query)
            ->select('status', DB::raw('COUNT(id) as cnt'))
            ->groupBy('status')
            ->get();

        $breakdown = [];
        foreach ($statusRows as $row) {
            $stKey = is_object($row->status) && property_exists($row->status, 'value')
                ? $row->status->value
                : (string) $row->status;
            $breakdown[$stKey] = (int) $row->cnt;
        }

        $completedCount = (int) ($breakdown['completed'] ?? $breakdown['COMPLETED'] ?? 0);
        $cancelledCount = (int) ($breakdown['cancelled'] ?? $breakdown['CANCELLED'] ?? 0);
        $nonCancelledCount = max(0, $totalTransfers - $cancelledCount);

        $completionRate = $nonCancelledCount > 0 ? round(($completedCount / $nonCancelledCount) * 100.0, 2) : 0.00;
        $cancellationRate = $totalTransfers > 0 ? round(($cancelledCount / $totalTransfers) * 100.0, 2) : 0.00;

        // Transit Lead Time
        $completedTransfers = (clone $query)
            ->whereIn('status', ['completed', 'COMPLETED'])
            ->whereNotNull('transfer_date')
            ->whereNotNull('received_date')
            ->get();

        $avgLeadTimeHours = 0.00;
        if ($completedTransfers->count() > 0) {
            $totalHours = 0;
            foreach ($completedTransfers as $tr) {
                $transferTs = strtotime($tr->transfer_date->toDateTimeString());
                $receivedTs = strtotime($tr->received_date->toDateTimeString());
                $hours = max(0, ($receivedTs - $transferTs) / 3600);
                $totalHours += $hours;
            }
            $avgLeadTimeHours = round($totalHours / $completedTransfers->count(), 1);
        }

        return [
            'total_transfers' => $totalTransfers,
            'total_volume_sent' => $totalVolumeSent,
            'total_volume_received' => $totalVolumeReceived,
            'total_transfer_valuation' => round($totalValuation, 2),
            'completion_rate' => $completionRate,
            'cancellation_rate' => $cancellationRate,
            'avg_transit_lead_time_hours' => $avgLeadTimeHours,
            'status_breakdown' => $breakdown,
        ];
    }

    public function getTransferMatrix(array $filters, User $user): Collection
    {
        $query = $this->buildBaseTransferQuery($filters, $user);
        $validTransferIds = (clone $query)
            ->whereNotIn('status', ['cancelled', 'CANCELLED'])
            ->pluck('id')
            ->toArray();

        if (count($validTransferIds) === 0) {
            return collect();
        }

        $matrixRows = DB::table('stock_transfers')
            ->join('stores as from_st', 'stock_transfers.from_store_id', '=', 'from_st.id')
            ->join('stores as to_st', 'stock_transfers.to_store_id', '=', 'to_st.id')
            ->leftJoin('stock_transfer_items', 'stock_transfers.id', '=', 'stock_transfer_items.stock_transfer_id')
            ->leftJoin('product_variant_sizes', 'stock_transfer_items.product_variant_size_id', '=', 'product_variant_sizes.id')
            ->whereIn('stock_transfers.id', $validTransferIds)
            ->select(
                'stock_transfers.from_store_id',
                'from_st.name as from_store_name',
                'stock_transfers.to_store_id',
                'to_st.name as to_store_name',
                DB::raw('COUNT(DISTINCT stock_transfers.id) as transfer_count'),
                DB::raw('SUM(stock_transfer_items.quantity_sent) as total_sent_qty'),
                DB::raw('SUM(stock_transfer_items.quantity_received) as total_rec_qty'),
                DB::raw('SUM(stock_transfer_items.quantity_sent * product_variant_sizes.cost_price) as valuation')
            )
            ->groupBy('stock_transfers.from_store_id', 'from_st.name', 'stock_transfers.to_store_id', 'to_st.name')
            ->get();

        return $matrixRows->map(fn ($r) => [
            'from_store_id' => (int) $r->from_store_id,
            'from_store_name' => $r->from_store_name,
            'to_store_id' => (int) $r->to_store_id,
            'to_store_name' => $r->to_store_name,
            'transfer_count' => (int) $r->transfer_count,
            'total_sent_quantity' => (int) ($r->total_sent_qty ?? 0),
            'total_received_quantity' => (int) ($r->total_rec_qty ?? 0),
            'total_valuation' => round((float) ($r->valuation ?? 0.00), 2),
        ]);
    }

    public function getInventoryTurnoverAnalytics(array $filters, User $user): array
    {
        $requestedStoreId = isset($filters['store_id']) ? (int) $filters['store_id'] : null;
        $authorizedStoreIds = $this->getAuthorizedStoreIds($user, $requestedStoreId);

        $stockQuery = InventoryStock::query();
        if ($authorizedStoreIds !== null) {
            $stockQuery->whereIn('store_id', $authorizedStoreIds);
        }

        $totalUnits = (int) (clone $stockQuery)->sum('stock_quantity');

        // Valuation
        $valuationRow = DB::table('inventory_stocks')
            ->join('product_variant_sizes', 'inventory_stocks.product_variant_size_id', '=', 'product_variant_sizes.id')
            ->when($authorizedStoreIds !== null, fn ($q) => $q->whereIn('inventory_stocks.store_id', $authorizedStoreIds))
            ->select(DB::raw('SUM(inventory_stocks.stock_quantity * product_variant_sizes.cost_price) as val'))
            ->first();
        $totalValuation = round((float) ($valuationRow?->val ?? 0.00), 2);

        // Stockout frequency: stock_quantity <= 0
        $stockoutFrequency = (clone $stockQuery)->where('stock_quantity', '<=', 0)->count();

        // Low stock count: 0 < stock_quantity <= reorder_level
        $lowStockCount = (clone $stockQuery)
            ->where('stock_quantity', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'reorder_level')
            ->count();

        // Overstock count: reorder_level > 0 and stock_quantity >= 3 * reorder_level
        $overstockCount = (clone $stockQuery)
            ->where('reorder_level', '>', 0)
            ->whereRaw('stock_quantity >= 3 * reorder_level')
            ->count();

        // Turnover ratio: Outbound movement cost / Average Valuation
        // Using StockMovement OUT movements
        $outboundRow = DB::table('stock_movements')
            ->join('product_variant_sizes', 'stock_movements.product_variant_size_id', '=', 'product_variant_sizes.id')
            ->when($authorizedStoreIds !== null, fn ($q) => $q->whereIn('stock_movements.store_id', $authorizedStoreIds))
            ->whereIn('stock_movements.movement_type', ['sale_pos', 'SALE_POS', 'transfer_out', 'TRANSFER_OUT', 'adjustment_deduct', 'ADJUSTMENT_DEDUCT', 'purchase_return', 'PURCHASE_RETURN'])
            ->select(DB::raw('SUM(ABS(stock_movements.quantity_change) * product_variant_sizes.cost_price) as outbound_cost'))
            ->first();

        $outboundCost = (float) ($outboundRow?->outbound_cost ?? 0.00);

        $turnoverRatio = $totalValuation > 0 ? round($outboundCost / $totalValuation, 2) : 0.00;

        return [
            'store_id' => $requestedStoreId,
            'total_inventory_units' => $totalUnits,
            'total_inventory_valuation' => $totalValuation,
            'inventory_turnover_ratio' => $turnoverRatio,
            'stockout_frequency' => $stockoutFrequency,
            'low_stock_count' => $lowStockCount,
            'overstock_count' => $overstockCount,
        ];
    }

    public function getCrossStoreBalance(array $filters, User $user): LengthAwarePaginator
    {
        $requestedStoreId = isset($filters['store_id']) ? (int) $filters['store_id'] : null;
        $authorizedStoreIds = $this->getAuthorizedStoreIds($user, $requestedStoreId);

        $query = ProductVariantSize::with(['variant.product.brand', 'variant.product.category']);

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'LIKE', "%{$search}%")
                    ->orWhereHas('variant.product', function ($pq) use ($search) {
                        $pq->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('article_number', 'LIKE', "%{$search}%");
                    });
            });
        }

        $perPage = min((int) ($filters['per_page'] ?? 15), 100);
        $paginated = $query->paginate($perPage);

        $allStores = Store::where('is_active', true)
            ->when($authorizedStoreIds !== null, fn ($q) => $q->whereIn('id', $authorizedStoreIds))
            ->get();

        $transformedItems = collect($paginated->items())->map(function ($variantSize) use ($allStores) {
            $stocks = InventoryStock::where('product_variant_size_id', $variantSize->id)->get();

            $storeBreakdown = [];
            $totalStock = 0;
            $maxReorder = 0;

            foreach ($allStores as $st) {
                $stStock = $stocks->firstWhere('store_id', $st->id);
                $qty = $stStock ? (int) $stStock->stock_quantity : 0;
                $reorder = $stStock ? (int) $stStock->reorder_level : 0;

                $totalStock += $qty;
                if ($reorder > $maxReorder) {
                    $maxReorder = $reorder;
                }

                $storeBreakdown[] = [
                    'store_id' => $st->id,
                    'store_name' => $st->name,
                    'stock_quantity' => $qty,
                    'reorder_level' => $reorder,
                ];
            }

            // Determine Overall Stock Status
            $status = 'normal';
            if ($totalStock <= 0) {
                $status = 'stockout';
            } elseif ($maxReorder > 0 && $totalStock <= $maxReorder) {
                $status = 'low_stock';
            } elseif ($maxReorder > 0 && $totalStock >= (3 * $maxReorder)) {
                $status = 'overstock';
            }

            return [
                'product_variant_size_id' => $variantSize->id,
                'sku' => $variantSize->sku,
                'article_number' => $variantSize->variant?->product?->article_number,
                'product_name' => $variantSize->variant?->product?->name,
                'brand_name' => $variantSize->variant?->product?->brand?->name,
                'category_name' => $variantSize->variant?->product?->category?->name,
                'cost_price' => (float) $variantSize->cost_price,
                'selling_price' => (float) $variantSize->selling_price,
                'total_stock_quantity' => $totalStock,
                'reorder_level' => $maxReorder,
                'stock_status' => $status,
                'store_breakdown' => $storeBreakdown,
            ];
        });

        return new LengthAwarePaginator(
            $transformedItems,
            $paginated->total(),
            $paginated->perPage(),
            $paginated->currentPage(),
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    public function getTransferHistory(array $filters, User $user): LengthAwarePaginator
    {
        $query = $this->buildBaseTransferQuery($filters, $user);
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $query->with(['fromStore', 'toStore', 'fromWarehouse', 'toWarehouse', 'items.variantSize'])
            ->orderBy('transfer_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }
}
