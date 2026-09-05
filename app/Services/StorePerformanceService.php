<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\ReturnSale;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StorePerformanceService
{
    public function resolveDateRange(array $filters): array
    {
        $preset = strtolower(trim((string) ($filters['period'] ?? 'this_month')));
        $now = Carbon::now();

        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $start = ! empty($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : $now->copy()->startOfMonth();
            $end = ! empty($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : $now->copy()->endOfDay();

            return [
                'preset' => 'custom',
                'start' => $start,
                'end' => $end,
            ];
        }

        switch ($preset) {
            case 'today':
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                break;
            case 'yesterday':
                $start = $now->copy()->subDay()->startOfDay();
                $end = $now->copy()->subDay()->endOfDay();
                break;
            case 'this_week':
            case 'current_week':
                $start = $now->copy()->startOfWeek();
                $end = $now->copy()->endOfWeek();
                break;
            case 'this_year':
            case 'current_year':
                $start = $now->copy()->startOfYear();
                $end = $now->copy()->endOfYear();
                break;
            case 'this_month':
            case 'current_month':
            default:
                $preset = 'this_month';
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                break;
        }

        return [
            'preset' => $preset,
            'start' => $start,
            'end' => $end,
        ];
    }

    public function getPerformance(Store $store, array $filters, User $user): array
    {
        $dateInfo = $this->resolveDateRange($filters);
        $start = $dateInfo['start'];
        $end = $dateInfo['end'];
        $groupBy = strtolower(trim((string) ($filters['group_by'] ?? 'day')));
        if (! in_array($groupBy, ['day', 'week', 'month'])) {
            $groupBy = 'day';
        }

        // 1. Invoices & Sales Financials
        $invQuery = Invoice::where('store_id', $store->id)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$start, $end]);

        $grossSales = (float) (clone $invQuery)->sum('subtotal');
        $discounts = (float) (clone $invQuery)->sum('discount_amount');
        $grossBilledRevenue = (float) (clone $invQuery)->sum('grand_total');
        $completedSalesCount = (int) (clone $invQuery)->count();

        // 2. Returns Query
        $retQuery = ReturnSale::where('store_id', $store->id)
            ->whereBetween('created_at', [$start, $end]);

        $returnsCount = (int) (clone $retQuery)->count();
        $returnsRefunds = (float) (clone $retQuery)->sum('total_refund_amount');

        // Net Sales = Gross Billed Revenue - Returns Refunds
        $netSales = max(0.0, round($grossBilledRevenue - $returnsRefunds, 2));

        // Total Items Sold & COGS
        $validInvoiceIds = (clone $invQuery)->pluck('id')->toArray();
        $totalItemsSold = 0;
        $invoiceCogs = 0.00;
        if (count($validInvoiceIds) > 0) {
            $itemsRow = DB::table('invoice_items')
                ->whereIn('invoice_id', $validInvoiceIds)
                ->select(
                    DB::raw('SUM(quantity) as total_qty'),
                    DB::raw('SUM(quantity * cost_price) as total_cogs')
                )->first();
            $totalItemsSold = (int) ($itemsRow?->total_qty ?? 0);
            $invoiceCogs = (float) ($itemsRow?->total_cogs ?? 0.00);
        }

        // Returned COGS
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
        $grossProfit = round($netSales - $cogs, 2);
        $grossMarginPct = $netSales > 0 ? round(($grossProfit / $netSales) * 100.0, 2) : 0.00;

        // Operating Expenses
        $operatingExpenses = (float) Expense::where('store_id', $store->id)
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->sum('amount');

        $netProfit = round($grossProfit - $operatingExpenses, 2);
        $avgOrderValue = $completedSalesCount > 0 ? round($netSales / $completedSalesCount, 2) : 0.00;
        $returnRatePct = $completedSalesCount > 0 ? round(($returnsCount / $completedSalesCount) * 100.0, 2) : 0.00;

        // 3. Sales Trend Grouping
        $salesTrend = $this->calculateSalesTrend($store->id, $start, $end, $groupBy);

        // 4. Top Selling Products (Top 10)
        $topProducts = $this->calculateTopProducts($store->id, $validInvoiceIds);

        // 5. Inventory Performance
        $inventoryMetrics = $this->calculateInventoryMetrics($store->id);

        // 6. Payment Performance
        $paymentMetrics = $this->calculatePaymentMetrics($store->id, $validInvoiceIds, $grossBilledRevenue);

        // 7. Returns & Exchanges Metrics
        $exchangesCount = 0;
        if (count($validReturnIds) > 0) {
            $exchangesCount = (int) ReturnSale::whereIn('id', $validReturnIds)
                ->where(function ($q) {
                    $q->where('refund_mode', 'exchange_offset')
                      ->orWhere('reason', 'LIKE', '%Exchange%');
                })->count();
        }

        // 8. Customer Performance Metrics
        $customerMetrics = $this->calculateCustomerMetrics($store->id, $start, $end, $netSales, $validInvoiceIds);

        // 9. Recent Activity Stream
        $recentActivity = $this->calculateRecentActivity($store->id);

        return [
            'store' => [
                'id' => $store->id,
                'code' => $store->code,
                'name' => $store->name,
                'city' => $store->city,
                'address' => $store->address,
            ],
            'period' => [
                'preset' => $dateInfo['preset'],
                'date_from' => $start->toDateString(),
                'date_to' => $end->toDateString(),
                'group_by' => $groupBy,
            ],
            'kpis' => [
                'total_sales' => round($grossBilledRevenue, 2),
                'gross_sales' => round($grossSales, 2),
                'discounts' => round($discounts, 2),
                'returns' => round($returnsRefunds, 2),
                'net_sales' => round($netSales, 2),
                'total_orders' => $completedSalesCount,
                'items_sold' => $totalItemsSold,
                'avg_order_value' => $avgOrderValue,
                'cogs' => round($cogs, 2),
                'gross_profit' => round($grossProfit, 2),
                'gross_margin_percentage' => $grossMarginPct,
                'operating_expenses' => round($operatingExpenses, 2),
                'net_profit' => round($netProfit, 2),
                'returns_count' => $returnsCount,
                'returns_amount' => round($returnsRefunds, 2),
                'return_rate_percentage' => $returnRatePct,
            ],
            'sales_trend' => $salesTrend,
            'profit_breakdown' => [
                'gross_sales' => round($grossSales, 2),
                'discounts' => round($discounts, 2),
                'net_sales' => round($netSales, 2),
                'cogs' => round($cogs, 2),
                'gross_profit' => round($grossProfit, 2),
                'gross_margin_pct' => $grossMarginPct,
                'operating_expenses' => round($operatingExpenses, 2),
                'net_profit' => round($netProfit, 2),
            ],
            'top_products' => $topProducts,
            'inventory' => $inventoryMetrics,
            'payments' => $paymentMetrics,
            'returns_exchanges' => [
                'returns_count' => $returnsCount,
                'returns_amount' => round($returnsRefunds, 2),
                'exchanges_count' => $exchangesCount,
                'return_rate_pct' => $returnRatePct,
            ],
            'customer_performance' => $customerMetrics,
            'recent_activity' => $recentActivity,
        ];
    }

    protected function calculateSalesTrend(int $storeId, Carbon $start, Carbon $end, string $groupBy): array
    {
        $invoices = Invoice::where('store_id', $storeId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $returns = ReturnSale::where('store_id', $storeId)
            ->whereBetween('created_at', [$start, $end])
            ->get();

        $grouped = [];

        foreach ($invoices as $inv) {
            $dt = Carbon::parse($inv->created_at);
            if ($groupBy === 'month') {
                $key = $dt->format('Y-m');
            } elseif ($groupBy === 'week') {
                $key = $dt->format('o-W');
            } else {
                $key = $dt->format('Y-m-d');
            }

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'period' => $key,
                    'gross_sales' => 0.0,
                    'net_sales' => 0.0,
                    'completed_orders' => 0,
                    'returns_amount' => 0.0,
                ];
            }

            $grouped[$key]['gross_sales'] += (float) $inv->grand_total;
            $grouped[$key]['net_sales'] += (float) $inv->grand_total;
            $grouped[$key]['completed_orders'] += 1;
        }

        foreach ($returns as $ret) {
            $dt = Carbon::parse($ret->created_at);
            if ($groupBy === 'month') {
                $key = $dt->format('Y-m');
            } elseif ($groupBy === 'week') {
                $key = $dt->format('o-W');
            } else {
                $key = $dt->format('Y-m-d');
            }

            if (isset($grouped[$key])) {
                $grouped[$key]['returns_amount'] += (float) $ret->total_refund_amount;
                $grouped[$key]['net_sales'] = max(0.0, round($grouped[$key]['net_sales'] - (float) $ret->total_refund_amount, 2));
            }
        }

        ksort($grouped);

        return array_values($grouped);
    }

    protected function calculateTopProducts(int $storeId, array $invoiceIds): array
    {
        if (count($invoiceIds) === 0) {
            return [];
        }

        $rows = DB::table('invoice_items')
            ->join('product_variant_sizes', 'invoice_items.product_variant_size_id', '=', 'product_variant_sizes.id')
            ->join('product_variants', 'product_variant_sizes.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->whereIn('invoice_items.invoice_id', $invoiceIds)
            ->select(
                'products.id as product_id',
                'products.name as product_name',
                'products.article_number',
                DB::raw('SUM(invoice_items.quantity) as total_qty'),
                DB::raw('SUM(invoice_items.subtotal) as total_sales'),
                DB::raw('SUM(invoice_items.quantity * invoice_items.cost_price) as total_cogs')
            )
            ->groupBy('products.id', 'products.name', 'products.article_number')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        $topList = [];
        foreach ($rows as $row) {
            $sales = (float) $row->total_sales;
            $cogs = (float) $row->total_cogs;
            $profit = round($sales - $cogs, 2);

            $topList[] = [
                'product_id' => $row->product_id,
                'name' => $row->product_name,
                'article_number' => $row->article_number ?? 'N/A',
                'quantity_sold' => (int) $row->total_qty,
                'sales_amount' => round($sales, 2),
                'gross_profit' => $profit,
            ];
        }

        return $topList;
    }

    protected function calculateInventoryMetrics(int $storeId): array
    {
        $stocks = InventoryStock::with('variantSize')
            ->where('store_id', $storeId)
            ->get();

        $totalUnits = 0;
        $costVal = 0.0;
        $sellingVal = 0.0;
        $inStock = 0;
        $lowStock = 0;
        $outOfStock = 0;

        $lowStockService = new LowStockService();
        $defaultThreshold = (int) ($lowStockService->getSettings()['default_low_stock_threshold'] ?? 5);

        foreach ($stocks as $s) {
            $qty = (int) $s->stock_quantity;
            $vs = $s->variantSize;

            $cost = $vs ? (float) ($vs->cost_price ?? 0.00) : 0.00;
            $selling = $vs ? (float) ($vs->selling_price ?? 0.00) : 0.00;

            $totalUnits += $qty;
            $costVal += ($qty * $cost);
            $sellingVal += ($qty * $selling);

            $threshold = $vs ? $lowStockService->getEffectiveThreshold($vs) : $defaultThreshold;

            if ($qty <= 0) {
                $outOfStock++;
            } elseif ($qty <= $threshold) {
                $lowStock++;
            } else {
                $inStock++;
            }
        }

        return [
            'total_stock_units' => $totalUnits,
            'cost_value' => round($costVal, 2),
            'selling_value' => round($sellingVal, 2),
            'in_stock_items' => $inStock,
            'low_stock_items' => $lowStock,
            'out_of_stock_items' => $outOfStock,
        ];
    }

    protected function calculatePaymentMetrics(int $storeId, array $invoiceIds, float $grossBilledRevenue): array
    {
        if (count($invoiceIds) === 0) {
            return [
                'cash' => 0.0,
                'upi' => 0.0,
                'card' => 0.0,
                'bank' => 0.0,
                'other' => 0.0,
                'total_collected' => 0.0,
                'total_outstanding' => 0.0,
            ];
        }

        $payments = InvoicePayment::whereIn('invoice_id', $invoiceIds)->get();

        $cash = 0.0;
        $upi = 0.0;
        $card = 0.0;
        $bank = 0.0;
        $other = 0.0;

        foreach ($payments as $p) {
            $amt = (float) $p->amount;
            $methodRaw = $p->payment_method instanceof \BackedEnum ? $p->payment_method->value : (string) $p->payment_method;
            $method = strtolower($methodRaw);

            if ($method === 'cash') {
                $cash += $amt;
            } elseif ($method === 'upi') {
                $upi += $amt;
            } elseif ($method === 'card') {
                $card += $amt;
            } elseif (in_array($method, ['bank_transfer', 'cheque', 'net_banking', 'bank'])) {
                $bank += $amt;
            } else {
                $other += $amt;
            }
        }

        $totalCollected = round($cash + $upi + $card + $bank + $other, 2);
        $totalOutstanding = max(0.0, round($grossBilledRevenue - $totalCollected, 2));

        return [
            'cash' => round($cash, 2),
            'upi' => round($upi, 2),
            'card' => round($card, 2),
            'bank' => round($bank, 2),
            'other' => round($other, 2),
            'total_collected' => $totalCollected,
            'total_outstanding' => $totalOutstanding,
        ];
    }

    protected function calculateCustomerMetrics(int $storeId, Carbon $start, Carbon $end, float $netSales, array $invoiceIds): array
    {
        if (count($invoiceIds) === 0) {
            return [
                'total_customers_served' => 0,
                'new_customers' => 0,
                'repeat_customers' => 0,
                'avg_customer_purchase' => 0.0,
            ];
        }

        $distinctCustomerIds = Invoice::whereIn('id', $invoiceIds)
            ->whereNotNull('customer_id')
            ->pluck('customer_id')
            ->unique()
            ->toArray();

        $totalServed = count($distinctCustomerIds);
        if ($totalServed === 0) {
            return [
                'total_customers_served' => 0,
                'new_customers' => 0,
                'repeat_customers' => 0,
                'avg_customer_purchase' => 0.0,
            ];
        }

        // New Customers created in date range
        $newCustomers = Customer::whereIn('id', $distinctCustomerIds)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        // Repeat customers: customers with > 1 invoice overall
        $repeatCustomers = DB::table('invoices')
            ->whereIn('customer_id', $distinctCustomerIds)
            ->where('status', '!=', 'cancelled')
            ->select('customer_id', DB::raw('COUNT(id) as total_invoices'))
            ->groupBy('customer_id')
            ->having('total_invoices', '>', 1)
            ->count();

        $avgCustomerPurchase = round($netSales / $totalServed, 2);

        return [
            'total_customers_served' => $totalServed,
            'new_customers' => $newCustomers,
            'repeat_customers' => $repeatCustomers,
            'avg_customer_purchase' => $avgCustomerPurchase,
        ];
    }

    protected function calculateRecentActivity(int $storeId): array
    {
        $activities = [];

        // 1. Sales
        $sales = Invoice::where('store_id', $storeId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        foreach ($sales as $s) {
            $activities[] = [
                'type' => 'sale',
                'reference' => $s->invoice_number,
                'amount' => (float) $s->grand_total,
                'date' => $s->created_at ? $s->created_at->toDateTimeString() : '',
                'description' => "POS Sales Invoice #{$s->invoice_number}",
            ];
        }

        // 2. Returns
        $returns = ReturnSale::where('store_id', $storeId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        foreach ($returns as $r) {
            $activities[] = [
                'type' => 'return',
                'reference' => $r->return_number,
                'amount' => (float) $r->total_refund_amount,
                'date' => $r->created_at ? $r->created_at->toDateTimeString() : '',
                'description' => "Sales Return #{$r->return_number}",
            ];
        }

        usort($activities, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        return array_slice($activities, 0, 10);
    }
}
