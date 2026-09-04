<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ReturnSale;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    public function getAuthorizedStoreIds(User $user, ?int $requestedStoreId = null): ?array
    {
        if ($user->roles()->where('name', 'Super Admin')->exists()) {
            return $requestedStoreId ? [$requestedStoreId] : null;
        }

        $userStoreIds = $user->stores()->pluck('stores.id')->toArray();

        if ($requestedStoreId !== null) {
            if (! in_array($requestedStoreId, $userStoreIds)) {
                throw new \RuntimeException('Forbidden: You are not authorized to view financial reports for this store.', 403);
            }

            return [$requestedStoreId];
        }

        return $userStoreIds;
    }

    public function resolveDateRange(array $filters): array
    {
        $preset = $filters['period'] ?? null;
        $now = Carbon::now();

        if (! empty($filters['date_from']) || ! empty($filters['date_to'])) {
            $start = ! empty($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : $now->copy()->startOfMonth();
            $end = ! empty($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : $now->copy()->endOfDay();
            $diffDays = max(1, $start->diffInDays($end));
            $prevStart = $start->copy()->subDays($diffDays);
            $prevEnd = $start->copy()->subSecond();

            return [
                'start' => $start,
                'end' => $end,
                'prev_start' => $prevStart,
                'prev_end' => $prevEnd,
            ];
        }

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

    public function calculateFinancialsForStore(int $storeId, Carbon $start, Carbon $end, array $filters = []): array
    {
        $invQuery = Invoice::where('store_id', $storeId)
            ->where('status', '!=', 'cancelled')
            ->whereBetween('created_at', [$start, $end]);

        if (! empty($filters['cashier_id'])) {
            $invQuery->where('created_by', (int) $filters['cashier_id']);
        }

        $grossSales = (float) (clone $invQuery)->sum('subtotal');
        $discounts = (float) (clone $invQuery)->sum('discount_amount');
        $taxableSales = (float) (clone $invQuery)->sum('taxable_amount');
        $cgst = (float) (clone $invQuery)->sum('total_cgst');
        $sgst = (float) (clone $invQuery)->sum('total_sgst');
        $igst = (float) (clone $invQuery)->sum('total_igst');
        $gstTax = (float) (clone $invQuery)->sum('total_tax');
        $grossBilledRevenue = (float) (clone $invQuery)->sum('grand_total');
        $totalCollections = (float) (clone $invQuery)->sum('paid_amount');
        $outstanding = (float) (clone $invQuery)->sum(DB::raw('COALESCE(grand_total, 0) - COALESCE(paid_amount, 0)'));
        $completedSalesCount = (int) (clone $invQuery)->count();

        // Returns Query
        $retQuery = ReturnSale::where('store_id', $storeId)
            ->whereBetween('created_at', [$start, $end]);

        $returnsRefunds = (float) (clone $retQuery)->sum('total_refund_amount');
        $returnedCount = (int) (clone $retQuery)->count();

        $netBilledRevenue = max(0.0, round($grossBilledRevenue - $returnsRefunds, 2));

        // COGS Calculation
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

        // Returned Taxable Value estimation
        $returnedTaxable = round($returnsRefunds / 1.12, 2);
        $netTaxableRevenue = max(0.0, round($taxableSales - $returnedTaxable, 2));

        // Net Billed Sales Revenue (Tax-inclusive)
        $netBilledRevenue = max(0.0, round($grossBilledRevenue - $returnsRefunds, 2));

        // Gross Profit = Net Billed Sales Revenue - COGS
        $grossProfit = round($netBilledRevenue - $cogs, 2);
        $grossMarginPct = $netBilledRevenue > 0 ? round(($grossProfit / $netBilledRevenue) * 100.0, 2) : 0.00;

        // Operating Expenses
        $expQuery = Expense::where('store_id', $storeId)
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()]);
        $operatingExpenses = (float) $expQuery->sum('amount');

        $netStoreProfit = round($grossProfit - $operatingExpenses, 2);

        return [
            'store_id' => $storeId,
            'gross_sales' => round($grossBilledRevenue, 2),
            'discounts' => round($discounts, 2),
            'taxable_sales' => round($taxableSales, 2),
            'cgst' => round($cgst, 2),
            'sgst' => round($sgst, 2),
            'igst' => round($igst, 2),
            'gst_tax' => round($gstTax, 2),
            'gross_billed_revenue' => round($grossBilledRevenue, 2),
            'returns_refunds' => round($returnsRefunds, 2),
            'net_billed_revenue' => round($netBilledRevenue, 2),
            'net_sales' => round($netBilledRevenue, 2),
            'net_taxable_revenue' => round($netTaxableRevenue, 2),
            'invoice_cogs' => round($invoiceCogs, 2),
            'returned_cogs' => round($returnedCogs, 2),
            'cogs' => round($cogs, 2),
            'gross_profit' => round($grossProfit, 2),
            'gross_margin_percentage' => $grossMarginPct,
            'operating_expenses' => round($operatingExpenses, 2),
            'net_store_profit' => round($netStoreProfit, 2),
            'net_profit' => round($netStoreProfit, 2),
            'total_collections' => round($totalCollections, 2),
            'total_outstanding' => round($outstanding, 2),
            'completed_sales_count' => $completedSalesCount,
            'returned_transactions_count' => $returnedCount,
        ];
    }

    public function getConsolidatedSales(array $filters, User $user): array
    {
        $requestedStoreId = isset($filters['store_id']) ? (int) $filters['store_id'] : null;
        $authorizedStoreIds = $this->getAuthorizedStoreIds($user, $requestedStoreId);

        $stores = Store::where('is_active', true)
            ->when($authorizedStoreIds !== null, fn ($q) => $q->whereIn('id', $authorizedStoreIds))
            ->orderBy('name', 'asc')
            ->get();

        $dates = $this->resolveDateRange($filters);
        $storeItems = [];

        $totGrossSales = 0.0;
        $totDiscounts = 0.0;
        $totTaxableSales = 0.0;
        $totCgst = 0.0;
        $totSgst = 0.0;
        $totIgst = 0.0;
        $totGstTax = 0.0;
        $totGrossBilled = 0.0;
        $totReturnsRefunds = 0.0;
        $totNetBilled = 0.0;
        $totNetTaxable = 0.0;
        $totCogs = 0.0;
        $totGrossProfit = 0.0;
        $totExpenses = 0.0;
        $totNetStoreProfit = 0.0;
        $totCollections = 0.0;
        $totOutstanding = 0.0;

        foreach ($stores as $st) {
            $sData = $this->calculateFinancialsForStore($st->id, $dates['start'], $dates['end'], $filters);
            $sData['store_code'] = $st->code;
            $sData['store_name'] = $st->name;

            $totGrossSales += $sData['gross_sales'];
            $totDiscounts += $sData['discounts'];
            $totTaxableSales += $sData['taxable_sales'];
            $totCgst += $sData['cgst'];
            $totSgst += $sData['sgst'];
            $totIgst += $sData['igst'];
            $totGstTax += $sData['gst_tax'];
            $totGrossBilled += $sData['gross_billed_revenue'];
            $totReturnsRefunds += $sData['returns_refunds'];
            $totNetBilled += $sData['net_billed_revenue'];
            $totNetTaxable += $sData['net_taxable_revenue'];
            $totCogs += $sData['cogs'];
            $totGrossProfit += $sData['gross_profit'];
            $totExpenses += $sData['operating_expenses'];
            $totNetStoreProfit += $sData['net_store_profit'];
            $totCollections += $sData['total_collections'];
            $totOutstanding += $sData['total_outstanding'];

            $storeItems[] = $sData;
        }

        $totGrossMarginPct = $totNetTaxable > 0 ? round(($totGrossProfit / $totNetTaxable) * 100.0, 2) : 0.00;

        return [
            'summary' => [
                'total_stores' => count($storeItems),
                'gross_sales' => round($totGrossSales, 2),
                'discounts' => round($totDiscounts, 2),
                'taxable_sales' => round($totTaxableSales, 2),
                'cgst' => round($totCgst, 2),
                'sgst' => round($totSgst, 2),
                'igst' => round($totIgst, 2),
                'gst_tax' => round($totGstTax, 2),
                'gross_billed_revenue' => round($totGrossBilled, 2),
                'returns_refunds' => round($totReturnsRefunds, 2),
                'net_billed_revenue' => round($totNetBilled, 2),
                'net_taxable_revenue' => round($totNetTaxable, 2),
                'cogs' => round($totCogs, 2),
                'gross_profit' => round($totGrossProfit, 2),
                'gross_margin_percentage' => $totGrossMarginPct,
                'operating_expenses' => round($totExpenses, 2),
                'net_store_profit' => round($totNetStoreProfit, 2),
                'total_collections' => round($totCollections, 2),
                'total_outstanding' => round($totOutstanding, 2),
            ],
            'stores' => $storeItems,
        ];
    }

    public function getProfitAndLoss(array $filters, User $user): array
    {
        $dates = $this->resolveDateRange($filters);
        $currSales = $this->getConsolidatedSales($filters, $user);

        // Previous Period P&L for Comparison
        $prevDates = [
            'date_from' => $dates['prev_start']->toDateString(),
            'date_to' => $dates['prev_end']->toDateString(),
        ];
        if (isset($filters['store_id'])) {
            $prevDates['store_id'] = $filters['store_id'];
        }
        $prevSales = $this->getConsolidatedSales($prevDates, $user);

        $currSum = $currSales['summary'];
        $prevSum = $prevSales['summary'];

        // Expense breakdown by categories
        $requestedStoreId = isset($filters['store_id']) ? (int) $filters['store_id'] : null;
        $authorizedStoreIds = $this->getAuthorizedStoreIds($user, $requestedStoreId);

        $expCategories = ExpenseCategory::all();
        $expenseBreakdown = [];

        foreach ($expCategories as $cat) {
            $catExpQuery = Expense::where('expense_category_id', $cat->id)
                ->whereBetween('expense_date', [$dates['start']->toDateString(), $dates['end']->toDateString()]);

            if ($authorizedStoreIds !== null) {
                $catExpQuery->whereIn('store_id', $authorizedStoreIds);
            }

            $catAmount = (float) $catExpQuery->sum('amount');
            if ($catAmount > 0) {
                $expenseBreakdown[] = [
                    'category_id' => $cat->id,
                    'category_name' => $cat->name,
                    'category_code' => $cat->code,
                    'amount' => round($catAmount, 2),
                ];
            }
        }

        $growth = fn ($c, $p) => $p > 0 ? round((($c - $p) / $p) * 100.0, 2) : ($c > 0 ? 100.00 : 0.00);

        return [
            'revenue' => [
                'gross_sales' => $currSum['gross_sales'],
                'discounts' => $currSum['discounts'],
                'taxable_sales' => $currSum['taxable_sales'],
                'gst_collected' => $currSum['gst_tax'],
                'gross_billed_revenue' => $currSum['gross_billed_revenue'],
                'returns_refunds' => $currSum['returns_refunds'],
                'net_billed_revenue' => $currSum['net_billed_revenue'],
                'net_sales' => $currSum['net_billed_revenue'],
                'net_taxable_revenue' => $currSum['net_taxable_revenue'],
            ],
            'cost_of_goods_sold' => [
                'invoice_cogs' => round($currSum['cogs'], 2),
                'returned_cogs' => 0.00,
                'net_cogs' => $currSum['cogs'],
            ],
            'profitability' => [
                'gross_profit' => $currSum['gross_profit'],
                'gross_margin_percentage' => $currSum['gross_margin_percentage'],
                'operating_expenses' => $currSum['operating_expenses'],
                'net_profit' => $currSum['net_store_profit'],
            ],
            'expense_categories_breakdown' => $expenseBreakdown,
            'period_comparison' => [
                'net_revenue_growth_pct' => $growth($currSum['net_billed_revenue'], $prevSum['net_billed_revenue']),
                'gross_profit_growth_pct' => $growth($currSum['gross_profit'], $prevSum['gross_profit']),
                'net_profit_growth_pct' => $growth($currSum['net_store_profit'], $prevSum['net_store_profit']),
            ],
        ];
    }

    public function getGstLiability(array $filters, User $user): array
    {
        $dates = $this->resolveDateRange($filters);
        $requestedStoreId = isset($filters['store_id']) ? (int) $filters['store_id'] : null;
        $authorizedStoreIds = $this->getAuthorizedStoreIds($user, $requestedStoreId);

        $invQuery = Invoice::where('status', 'completed')
            ->whereBetween('created_at', [$dates['start'], $dates['end']]);

        if ($authorizedStoreIds !== null) {
            $invQuery->whereIn('store_id', $authorizedStoreIds);
        }

        $validInvoiceIds = (clone $invQuery)->pluck('id')->toArray();
        $totalInvoicesCount = count($validInvoiceIds);

        $grossTaxable = (float) (clone $invQuery)->sum('taxable_amount');
        $grossCgst = (float) (clone $invQuery)->sum('total_cgst');
        $grossSgst = (float) (clone $invQuery)->sum('total_sgst');
        $grossIgst = (float) (clone $invQuery)->sum('total_igst');
        $grossTotalGst = (float) (clone $invQuery)->sum('total_tax');

        // Sales Returns Tax Credit Adjustment
        $retQuery = ReturnSale::whereBetween('created_at', [$dates['start'], $dates['end']]);
        if ($authorizedStoreIds !== null) {
            $retQuery->whereIn('store_id', $authorizedStoreIds);
        }

        $totalRefunds = (float) (clone $retQuery)->sum('total_refund_amount');
        $salesReturnTaxCredit = round($totalRefunds - ($totalRefunds / 1.12), 2); // GST component of refunds

        $netTaxable = max(0.0, round($grossTaxable - ($totalRefunds / 1.12), 2));
        $netGstPayable = max(0.0, round($grossTotalGst - $salesReturnTaxCredit, 2));

        // Store-wise GST Breakdown
        $stores = Store::where('is_active', true)
            ->when($authorizedStoreIds !== null, fn ($q) => $q->whereIn('id', $authorizedStoreIds))
            ->get();

        $storeTaxBreakdown = [];
        foreach ($stores as $st) {
            $stInvQuery = Invoice::where('store_id', $st->id)
                ->where('status', 'completed')
                ->whereBetween('created_at', [$dates['start'], $dates['end']]);

            $stRetQuery = ReturnSale::where('store_id', $st->id)
                ->whereBetween('created_at', [$dates['start'], $dates['end']]);

            $stGrossTaxable = (float) (clone $stInvQuery)->sum('taxable_amount');
            $stCgst = (float) (clone $stInvQuery)->sum('total_cgst');
            $stSgst = (float) (clone $stInvQuery)->sum('total_sgst');
            $stIgst = (float) (clone $stInvQuery)->sum('total_igst');
            $stTotalTax = (float) (clone $stInvQuery)->sum('total_tax');
            $stRefunds = (float) (clone $stRetQuery)->sum('total_refund_amount');
            $stTaxCredit = round($stRefunds - ($stRefunds / 1.12), 2);
            $stNetGst = max(0.0, round($stTotalTax - $stTaxCredit, 2));

            $storeTaxBreakdown[] = [
                'store_id' => $st->id,
                'store_code' => $st->code,
                'store_name' => $st->name,
                'taxable_value' => round($stGrossTaxable, 2),
                'cgst_amount' => round($stCgst, 2),
                'sgst_amount' => round($stSgst, 2),
                'igst_amount' => round($stIgst, 2),
                'total_tax_amount' => round($stTotalTax, 2),
                'sales_return_tax_credit' => $stTaxCredit,
                'net_gst_payable' => $stNetGst,
            ];
        }

        // HSN-wise Tax Summary Table
        $hsnSummary = [];
        if ($totalInvoicesCount > 0) {
            $hsnRows = DB::table('invoice_items')
                ->whereIn('invoice_id', $validInvoiceIds)
                ->select(
                    'hsn_code_snapshot as hsn_code',
                    'tax_rate_percentage',
                    DB::raw('SUM(quantity) as total_qty'),
                    DB::raw('SUM(taxable_value) as taxable_val'),
                    DB::raw('SUM(cgst_amount) as cgst_val'),
                    DB::raw('SUM(sgst_amount) as sgst_val'),
                    DB::raw('SUM(igst_amount) as igst_val'),
                    DB::raw('SUM(total_tax_amount) as total_tax_val')
                )
                ->groupBy('hsn_code_snapshot', 'tax_rate_percentage')
                ->get();

            foreach ($hsnRows as $hr) {
                $hsnSummary[] = [
                    'hsn_code' => $hr->hsn_code ?? 'N/A',
                    'tax_rate_percentage' => (float) $hr->tax_rate_percentage,
                    'total_quantity' => (int) $hr->total_qty,
                    'taxable_value' => round((float) $hr->taxable_val, 2),
                    'cgst_amount' => round((float) $hr->cgst_val, 2),
                    'sgst_amount' => round((float) $hr->sgst_val, 2),
                    'igst_amount' => round((float) $hr->igst_val, 2),
                    'total_tax_amount' => round((float) $hr->total_tax_val, 2),
                ];
            }
        }

        // GSTR Compliance Export Readiness Payload
        $gstrReadiness = [
            'gstr1_b2c_pos_sales_ready' => true,
            'gstr3b_tax_liability_ready' => true,
            'export_format' => 'JSON',
            'export_period' => [
                'from' => $dates['start']->toDateString(),
                'to' => $dates['end']->toDateString(),
            ],
            'total_taxable_value' => round($grossTaxable, 2),
            'total_tax_liability' => round($grossTotalGst, 2),
        ];

        return [
            'tax_liability_summary' => [
                'total_invoices_count' => $totalInvoicesCount,
                'gross_taxable_value' => round($grossTaxable, 2),
                'gross_cgst_amount' => round($grossCgst, 2),
                'gross_sgst_amount' => round($grossSgst, 2),
                'gross_igst_amount' => round($grossIgst, 2),
                'gross_total_gst' => round($grossTotalGst, 2),
                'sales_returns_tax_credit' => $salesReturnTaxCredit,
                'net_taxable_value' => $netTaxable,
                'net_gst_payable' => $netGstPayable,
            ],
            'store_tax_breakdown' => $storeTaxBreakdown,
            'hsn_tax_summary' => $hsnSummary,
            'gstr_compliance_export_readiness' => $gstrReadiness,
        ];
    }
}
