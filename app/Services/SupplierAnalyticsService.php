<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SupplierAnalyticsService
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

    public function buildBasePoQuery(Supplier $supplier, array $filters, User $user)
    {
        $requestedStoreId = isset($filters['store_id']) ? (int) $filters['store_id'] : null;
        $authorizedStoreIds = $this->getAuthorizedStoreIds($user, $requestedStoreId);

        $query = PurchaseOrder::where('supplier_id', $supplier->id);

        if ($authorizedStoreIds !== null) {
            $query->whereIn('store_id', $authorizedStoreIds);
        }

        if (! empty($filters['start_date'])) {
            $query->whereDate('order_date', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('order_date', '<=', $filters['end_date']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', trim((string) $filters['status']));
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'LIKE', "%{$search}%")
                    ->orWhere('supplier_invoice_number', 'LIKE', "%{$search}%");
            });
        }

        return $query;
    }

    public function getPurchaseHistory(Supplier $supplier, array $filters, User $user): LengthAwarePaginator
    {
        $query = $this->buildBasePoQuery($supplier, $filters, $user);
        $perPage = min((int) ($filters['per_page'] ?? 15), 100);

        return $query->with(['store', 'warehouse', 'items', 'returns'])
            ->orderBy('order_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    public function getPurchaseSummary(Supplier $supplier, array $filters, User $user): array
    {
        $poQuery = $this->buildBasePoQuery($supplier, $filters, $user);

        $totalPos = (clone $poQuery)->count();

        $receivedPos = (clone $poQuery)->whereIn('status', ['received', 'RECEIVED', 'completed', 'COMPLETED']);
        $totalSpend = (float) $receivedPos->sum('grand_total');

        $totalPaid = (float) (clone $poQuery)->sum('paid_amount');
        $totalDue = (float) (clone $poQuery)->sum('due_amount');

        $pendingPos = (clone $poQuery)->whereIn('status', ['pending', 'PENDING', 'ordered', 'ORDERED']);
        $pendingValuation = (float) $pendingPos->sum('grand_total');

        // Returns Query
        $requestedStoreId = isset($filters['store_id']) ? (int) $filters['store_id'] : null;
        $authorizedStoreIds = $this->getAuthorizedStoreIds($user, $requestedStoreId);

        $retQuery = PurchaseReturn::where('supplier_id', $supplier->id);
        if ($authorizedStoreIds !== null) {
            $retQuery->whereIn('store_id', $authorizedStoreIds);
        }
        if (! empty($filters['start_date'])) {
            $retQuery->whereDate('created_at', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $retQuery->whereDate('created_at', '<=', $filters['end_date']);
        }

        $totalReturnedValue = (float) $retQuery->sum('total_return_amount');
        $numberOfReturns = (int) $retQuery->count();

        // Status Breakdown
        $statusRows = (clone $poQuery)
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

        $firstPo = $totalPos > 0 ? (clone $poQuery)->orderBy('order_date', 'asc')->first() : null;
        $lastPo = $totalPos > 0 ? (clone $poQuery)->orderBy('order_date', 'desc')->first() : null;

        $firstOrderDate = $firstPo?->order_date ? $firstPo->order_date->toDateString() : null;
        $lastOrderDate = $lastPo?->order_date ? $lastPo->order_date->toDateString() : null;

        return [
            'supplier_id' => $supplier->id,
            'total_purchase_orders' => $totalPos,
            'total_spend' => round($totalSpend, 2),
            'total_paid_amount' => round($totalPaid, 2),
            'total_due_amount' => round($totalDue, 2),
            'total_returned_value' => round($totalReturnedValue, 2),
            'number_of_returns' => $numberOfReturns,
            'pending_po_valuation' => round($pendingValuation, 2),
            'po_status_breakdown' => $breakdown,
            'first_order_date' => $firstOrderDate,
            'last_order_date' => $lastOrderDate,
        ];
    }

    public function getPerformanceAnalytics(Supplier $supplier, array $filters, User $user): array
    {
        $summary = $this->getPurchaseSummary($supplier, $filters, $user);

        $poQuery = $this->buildBasePoQuery($supplier, $filters, $user);
        $validPoIds = (clone $poQuery)->pluck('id')->toArray();

        // 1. Fulfillment Rate
        $fulfillmentRate = 100.00;
        if (count($validPoIds) > 0) {
            $itemStats = PurchaseOrderItem::whereIn('purchase_order_id', $validPoIds)
                ->select(DB::raw('SUM(quantity_ordered) as total_ordered'), DB::raw('SUM(quantity_received) as total_received'))
                ->first();

            $ordered = $itemStats?->total_ordered ? (float) $itemStats->total_ordered : 0.0;
            $received = $itemStats?->total_received ? (float) $itemStats->total_received : 0.0;

            if ($ordered > 0) {
                $fulfillmentRate = round(min(100.0, ($received / $ordered) * 100.0), 2);
            }
        }

        // 2. Delivery Completion Rate
        $deliveryCompletionRate = 0.00;
        $totalNonCancelled = (clone $poQuery)->whereNotIn('status', ['cancelled', 'CANCELLED'])->count();
        if ($totalNonCancelled > 0) {
            $receivedCount = (clone $poQuery)->whereIn('status', ['received', 'RECEIVED', 'completed', 'COMPLETED'])->count();
            $deliveryCompletionRate = round(($receivedCount / $totalNonCancelled) * 100.0, 2);
        }

        // 3. Return Rate
        $returnRate = 0.00;
        if ($summary['total_spend'] > 0) {
            $returnRate = round(min(100.0, ($summary['total_returned_value'] / $summary['total_spend']) * 100.0), 2);
        }

        // 4. Average Lead Time
        $avgLeadTime = 0.00;
        $receivedPosWithDates = (clone $poQuery)
            ->whereIn('status', ['received', 'RECEIVED', 'completed', 'COMPLETED'])
            ->whereNotNull('received_date')
            ->whereNotNull('order_date')
            ->get();

        if ($receivedPosWithDates->count() > 0) {
            $totalDays = 0;
            foreach ($receivedPosWithDates as $po) {
                $orderTs = strtotime($po->order_date->toDateString());
                $receivedTs = strtotime($po->received_date->toDateString());
                $days = max(0, ($receivedTs - $orderTs) / 86400);
                $totalDays += $days;
            }
            $avgLeadTime = round($totalDays / $receivedPosWithDates->count(), 1);
        }

        // 5. Composite Supplier Score (0.0 to 100.0)
        // Score = (0.50 * Fulfillment Rate) + (0.30 * max(0, 100 - Return Rate * 5)) + (0.20 * max(0, 100 - Lead Time * 5))
        $returnPenaltyScore = max(0.0, 100.0 - ($returnRate * 5.0));
        $leadTimePenaltyScore = max(0.0, 100.0 - ($avgLeadTime * 5.0));

        $supplierScore = round(
            (0.50 * $fulfillmentRate) + (0.30 * $returnPenaltyScore) + (0.20 * $leadTimePenaltyScore),
            2
        );
        $supplierScore = max(0.0, min(100.0, $supplierScore));

        // 6. Top Supplied Products
        $topProducts = [];
        if (count($validPoIds) > 0) {
            $topRows = DB::table('purchase_order_items')
                ->join('product_variant_sizes', 'purchase_order_items.product_variant_size_id', '=', 'product_variant_sizes.id')
                ->join('product_variants', 'product_variant_sizes.product_variant_id', '=', 'product_variants.id')
                ->join('products', 'product_variants.product_id', '=', 'products.id')
                ->whereIn('purchase_order_items.purchase_order_id', $validPoIds)
                ->select(
                    'product_variant_sizes.sku',
                    'products.name as product_name',
                    DB::raw('SUM(purchase_order_items.quantity_received) as total_qty'),
                    DB::raw('SUM(purchase_order_items.total_cost) as total_spend')
                )
                ->groupBy('product_variant_sizes.id', 'product_variant_sizes.sku', 'products.name')
                ->orderBy('total_qty', 'desc')
                ->limit(5)
                ->get();

            $topProducts = $topRows->map(fn ($r) => [
                'sku' => $r->sku,
                'product_name' => $r->product_name,
                'total_received_quantity' => (int) $r->total_qty,
                'total_spend' => (float) $r->total_spend,
            ])->toArray();
        }

        // 7. Monthly Spend Trends (Supports MySQL & SQLite)
        $monthlyTrends = [];
        if (count($validPoIds) > 0) {
            $driver = DB::connection()->getDriverName();
            $dateFormatExpr = $driver === 'sqlite'
                ? "strftime('%Y-%m', order_date)"
                : "DATE_FORMAT(order_date, '%Y-%m')";

            $monthlyRows = (clone $poQuery)
                ->whereIn('status', ['received', 'RECEIVED', 'completed', 'COMPLETED'])
                ->select(
                    DB::raw("{$dateFormatExpr} as year_month"),
                    DB::raw('COUNT(id) as po_count'),
                    DB::raw('SUM(grand_total) as total_spend')
                )
                ->groupBy('year_month')
                ->orderBy('year_month', 'asc')
                ->get();

            $monthlyTrends = $monthlyRows->map(fn ($m) => [
                'year_month' => $m->year_month,
                'po_count' => (int) $m->po_count,
                'total_spend' => (float) $m->total_spend,
            ])->toArray();
        }

        return [
            'supplier_id' => $supplier->id,
            'fulfillment_rate' => $fulfillmentRate,
            'delivery_completion_rate' => $deliveryCompletionRate,
            'return_rate' => $returnRate,
            'average_lead_time_days' => $avgLeadTime,
            'supplier_score' => $supplierScore,
            'top_supplied_products' => $topProducts,
            'monthly_spend_trends' => $monthlyTrends,
        ];
    }

    public function getSupplierRankings(array $filters, User $user): LengthAwarePaginator
    {
        $suppliers = Supplier::whereNull('deleted_at')->get();

        $rankingsData = new Collection;

        foreach ($suppliers as $supplier) {
            $analytics = $this->getPerformanceAnalytics($supplier, $filters, $user);
            $summary = $this->getPurchaseSummary($supplier, $filters, $user);

            $rankingsData->push([
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'company_name' => $supplier->company_name,
                'total_spend' => $summary['total_spend'],
                'fulfillment_rate' => $analytics['fulfillment_rate'],
                'return_rate' => $analytics['return_rate'],
                'average_lead_time_days' => $analytics['average_lead_time_days'],
                'supplier_score' => $analytics['supplier_score'],
            ]);
        }

        $sorted = $rankingsData->sort(function ($a, $b) {
            if ($a['supplier_score'] == $b['supplier_score']) {
                return $b['total_spend'] <=> $a['total_spend'];
            }

            return $b['supplier_score'] <=> $a['supplier_score'];
        })->values();

        // Assign Rank index
        $ranked = $sorted->map(function ($item, $idx) {
            $item['rank'] = $idx + 1;

            return $item;
        });

        $perPage = min((int) ($filters['per_page'] ?? 15), 100);
        $page = (int) ($filters['page'] ?? 1);
        $slice = $ranked->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $slice,
            $ranked->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    public function getSupplierLedger(Supplier $supplier, array $filters, User $user): array
    {
        $openingBalance = (float) $supplier->opening_balance;
        $openingType = strtolower((string) $supplier->opening_balance_type) === 'advance' ? 'advance' : 'payable';
        $runningBalance = $openingType === 'advance' ? -abs($openingBalance) : abs($openingBalance);

        $entries = [];

        // 1. Opening Balance Entry
        $entries[] = [
            'id' => 'op-'.$supplier->id,
            'date' => $supplier->created_at ? $supplier->created_at->toDateString() : date('Y-m-d'),
            'type' => 'opening_balance',
            'particulars' => 'Opening Balance ('.ucfirst($openingType).')',
            'reference_number' => $supplier->code ?: 'SUP-'.str_pad((string) $supplier->id, 4, '0', STR_PAD_LEFT),
            'debit' => $openingType === 'payable' ? abs($openingBalance) : 0.00,
            'credit' => $openingType === 'advance' ? abs($openingBalance) : 0.00,
            'created_at' => $supplier->created_at ? $supplier->created_at->toIso8601String() : date('c'),
        ];

        // 2. Purchase Orders (Received/Ordered/Partial - exclude Cancelled & Draft)
        $pos = $supplier->purchaseOrders()
            ->whereNotIn('status', ['cancelled', 'CANCELLED', 'draft', 'DRAFT'])
            ->get();

        foreach ($pos as $po) {
            $entries[] = [
                'id' => 'po-'.$po->id,
                'date' => $po->order_date ? $po->order_date->toDateString() : $po->created_at->toDateString(),
                'type' => 'purchase_order',
                'particulars' => 'Purchase Bill #'.$po->po_number.($po->supplier_invoice_number ? ' (Inv Ref: '.$po->supplier_invoice_number.')' : ''),
                'reference_number' => $po->po_number,
                'debit' => (float) $po->grand_total, // Payable Increase
                'credit' => 0.00,
                'created_at' => $po->created_at ? $po->created_at->toIso8601String() : date('c'),
            ];
        }

        // 3. Supplier Payments
        $payments = $supplier->payments()->get();
        foreach ($payments as $pay) {
            $entries[] = [
                'id' => 'pay-'.$pay->id,
                'date' => $pay->payment_date ? $pay->payment_date->toDateString() : $pay->created_at->toDateString(),
                'type' => 'payment',
                'particulars' => 'Payment #'.$pay->payment_number.' ('.strtoupper($pay->payment_method).')',
                'reference_number' => $pay->payment_number,
                'debit' => 0.00,
                'credit' => (float) $pay->amount, // Payable Decrease
                'created_at' => $pay->created_at ? $pay->created_at->toIso8601String() : date('c'),
            ];
        }

        // 4. Purchase Returns
        $returns = $supplier->purchaseReturns()->get();
        foreach ($returns as $ret) {
            $entries[] = [
                'id' => 'ret-'.$ret->id,
                'date' => $ret->created_at ? $ret->created_at->toDateString() : date('Y-m-d'),
                'type' => 'purchase_return',
                'particulars' => 'Purchase Return #'.$ret->return_number,
                'reference_number' => $ret->return_number,
                'debit' => 0.00,
                'credit' => (float) $ret->total_return_amount, // Payable Decrease
                'created_at' => $ret->created_at ? $ret->created_at->toIso8601String() : date('c'),
            ];
        }

        // Sort entries chronologically by date and creation timestamp
        usort($entries, function ($a, $b) {
            $dateCmp = strcmp($a['date'], $b['date']);
            if ($dateCmp !== 0) return $dateCmp;
            return strcmp($a['created_at'], $b['created_at']);
        });

        // Compute running balance
        $currentBalance = 0.00;
        $processedEntries = [];

        foreach ($entries as $entry) {
            $currentBalance += ($entry['debit'] - $entry['credit']);
            $entry['balance'] = round($currentBalance, 2);
            $entry['balance_type'] = $entry['balance'] >= 0 ? 'Payable (Due)' : 'Advance (Credit)';
            $processedEntries[] = $entry;
        }

        return [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'company_name' => $supplier->company_name,
            'opening_balance' => $openingBalance,
            'opening_balance_type' => $openingType,
            'closing_balance' => round(max(0, $currentBalance), 2),
            'closing_balance_type' => $currentBalance >= 0 ? 'Payable' : 'Advance',
            'entries' => $processedEntries,
        ];
    }
}

