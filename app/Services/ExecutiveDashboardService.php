<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PosRegister;
use App\Models\PosRegisterCashMovement;
use App\Models\PosSession;
use App\Models\ProductVariantSize;
use App\Models\ReturnItem;
use App\Models\ReturnSale;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExecutiveDashboardService
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

    public function resolveDateRange(array $filters): array
    {
        $preset = $filters['period'] ?? 'current_month';
        $now = Carbon::now();

        switch ($preset) {
            case 'today':
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                $prevStart = $start->copy()->subDay();
                $prevEnd = $end->copy()->subDay();
                break;
            case 'yesterday':
                $start = $now->copy()->subDay()->startOfDay();
                $end = $now->copy()->subDay()->endOfDay();
                $prevStart = $start->copy()->subDay();
                $prevEnd = $end->copy()->subDay();
                break;
            case 'current_week':
                $start = $now->copy()->startOfWeek();
                $end = $now->copy()->endOfWeek();
                $prevStart = $start->copy()->subWeek();
                $prevEnd = $end->copy()->subWeek();
                break;
            case 'previous_month':
                $start = $now->copy()->subMonth()->startOfMonth();
                $end = $now->copy()->subMonth()->endOfMonth();
                $prevStart = $start->copy()->subMonth()->startOfMonth();
                $prevEnd = $start->copy()->subMonth()->endOfMonth();
                break;
            case 'current_year':
                $start = $now->copy()->startOfYear();
                $end = $now->copy()->endOfYear();
                $prevStart = $start->copy()->subYear();
                $prevEnd = $end->copy()->subYear();
                break;
            case 'custom':
                $start = ! empty($filters['start_date']) ? Carbon::parse($filters['start_date'])->startOfDay() : $now->copy()->startOfMonth();
                $end = ! empty($filters['end_date']) ? Carbon::parse($filters['end_date'])->endOfDay() : $now->copy()->endOfDay();
                $diffDays = max(1, $start->diffInDays($end));
                $prevStart = $start->copy()->subDays($diffDays);
                $prevEnd = $start->copy()->subSecond();
                break;
            case 'current_month':
            default:
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                $prevStart = $start->copy()->subMonth()->startOfMonth();
                $prevEnd = $start->copy()->subMonth()->endOfMonth();
                break;
        }

        return [
            'start' => $start,
            'end' => $end,
            'prev_start' => $prevStart,
            'prev_end' => $prevEnd,
        ];
    }

    public function buildBaseInvoiceQuery(array $filters, User $user, Carbon $start, Carbon $end)
    {
        $requestedStoreId = isset($filters['store_id']) ? (int) $filters['store_id'] : null;
        $authorizedStoreIds = $this->getAuthorizedStoreIds($user, $requestedStoreId);

        $query = Invoice::where('status', 'completed')
            ->whereBetween('created_at', [$start, $end]);

        if ($authorizedStoreIds !== null) {
            $query->whereIn('store_id', $authorizedStoreIds);
        }

        return $query;
    }

    public function buildBaseReturnQuery(array $filters, User $user, Carbon $start, Carbon $end)
    {
        $requestedStoreId = isset($filters['store_id']) ? (int) $filters['store_id'] : null;
        $authorizedStoreIds = $this->getAuthorizedStoreIds($user, $requestedStoreId);

        $query = ReturnSale::whereBetween('created_at', [$start, $end]);

        if ($authorizedStoreIds !== null) {
            $query->whereIn('store_id', $authorizedStoreIds);
        }

        return $query;
    }

    public function calculateFinancialsForRange(array $filters, User $user, Carbon $start, Carbon $end): array
    {
        $invQuery = $this->buildBaseInvoiceQuery($filters, $user, $start, $end);
        $retQuery = $this->buildBaseReturnQuery($filters, $user, $start, $end);

        $grossSales = (float) (clone $invQuery)->sum('subtotal');
        $discounts = (float) (clone $invQuery)->sum('discount_amount');
        $taxableSales = (float) (clone $invQuery)->sum('taxable_amount');
        $gstTax = (float) (clone $invQuery)->sum('total_tax');
        $grossBilledRevenue = (float) (clone $invQuery)->sum('grand_total');
        $totalCollections = (float) (clone $invQuery)->sum('paid_amount');
        $outstanding = (float) (clone $invQuery)->sum(DB::raw('COALESCE(grand_total, 0) - COALESCE(paid_amount, 0)'));
        $completedSalesCount = (int) (clone $invQuery)->count();

        $returnsRefunds = (float) (clone $retQuery)->sum('total_refund_amount');
        $returnedCount = (int) (clone $retQuery)->count();

        $netBilledRevenue = max(0.0, round($grossBilledRevenue - $returnsRefunds, 2));

        // Calculate COGS
        $validInvoiceIds = (clone $invQuery)->pluck('id')->toArray();
        $invoiceCogs = 0.00;
        if (count($validInvoiceIds) > 0) {
            $cogsRow = DB::table('invoice_items')
                ->whereIn('invoice_id', $validInvoiceIds)
                ->select(DB::raw('SUM(quantity * cost_price) as cogs'))
                ->first();
            $invoiceCogs = (float) ($cogsRow?->cogs ?? 0.00);
        }

        $validReturnIds = (clone $retQuery)->pluck('id')->toArray();
        $returnedCogs = 0.00;
        if (count($validReturnIds) > 0) {
            $retCogsRow = DB::table('return_items')
                ->join('invoice_items', 'return_items.invoice_item_id', '=', 'invoice_items.id')
                ->whereIn('return_items.return_id', $validReturnIds)
                ->select(DB::raw('SUM(return_items.quantity * invoice_items.cost_price) as ret_cogs'))
                ->first();
            $returnedCogs = (float) ($retCogsRow?->ret_cogs ?? 0.00);
        }

        $cogs = max(0.0, round($invoiceCogs - $returnedCogs, 2));

        // Calculate Net Taxable Revenue (excl GST)
        $returnedTaxable = round($returnsRefunds / 1.12, 2); // Approximate tax-exclusive return value
        $netTaxableRevenue = max(0.0, round($taxableSales - $returnedTaxable, 2));

        // Gross Profit = Net Taxable Revenue - COGS (GST is explicitly EXCLUDED!)
        $grossProfit = round($netTaxableRevenue - $cogs, 2);
        $grossMarginPct = $netTaxableRevenue > 0 ? round(($grossProfit / $netTaxableRevenue) * 100.0, 2) : 0.00;

        // Expenses Query
        $requestedStoreId = isset($filters['store_id']) ? (int) $filters['store_id'] : null;
        $authorizedStoreIds = $this->getAuthorizedStoreIds($user, $requestedStoreId);

        $expQuery = Expense::whereBetween('expense_date', [$start->toDateString(), $end->toDateString()]);
        if ($authorizedStoreIds !== null) {
            $expQuery->whereIn('store_id', $authorizedStoreIds);
        }
        $totalExpenses = (float) $expQuery->sum('amount');

        $netStoreProfit = round($grossProfit - $totalExpenses, 2);

        return [
            'gross_sales' => round($grossSales, 2),
            'discounts' => round($discounts, 2),
            'taxable_sales' => round($taxableSales, 2),
            'gst_tax' => round($gstTax, 2),
            'gross_billed_sales_revenue' => round($grossBilledRevenue, 2),
            'returns_refunds' => round($returnsRefunds, 2),
            'net_billed_sales_revenue' => round($netBilledRevenue, 2),
            'net_taxable_revenue' => round($netTaxableRevenue, 2),
            'total_collections' => round($totalCollections, 2),
            'outstanding_amount' => round($outstanding, 2),
            'cogs' => round($cogs, 2),
            'gross_profit' => round($grossProfit, 2),
            'gross_margin_percentage' => $grossMarginPct,
            'total_expenses' => round($totalExpenses, 2),
            'net_store_profit' => round($netStoreProfit, 2),
            'completed_sales_count' => $completedSalesCount,
            'returned_transactions_count' => $returnedCount,
        ];
    }

    public function getExecutiveKpiSummary(array $filters, User $user): array
    {
        $dates = $this->resolveDateRange($filters);
        $curr = $this->calculateFinancialsForRange($filters, $user, $dates['start'], $dates['end']);
        $prev = $this->calculateFinancialsForRange($filters, $user, $dates['prev_start'], $dates['prev_end']);

        $growth = fn ($c, $p) => $p > 0 ? round((($c - $p) / $p) * 100.0, 2) : ($c > 0 ? 100.00 : 0.00);

        $activeCustomersCount = Customer::count();

        $curr['active_customers_count'] = $activeCustomersCount;
        $curr['period_comparison'] = [
            'net_revenue_growth_pct' => $growth($curr['net_billed_sales_revenue'], $prev['net_billed_sales_revenue']),
            'gross_profit_growth_pct' => $growth($curr['gross_profit'], $prev['gross_profit']),
            'sales_count_growth_pct' => $growth($curr['completed_sales_count'], $prev['completed_sales_count']),
        ];

        return $curr;
    }

    public function getSalesTrends(array $filters, User $user): Collection
    {
        $dates = $this->resolveDateRange($filters);
        $invQuery = $this->buildBaseInvoiceQuery($filters, $user, $dates['start'], $dates['end']);
        $retQuery = $this->buildBaseReturnQuery($filters, $user, $dates['start'], $dates['end']);

        $driver = DB::connection()->getDriverName();
        $dateFormatExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m-%d', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m-%d')";

        $salesRows = (clone $invQuery)
            ->select(
                DB::raw("{$dateFormatExpr} as dt"),
                DB::raw('SUM(grand_total) as gross_rev'),
                DB::raw('COUNT(id) as orders_cnt')
            )
            ->groupBy('dt')
            ->orderBy('dt', 'asc')
            ->get()
            ->keyBy('dt');

        $returnRows = (clone $retQuery)
            ->select(
                DB::raw("{$dateFormatExpr} as dt"),
                DB::raw('SUM(total_refund_amount) as ref_amt'),
                DB::raw('COUNT(id) as ret_cnt')
            )
            ->groupBy('dt')
            ->get()
            ->keyBy('dt');

        $allDates = $salesRows->keys()->merge($returnRows->keys())->unique()->sort();

        return $allDates->map(function ($dt) use ($salesRows, $returnRows) {
            $s = $salesRows->get($dt);
            $r = $returnRows->get($dt);

            $grossRev = (float) ($s?->gross_rev ?? 0.00);
            $refAmt = (float) ($r?->ref_amt ?? 0.00);
            $netRev = max(0.0, round($grossRev - $refAmt, 2));

            return [
                'period' => $dt,
                'gross_revenue' => round($grossRev, 2),
                'net_revenue' => round($netRev, 2),
                'completed_orders' => (int) ($s?->orders_cnt ?? 0),
                'returns_count' => (int) ($r?->ret_cnt ?? 0),
                'refund_amount' => round($refAmt, 2),
            ];
        })->values();
    }

    public function getStorePerformanceRankings(array $filters, User $user): Collection
    {
        $requestedStoreId = isset($filters['store_id']) ? (int) $filters['store_id'] : null;
        $authorizedStoreIds = $this->getAuthorizedStoreIds($user, $requestedStoreId);

        $stores = Store::where('is_active', true)
            ->when($authorizedStoreIds !== null, fn ($q) => $q->whereIn('id', $authorizedStoreIds))
            ->get();

        $dates = $this->resolveDateRange($filters);
        $rankings = new Collection;

        foreach ($stores as $st) {
            $stFilters = array_merge($filters, ['store_id' => $st->id]);
            $fin = $this->calculateFinancialsForRange($stFilters, $user, $dates['start'], $dates['end']);

            // Inventory Valuation
            $invValRow = DB::table('inventory_stocks')
                ->join('product_variant_sizes', 'inventory_stocks.product_variant_size_id', '=', 'product_variant_sizes.id')
                ->where('inventory_stocks.store_id', $st->id)
                ->select(DB::raw('SUM(inventory_stocks.stock_quantity * product_variant_sizes.cost_price) as val'))
                ->first();

            $invValuation = round((float) ($invValRow?->val ?? 0.00), 2);

            $rankings->push([
                'store_id' => $st->id,
                'store_code' => $st->code,
                'store_name' => $st->name,
                'gross_sales' => $fin['gross_sales'],
                'net_sales' => $fin['net_billed_sales_revenue'],
                'cogs' => $fin['cogs'],
                'gross_profit' => $fin['gross_profit'],
                'gross_margin_percentage' => $fin['gross_margin_percentage'],
                'completed_orders' => $fin['completed_sales_count'],
                'returns_count' => $fin['returned_transactions_count'],
                'total_collections' => $fin['total_collections'],
                'total_expenses' => $fin['total_expenses'],
                'net_store_profit' => $fin['net_store_profit'],
                'inventory_valuation' => $invValuation,
            ]);
        }

        $sorted = $rankings->sort(function ($a, $b) {
            if ($a['net_sales'] == $b['net_sales']) {
                return $b['gross_profit'] <=> $a['gross_profit'];
            }

            return $b['net_sales'] <=> $a['net_sales'];
        })->values();

        return $sorted->map(function ($item, $idx) {
            $item['rank'] = $idx + 1;

            return $item;
        });
    }

    public function getProductPerformanceRankings(array $filters, User $user): array
    {
        $dates = $this->resolveDateRange($filters);
        $invQuery = $this->buildBaseInvoiceQuery($filters, $user, $dates['start'], $dates['end']);
        $validInvoiceIds = (clone $invQuery)->pluck('id')->toArray();

        if (count($validInvoiceIds) === 0) {
            return [
                'top_products' => [],
                'top_skus' => [],
                'top_categories' => [],
                'top_brands' => [],
            ];
        }

        // Top SKUs
        $topSkus = DB::table('invoice_items')
            ->whereIn('invoice_id', $validInvoiceIds)
            ->select(
                'sku_snapshot as sku',
                'product_name_snapshot as product_name',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(subtotal) as net_revenue')
            )
            ->groupBy('sku_snapshot', 'product_name_snapshot')
            ->orderBy('total_qty', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'sku' => $r->sku,
                'product_name' => $r->product_name,
                'quantity_sold' => (int) $r->total_qty,
                'net_revenue' => (float) $r->net_revenue,
            ])->toArray();

        // Top Products
        $topProducts = DB::table('invoice_items')
            ->whereIn('invoice_id', $validInvoiceIds)
            ->select(
                'article_number_snapshot as article_number',
                'product_name_snapshot as product_name',
                DB::raw('SUM(quantity) as total_qty'),
                DB::raw('SUM(subtotal) as net_revenue')
            )
            ->groupBy('article_number_snapshot', 'product_name_snapshot')
            ->orderBy('net_revenue', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'article_number' => $r->article_number,
                'product_name' => $r->product_name,
                'quantity_sold' => (int) $r->total_qty,
                'net_revenue' => (float) $r->net_revenue,
            ])->toArray();

        // Top Categories
        $topCategories = DB::table('invoice_items')
            ->join('product_variant_sizes', 'invoice_items.product_variant_size_id', '=', 'product_variant_sizes.id')
            ->join('product_variants', 'product_variant_sizes.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->whereIn('invoice_items.invoice_id', $validInvoiceIds)
            ->select(
                'categories.name as category_name',
                DB::raw('SUM(invoice_items.quantity) as total_qty'),
                DB::raw('SUM(invoice_items.subtotal) as net_revenue')
            )
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('net_revenue', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'category_name' => $r->category_name,
                'quantity_sold' => (int) $r->total_qty,
                'net_revenue' => (float) $r->net_revenue,
            ])->toArray();

        // Top Brands
        $topBrands = DB::table('invoice_items')
            ->join('product_variant_sizes', 'invoice_items.product_variant_size_id', '=', 'product_variant_sizes.id')
            ->join('product_variants', 'product_variant_sizes.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->join('brands', 'products.brand_id', '=', 'brands.id')
            ->whereIn('invoice_items.invoice_id', $validInvoiceIds)
            ->select(
                'brands.name as brand_name',
                DB::raw('SUM(invoice_items.quantity) as total_qty'),
                DB::raw('SUM(invoice_items.subtotal) as net_revenue')
            )
            ->groupBy('brands.id', 'brands.name')
            ->orderBy('net_revenue', 'desc')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'brand_name' => $r->brand_name,
                'quantity_sold' => (int) $r->total_qty,
                'net_revenue' => (float) $r->net_revenue,
            ])->toArray();

        return [
            'top_products' => $topProducts,
            'top_skus' => $topSkus,
            'top_categories' => $topCategories,
            'top_brands' => $topBrands,
        ];
    }

    public function getInventoryKpis(array $filters, User $user): array
    {
        $requestedStoreId = isset($filters['store_id']) ? (int) $filters['store_id'] : null;
        $authorizedStoreIds = $this->getAuthorizedStoreIds($user, $requestedStoreId);

        $stockQuery = InventoryStock::query();
        if ($authorizedStoreIds !== null) {
            $stockQuery->whereIn('store_id', $authorizedStoreIds);
        }

        $totalUnits = (int) (clone $stockQuery)->sum('stock_quantity');

        // Valuations
        $costRow = DB::table('inventory_stocks')
            ->join('product_variant_sizes', 'inventory_stocks.product_variant_size_id', '=', 'product_variant_sizes.id')
            ->when($authorizedStoreIds !== null, fn ($q) => $q->whereIn('inventory_stocks.store_id', $authorizedStoreIds))
            ->select(
                DB::raw('SUM(inventory_stocks.stock_quantity * product_variant_sizes.cost_price) as val_cost'),
                DB::raw('SUM(inventory_stocks.stock_quantity * product_variant_sizes.selling_price) as val_sell')
            )
            ->first();

        $valCost = round((float) ($costRow?->val_cost ?? 0.00), 2);
        $valSell = round((float) ($costRow?->val_sell ?? 0.00), 2);

        $lowStockCount = (clone $stockQuery)->where('stock_quantity', '>', 0)->whereColumn('stock_quantity', '<=', 'reorder_level')->count();
        $outOfStockCount = (clone $stockQuery)->where('stock_quantity', '<=', 0)->count();
        $overstockCount = (clone $stockQuery)->where('reorder_level', '>', 0)->whereRaw('stock_quantity >= 3 * reorder_level')->count();

        // Turnover Ratio
        $dates = $this->resolveDateRange($filters);
        $fin = $this->calculateFinancialsForRange($filters, $user, $dates['start'], $dates['end']);
        $cogs = $fin['cogs'];

        $turnoverRatio = $valCost > 0 ? round($cogs / $valCost, 2) : 0.00;

        return [
            'total_inventory_units' => $totalUnits,
            'inventory_valuation_cost' => $valCost,
            'inventory_valuation_selling' => $valSell,
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
            'overstock_count' => $overstockCount,
            'inventory_turnover_ratio' => $turnoverRatio,
        ];
    }

    public function getPosRegisterKpis(array $filters, User $user): array
    {
        $requestedStoreId = isset($filters['store_id']) ? (int) $filters['store_id'] : null;
        $authorizedStoreIds = $this->getAuthorizedStoreIds($user, $requestedStoreId);

        $sessQuery = PosSession::where('status', 'open');
        $regQuery = PosRegister::where('is_active', true);

        if ($authorizedStoreIds !== null) {
            $sessQuery->whereIn('store_id', $authorizedStoreIds);
            $regQuery->whereIn('store_id', $authorizedStoreIds);
        }

        $activeSessionsCount = $sessQuery->count();
        $openRegistersCount = $regQuery->whereHas('sessions', fn ($q) => $q->where('status', 'open'))->count();

        // Drawer Cash calculation across open sessions
        $openSessionIds = $sessQuery->pluck('id')->toArray();
        $expectedCash = 0.00;

        if (count($openSessionIds) > 0) {
            $openingCashSum = (float) (clone $sessQuery)->sum('opening_cash');

            $cashPaymentsSum = (float) DB::table('invoice_payments')
                ->join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id')
                ->whereIn('invoices.pos_session_id', $openSessionIds)
                ->where('invoice_payments.payment_method', 'cash')
                ->where('invoices.status', 'completed')
                ->sum('invoice_payments.amount');

            $cashMovementsSum = (float) DB::table('pos_register_cash_movements')
                ->whereIn('pos_session_id', $openSessionIds)
                ->select(DB::raw("SUM(CASE WHEN movement_type = 'cash_in' THEN amount WHEN movement_type IN ('cash_out', 'drawer_drop') THEN -amount ELSE 0 END) as net_mv"))
                ->value('net_mv');

            $expectedCash = round($openingCashSum + $cashPaymentsSum + $cashMovementsSum, 2);
        }

        // Cash movements summary
        $dates = $this->resolveDateRange($filters);
        $mvQuery = PosRegisterCashMovement::whereBetween('created_at', [$dates['start'], $dates['end']]);
        if ($authorizedStoreIds !== null) {
            $mvQuery->whereHas('posRegister', fn ($q) => $q->whereIn('store_id', $authorizedStoreIds));
        }

        $totalCashIn = (float) (clone $mvQuery)->where('movement_type', 'cash_in')->sum('amount');
        $totalCashOut = (float) (clone $mvQuery)->where('movement_type', 'cash_out')->sum('amount');
        $totalDrawerDrop = (float) (clone $mvQuery)->where('movement_type', 'drawer_drop')->sum('amount');

        // Total Reconciliation Difference (closed sessions)
        $diffQuery = PosSession::where('status', 'closed')
            ->whereBetween('closed_at', [$dates['start'], $dates['end']]);
        if ($authorizedStoreIds !== null) {
            $diffQuery->whereIn('store_id', $authorizedStoreIds);
        }

        $totalDiff = (float) $diffQuery->sum('cash_difference');

        return [
            'active_pos_sessions' => $activeSessionsCount,
            'open_registers' => $openRegistersCount,
            'expected_drawer_cash' => round($expectedCash, 2),
            'total_cash_in' => round($totalCashIn, 2),
            'total_cash_out' => round($totalCashOut, 2),
            'total_drawer_drop' => round($totalDrawerDrop, 2),
            'total_reconciliation_difference' => round($totalDiff, 2),
        ];
    }

    public function getCustomerKpis(array $filters, User $user): array
    {
        $dates = $this->resolveDateRange($filters);
        $newCustomersCount = Customer::whereBetween('created_at', [$dates['start'], $dates['end']])->count();

        $invQuery = $this->buildBaseInvoiceQuery($filters, $user, $dates['start'], $dates['end']);
        $purchasingCustomers = (clone $invQuery)->whereNotNull('customer_id')->pluck('customer_id');

        $activePurchasingCustomers = $purchasingCustomers->unique()->count();

        $customerOrderCounts = $purchasingCustomers->countBy();
        $repeatCustomersCount = $customerOrderCounts->filter(fn ($cnt) => $cnt >= 2)->count();

        $repeatRate = $activePurchasingCustomers > 0
            ? round(($repeatCustomersCount / $activePurchasingCustomers) * 100.0, 2)
            : 0.00;

        $completedOrdersCount = (clone $invQuery)->count();
        $netBilledRevenue = (float) (clone $invQuery)->sum('grand_total');

        $aov = $completedOrdersCount > 0 ? round($netBilledRevenue / $completedOrdersCount, 2) : 0.00;

        $retQuery = $this->buildBaseReturnQuery($filters, $user, $dates['start'], $dates['end']);
        $returnsCount = (clone $retQuery)->count();

        $customerReturnRate = $completedOrdersCount > 0 ? round(($returnsCount / $completedOrdersCount) * 100.0, 2) : 0.00;

        return [
            'new_customers_count' => $newCustomersCount,
            'active_purchasing_customers' => $activePurchasingCustomers,
            'repeat_customer_rate' => $repeatRate,
            'average_order_value' => $aov,
            'customer_return_rate' => $customerReturnRate,
        ];
    }
}
