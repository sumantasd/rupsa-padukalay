<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\GoodsReceive;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\PurchaseBill;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\ReturnSale;
use App\Models\StockAdjustment;
use App\Models\StockDamageTransaction;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportPdfService
{
    public function __construct(
        protected ReportService $reportService,
        protected FinancialReportService $financialReportService,
        protected InventoryService $inventoryService,
        protected ExecutiveDashboardService $executiveDashboardService
    ) {}

    public function generatePdf(User $user, array $filters, string $action = 'inline')
    {
        $type = $filters['type'] ?? 'sales_summary';
        $reportData = $this->prepareReportData($user, $type, $filters);
        
        $filename = $this->generateFilename($type, $filters);

        $pdf = Pdf::loadView('reports.pdf', $reportData)
            ->setPaper('a4', 'portrait')
            ->setOption([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'sans-serif',
            ]);

        if ($action === 'attachment' || $action === 'download') {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    protected function prepareReportData(User $user, string $type, array $filters): array
    {
        $store = null;
        if (! empty($filters['store_id']) && $filters['store_id'] !== 'all') {
            $store = Store::find($filters['store_id']);
        }

        $periodLabel = $this->formatPeriodLabel($filters);
        $generatedAt = Carbon::now()->format('d M Y, h:i A');

        $baseData = [
            'type' => $type,
            'title' => $this->getReportTitle($type),
            'company_name' => 'RUPSA PADUKALAYA',
            'store_name' => $store ? $store->name : 'Main Outlet (STR-001)',
            'period_label' => $periodLabel,
            'generated_at' => $generatedAt,
            'user_name' => $user->name,
            'filters' => $filters,
            'kpis' => [],
            'financial_statement' => null,
            'rows' => [],
            'headers' => [],
        ];

        switch ($type) {
            case 'sales_summary':
                $summary = $this->reportService->getSalesSummary($user, $filters);
                $baseData['kpis'] = [
                    ['label' => 'Total Billed Sales', 'value' => '₹' . number_format($summary['total_billed_sales'] ?? 0, 2)],
                    ['label' => 'Completed Orders', 'value' => number_format($summary['completed_sales_count'] ?? 0)],
                    ['label' => 'COGS', 'value' => '₹' . number_format($summary['total_cogs'] ?? 0, 2)],
                    ['label' => 'Gross Profit', 'value' => '₹' . number_format($summary['gross_profit'] ?? 0, 2)],
                    ['label' => 'Outstanding Due', 'value' => '₹' . number_format($summary['total_outstanding'] ?? 0, 2)],
                ];
                $query = Invoice::with(['customer', 'store'])->where('status', 'completed');
                $this->applyStoreAndDateFilters($query, $filters, $user);
                $invoices = $query->orderBy('created_at', 'desc')->get();
                
                $baseData['headers'] = ['Invoice #', 'Date & Time', 'Customer', 'Items (Qty)', 'Grand Total', 'Paid', 'Payment Status'];
                $baseData['rows'] = $invoices->map(function ($inv) {
                    return [
                        $inv->invoice_number,
                        Carbon::parse($inv->created_at)->format('d M Y, h:i A'),
                        $inv->customer ? $inv->customer->name : 'Walk-in Customer',
                        ($inv->items_count ?? count($inv->items ?? [])) . ' items',
                        '₹' . number_format($inv->grand_total, 2),
                        '₹' . number_format($inv->paid_amount, 2),
                        strtoupper($inv->payment_status),
                    ];
                })->toArray();
                break;

            case 'sales_itemwise':
                $summary = $this->reportService->getItemWiseSales($user, $filters);
                $baseData['kpis'] = [
                    ['label' => 'Total Units Sold', 'value' => number_format($summary['total_units_sold'] ?? 0) . ' pcs'],
                    ['label' => 'Total Billed Revenue', 'value' => '₹' . number_format($summary['total_revenue'] ?? 0, 2)],
                    ['label' => 'Unique Articles Sold', 'value' => number_format(count($summary['items'] ?? []))],
                ];
                $baseData['headers'] = ['Article #', 'Product Name', 'Category', 'Brand', 'Size', 'Qty Sold', 'Revenue (₹)'];
                $baseData['rows'] = collect($summary['items'] ?? [])->map(function ($item) {
                    return [
                        $item['article_number'] ?? 'N/A',
                        $item['product_name'] ?? 'N/A',
                        $item['category_name'] ?? 'N/A',
                        $item['brand_name'] ?? 'N/A',
                        'IND ' . ($item['size_number'] ?? 'N/A'),
                        number_format($item['total_quantity_sold'] ?? 0) . ' pcs',
                        '₹' . number_format($item['total_revenue'] ?? 0, 2),
                    ];
                })->toArray();
                break;

            case 'sales_datewise':
                $summary = $this->reportService->getDateWiseSales($user, $filters);
                $baseData['kpis'] = [
                    ['label' => 'Total Billed Revenue', 'value' => '₹' . number_format($summary['total_revenue'] ?? 0, 2)],
                    ['label' => 'Total Transactions', 'value' => number_format($summary['total_sales_count'] ?? 0)],
                ];
                $baseData['headers'] = ['Period / Date', 'Transactions Count', 'Gross Revenue (₹)', 'Tax (₹)', 'Discounts (₹)', 'Net Revenue (₹)'];
                $baseData['rows'] = collect($summary['periods'] ?? [])->map(function ($p) {
                    return [
                        $p['date_group'] ?? 'N/A',
                        number_format($p['sales_count'] ?? 0),
                        '₹' . number_format($p['gross_revenue'] ?? 0, 2),
                        '₹' . number_format($p['tax_amount'] ?? 0, 2),
                        '₹' . number_format($p['discount_amount'] ?? 0, 2),
                        '₹' . number_format($p['net_revenue'] ?? 0, 2),
                    ];
                })->toArray();
                break;

            case 'profit_loss':
                $pl = $this->financialReportService->getProfitAndLoss($filters, $user);
                $grossSales = floatval($pl['gross_sales'] ?? 0);
                $discounts = floatval($pl['discounts'] ?? 0);
                $returns = floatval($pl['returns_refunds'] ?? 0);
                $netSales = floatval($pl['net_sales'] ?? $pl['net_revenue'] ?? ($grossSales - $discounts - $returns));
                $cogs = floatval($pl['cogs'] ?? 0);
                $grossProfit = floatval($pl['gross_profit'] ?? ($netSales - $cogs));
                $operatingExpenses = floatval($pl['operating_expenses'] ?? $pl['total_expenses'] ?? 0);
                $netProfit = floatval($pl['net_profit'] ?? $pl['net_store_profit'] ?? ($grossProfit - $operatingExpenses));

                $isProfit = $netProfit >= 0;

                $baseData['financial_statement'] = [
                    'gross_sales' => $grossSales,
                    'discounts' => $discounts,
                    'returns' => $returns,
                    'net_sales' => $netSales,
                    'cogs' => $cogs,
                    'gross_profit' => $grossProfit,
                    'operating_expenses' => $operatingExpenses,
                    'net_profit' => $netProfit,
                    'is_profit' => $isProfit,
                    'label' => $isProfit ? 'PROFIT' : 'LOSS',
                    'formatted_amount' => '₹' . number_format(abs($netProfit), 2),
                ];

                $baseData['kpis'] = [
                    ['label' => 'Net Billed Sales', 'value' => '₹' . number_format($netSales, 2)],
                    ['label' => 'COGS', 'value' => '₹' . number_format($cogs, 2)],
                    ['label' => 'Gross Profit', 'value' => '₹' . number_format($grossProfit, 2)],
                    ['label' => 'Operating Expenses', 'value' => '₹' . number_format($operatingExpenses, 2)],
                    ['label' => $isProfit ? 'NET PROFIT' : 'NET LOSS', 'value' => '₹' . number_format(abs($netProfit), 2)],
                ];
                break;

            case 'inventory_stock':
            case 'inventory_overview':
            case 'inventory_valuation':
            case 'inventory_fast_moving':
            case 'inventory_slow_moving':
            case 'inventory_dead_stock':
                $valuation = $this->reportService->getStockValuation($user, $filters);
                $summary = $valuation['summary'] ?? [];
                $items = collect($valuation['items'] ?? []);

                if ($type === 'inventory_fast_moving') {
                    $items = $items->filter(fn($i) => ($i['stock_quantity'] ?? 0) > 10);
                } elseif ($type === 'inventory_slow_moving') {
                    $items = $items->filter(fn($i) => ($i['stock_quantity'] ?? 0) > 0 && ($i['stock_quantity'] ?? 0) <= 5);
                } elseif ($type === 'inventory_dead_stock') {
                    $items = $items->filter(fn($i) => ($i['stock_quantity'] ?? 0) === 0);
                }

                $totalUnits = $type === 'inventory_stock' || $type === 'inventory_valuation' || $type === 'inventory_overview'
                    ? ($summary['total_stock_quantity'] ?? $items->sum('stock_quantity'))
                    : $items->sum('stock_quantity');

                $totalCostValue = $type === 'inventory_stock' || $type === 'inventory_valuation' || $type === 'inventory_overview'
                    ? ($summary['total_inventory_cost_value'] ?? $items->sum('total_cost_value'))
                    : $items->sum('total_cost_value');

                $totalSellingValue = $type === 'inventory_stock' || $type === 'inventory_valuation' || $type === 'inventory_overview'
                    ? ($summary['total_inventory_selling_value'] ?? $items->sum('total_selling_value'))
                    : $items->sum('total_selling_value');

                $grossMargin = round($totalSellingValue - $totalCostValue, 2);

                $baseData['kpis'] = [
                    ['label' => 'Total Inventory Units', 'value' => number_format($totalUnits) . ' pcs'],
                    ['label' => 'Stock Valuation (Cost)', 'value' => '₹' . number_format($totalCostValue, 2)],
                    ['label' => 'Stock Valuation (Selling)', 'value' => '₹' . number_format($totalSellingValue, 2)],
                    ['label' => 'Expected Gross Margin', 'value' => '₹' . number_format($grossMargin, 2)],
                ];

                $baseData['headers'] = ['Article #', 'Product Name', 'Brand', 'Category', 'Color', 'Size', 'Stock Qty', 'Cost Price', 'Selling Price', 'Cost Value', 'Selling Value'];
                $baseData['rows'] = $items->map(function ($it) {
                    return [
                        $it['article_number'] ?? 'N/A',
                        $it['product_name'] ?? 'N/A',
                        $it['brand_name'] ?? 'N/A',
                        $it['category_name'] ?? 'N/A',
                        $it['color'] ?? 'N/A',
                        !empty($it['size']) ? 'IND ' . $it['size'] : 'N/A',
                        number_format($it['stock_quantity'] ?? 0) . ' pcs',
                        '₹' . number_format($it['cost_price'] ?? 0, 2),
                        '₹' . number_format($it['selling_price'] ?? 0, 2),
                        '₹' . number_format($it['total_cost_value'] ?? 0, 2),
                        '₹' . number_format($it['total_selling_value'] ?? 0, 2),
                    ];
                })->values()->toArray();
                break;

            case 'inventory_low_stock':
                $stocks = InventoryStock::with(['store', 'variantSize.variant.product', 'variantSize.size'])
                    ->whereColumn('stock_quantity', '<=', 'reorder_level')
                    ->get();
                $baseData['kpis'] = [
                    ['label' => 'Low Stock Items Count', 'value' => number_format(count($stocks))],
                ];
                $baseData['headers'] = ['Article #', 'Product Name', 'Size', 'Current Stock', 'Min Reorder Threshold', 'Status'];
                $baseData['rows'] = $stocks->map(function ($st) {
                    $pvs = $st->variantSize;
                    $prod = $pvs ? $pvs->variant->product : null;
                    $qty = $st->stock_quantity ?? 0;
                    $reorder = $st->reorder_level ?? 0;
                    return [
                        $prod ? $prod->article_number : 'N/A',
                        $prod ? $prod->name : 'N/A',
                        ($pvs && $pvs->size) ? 'IND ' . $pvs->size->size_number : 'N/A',
                        number_format($qty) . ' pcs',
                        number_format($reorder) . ' pcs',
                        $qty == 0 ? 'OUT OF STOCK' : 'LOW STOCK',
                    ];
                })->toArray();
                break;

            case 'inventory_movements':
                $query = StockMovement::with(['variantSize.variant.product', 'variantSize.size', 'creator', 'store']);
                $this->applyStoreAndDateFilters($query, $filters, $user);
                $movements = $query->orderBy('created_at', 'desc')->get();

                $baseData['kpis'] = [
                    ['label' => 'Total Movements Logged', 'value' => number_format(count($movements))],
                ];
                $baseData['headers'] = ['Date & Time', 'Product Name', 'Article / Size', 'Movement Type', 'Type (IN/OUT)', 'Quantity', 'Reference', 'Staff'];
                $baseData['rows'] = $movements->map(function ($mv) {
                    $pvs = $mv->variantSize;
                    $prod = $pvs ? $pvs->variant->product : null;
                    $typeVal = is_object($mv->movement_type) ? $mv->movement_type->value : ($mv->movement_type ?? 'N/A');
                    $qty = $mv->quantity_change ?? 0;
                    return [
                        Carbon::parse($mv->created_at)->format('d M Y, h:i A'),
                        $prod ? $prod->name : 'N/A',
                        ($prod ? $prod->article_number : '') . ' (IND ' . ($pvs && $pvs->size ? $pvs->size->size_number : '') . ')',
                        strtoupper($typeVal),
                        $qty >= 0 ? 'IN' : 'OUT',
                        number_format(abs($qty)) . ' pcs',
                        $mv->reference_type ? (basename(str_replace('\\', '/', $mv->reference_type)) . ' #' . $mv->reference_id) : 'N/A',
                        $mv->creator ? $mv->creator->name : 'System',
                    ];
                })->toArray();
                break;

            case 'inventory_adjustments':
                $query = StockAdjustment::with(['creator', 'store', 'items.variantSize.variant.product']);
                $this->applyStoreAndDateFilters($query, $filters, $user);
                $adjustments = $query->orderBy('created_at', 'desc')->get();

                $baseData['headers'] = ['Adjustment #', 'Date', 'Reason', 'Notes', 'Total SKUs', 'Staff'];
                $baseData['rows'] = $adjustments->map(function ($adj) {
                    return [
                        $adj->adjustment_number ?? ('ADJ-' . $adj->id),
                        Carbon::parse($adj->created_at)->format('d M Y'),
                        $adj->reason ?? 'Stock Correction',
                        $adj->notes ?? 'N/A',
                        count($adj->items ?? []) . ' items',
                        $adj->creator ? $adj->creator->name : 'Admin',
                    ];
                })->toArray();
                break;

            case 'inventory_transfers':
                $query = StockTransfer::with(['fromStore', 'toStore', 'transferredBy']);
                $this->applyStoreAndDateFilters($query, $filters, $user);
                $transfers = $query->orderBy('created_at', 'desc')->get();

                $baseData['headers'] = ['Transfer #', 'Date', 'From Store', 'To Store', 'Status', 'Staff'];
                $baseData['rows'] = $transfers->map(function ($tr) {
                    return [
                        $tr->transfer_number ?? ('TRF-' . $tr->id),
                        Carbon::parse($tr->created_at)->format('d M Y'),
                        $tr->fromStore ? $tr->fromStore->name : 'N/A',
                        $tr->toStore ? $tr->toStore->name : 'N/A',
                        strtoupper($tr->status ?? 'COMPLETED'),
                        $tr->transferredBy ? $tr->transferredBy->name : 'Admin',
                    ];
                })->toArray();
                break;

            case 'inventory_damage':
                $query = StockDamageTransaction::with(['creator', 'store', 'items.variantSize.variant.product']);
                $this->applyStoreAndDateFilters($query, $filters, $user);
                $damages = $query->orderBy('created_at', 'desc')->get();

                $baseData['headers'] = ['Damage Ref #', 'Date', 'Reason', 'Total Units Damaged', 'Remarks', 'Staff'];
                $baseData['rows'] = $damages->map(function ($dmg) {
                    return [
                        $dmg->damage_number ?? ('DMG-' . $dmg->id),
                        Carbon::parse($dmg->created_at)->format('d M Y'),
                        strtoupper($dmg->reason ?? 'DAMAGED'),
                        number_format($dmg->total_quantity ?? 0) . ' pcs',
                        $dmg->remarks ?? 'N/A',
                        $dmg->creator ? $dmg->creator->name : 'Admin',
                    ];
                })->toArray();
                break;

            case 'payment_summary':
            case 'payment_datewise':
            case 'payment_collections':
            case 'payment_refunds':
                $summary = $this->reportService->getPaymentSummary($user, $filters);
                $baseData['kpis'] = [
                    ['label' => 'Total Collections Billed', 'value' => '₹' . number_format($summary['total_collections'] ?? 0, 2)],
                    ['label' => 'Cash Collections', 'value' => '₹' . number_format($summary['cash_collections'] ?? 0, 2)],
                    ['label' => 'UPI / Digital Collections', 'value' => '₹' . number_format($summary['upi_collections'] ?? 0, 2)],
                    ['label' => 'Card Collections', 'value' => '₹' . number_format($summary['card_collections'] ?? 0, 2)],
                ];
                $datewise = $this->reportService->getDateWisePayments($user, $filters);
                $baseData['headers'] = ['Period / Date', 'Transactions Count', 'Cash Collections (₹)', 'UPI Collections (₹)', 'Card Collections (₹)', 'Total Collections (₹)'];
                $baseData['rows'] = collect($datewise['periods'] ?? [])->map(function ($p) {
                    return [
                        $p['date_group'] ?? 'N/A',
                        number_format($p['payment_count'] ?? 0),
                        '₹' . number_format($p['cash'] ?? 0, 2),
                        '₹' . number_format($p['upi'] ?? 0, 2),
                        '₹' . number_format($p['card'] ?? 0, 2),
                        '₹' . number_format($p['total'] ?? 0, 2),
                    ];
                })->toArray();
                break;

            case 'purchase_summary':
            case 'purchase_orders':
                $summary = $this->reportService->getPurchaseSummary($user, $filters);
                $baseData['kpis'] = [
                    ['label' => 'Total Purchase Spend', 'value' => '₹' . number_format($summary['total_purchase_spend'] ?? 0, 2)],
                    ['label' => 'Total Purchase Orders', 'value' => number_format($summary['total_po_count'] ?? 0)],
                    ['label' => 'Outstanding Supplier Payable', 'value' => '₹' . number_format($summary['outstanding_supplier_due'] ?? 0, 2)],
                ];
                $query = PurchaseOrder::with(['supplier', 'store']);
                $this->applyStoreAndDateFilters($query, $filters, $user);
                $pos = $query->orderBy('created_at', 'desc')->get();

                $baseData['headers'] = ['PO Number', 'Order Date', 'Supplier', 'Items (SKUs)', 'Grand Total (₹)', 'Status'];
                $baseData['rows'] = $pos->map(function ($po) {
                    return [
                        $po->po_number,
                        Carbon::parse($po->order_date)->format('d M Y'),
                        $po->supplier ? $po->supplier->name : 'N/A',
                        ($po->items_count ?? count($po->items ?? [])) . ' SKUs',
                        '₹' . number_format($po->grand_total, 2),
                        strtoupper($po->status),
                    ];
                })->toArray();
                break;

            case 'purchase_bills':
                $summary = $this->reportService->getPurchaseSummary($user, $filters);
                $baseData['kpis'] = [
                    ['label' => 'Total Purchase Bills Spend', 'value' => '₹' . number_format($summary['total_purchase_spend'] ?? 0, 2)],
                    ['label' => 'Total Purchase Bills Count', 'value' => number_format($summary['total_bills_count'] ?? 0)],
                ];
                $query = PurchaseBill::with(['supplier', 'store']);
                $this->applyStoreAndDateFilters($query, $filters, $user, 'bill_date');
                $bills = $query->orderBy('bill_date', 'desc')->get();

                $baseData['headers'] = ['Bill Number', 'Bill Date', 'Supplier', 'Payment Status', 'Grand Total (₹)', 'Paid (₹)', 'Due (₹)'];
                $baseData['rows'] = $bills->map(function ($b) {
                    return [
                        $b->bill_number,
                        Carbon::parse($b->bill_date)->format('d M Y'),
                        $b->supplier ? $b->supplier->name : 'N/A',
                        strtoupper($b->payment_status ?? 'UNPAID'),
                        '₹' . number_format($b->grand_total, 2),
                        '₹' . number_format($b->paid_amount, 2),
                        '₹' . number_format($b->due_amount, 2),
                    ];
                })->toArray();
                break;

            case 'purchase_grn':
                $query = GoodsReceive::with(['supplier', 'store', 'purchaseOrder']);
                $this->applyStoreAndDateFilters($query, $filters, $user, 'received_date');
                $grns = $query->orderBy('received_date', 'desc')->get();

                $baseData['headers'] = ['GRN Number', 'Receive Date', 'PO Number', 'Supplier', 'Status', 'Received Qty'];
                $baseData['rows'] = $grns->map(function ($grn) {
                    return [
                        $grn->grn_number,
                        Carbon::parse($grn->received_date)->format('d M Y'),
                        $grn->purchaseOrder ? $grn->purchaseOrder->po_number : 'N/A',
                        $grn->supplier ? $grn->supplier->name : 'N/A',
                        strtoupper($grn->status ?? 'RECEIVED'),
                        number_format($grn->total_received_quantity ?? 0) . ' pcs',
                    ];
                })->toArray();
                break;

            case 'purchase_returns':
                $query = PurchaseReturn::with(['supplier', 'store']);
                $this->applyStoreAndDateFilters($query, $filters, $user, 'created_at');
                $returns = $query->orderBy('created_at', 'desc')->get();

                $baseData['headers'] = ['Return Number', 'Return Date', 'Supplier', 'Reason', 'Refund Amount (₹)', 'Refund Mode'];
                $baseData['rows'] = $returns->map(function ($ret) {
                    return [
                        $ret->return_number,
                        Carbon::parse($ret->created_at)->format('d M Y'),
                        $ret->supplier ? $ret->supplier->name : 'N/A',
                        $ret->reason ?? 'Vendor Return',
                        '₹' . number_format($ret->total_return_amount ?? 0, 2),
                        strtoupper($ret->refund_mode ?? 'COMPLETED'),
                    ];
                })->toArray();
                break;

            case 'customer_summary':
                $summary = $this->reportService->getCustomerSummary($user, $filters);
                $baseData['kpis'] = [
                    ['label' => 'Total Registered Customers', 'value' => number_format($summary['total_customers_count'] ?? 0)],
                    ['label' => 'Active Purchasing Customers', 'value' => number_format($summary['active_purchasing_customers'] ?? 0)],
                    ['label' => 'Average Order Value (AOV)', 'value' => '₹' . number_format($summary['average_order_value'] ?? 0, 2)],
                ];
                $customers = Customer::orderBy('name', 'asc')->get();
                $baseData['headers'] = ['Customer Name', 'Mobile Number', 'Email', 'City', 'Loyalty Points', 'Store Credit (₹)'];
                $baseData['rows'] = $customers->map(function ($c) {
                    return [
                        $c->name,
                        $c->mobile_number ?? 'N/A',
                        $c->email ?? 'N/A',
                        $c->city ?? 'N/A',
                        number_format($c->loyalty_points ?? 0) . ' pts',
                        '₹' . number_format($c->store_credit_balance ?? 0, 2),
                    ];
                })->toArray();
                break;

            default:
                $summary = $this->reportService->getSalesSummary($user, $filters);
                $baseData['kpis'] = [
                    ['label' => 'Total Billed Sales', 'value' => '₹' . number_format($summary['total_billed_sales'] ?? 0, 2)],
                ];
                break;
        }

        return $baseData;
    }

    protected function applyStoreAndDateFilters($query, array $filters, User $user, string $dateColumn = 'created_at'): void
    {
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $query->whereIn('store_id', $userStoreIds);
        }

        if (! empty($filters['store_id']) && $filters['store_id'] !== 'all') {
            $query->where('store_id', (int) $filters['store_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where($dateColumn, '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (! empty($filters['date_to'])) {
            $query->where($dateColumn, '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }
    }

    protected function formatPeriodLabel(array $filters): string
    {
        $preset = $filters['period'] ?? $filters['preset'] ?? null;
        if ($preset && $preset !== 'custom') {
            return ucwords(str_replace('_', ' ', $preset));
        }

        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            return Carbon::parse($filters['date_from'])->format('d M Y') . ' to ' . Carbon::parse($filters['date_to'])->format('d M Y');
        }

        if (! empty($filters['date_from'])) {
            return 'From ' . Carbon::parse($filters['date_from'])->format('d M Y');
        }

        return 'All Available Records';
    }

    protected function getReportTitle(string $type): string
    {
        $titles = [
            'sales_summary' => 'Sales Summary Report',
            'sales_itemwise' => 'Item-Wise Sales Report',
            'sales_datewise' => 'Date-Wise Sales Report',
            'profit_loss' => 'Profit & Loss Statement Report',
            'inventory_stock' => 'Stock Overview Report',
            'inventory_overview' => 'Stock Overview Report',
            'inventory_valuation' => 'Stock Valuation Report',
            'inventory_low_stock' => 'Low Stock Alerts Report',
            'inventory_fast_moving' => 'Fast Moving Stock Report',
            'inventory_slow_moving' => 'Slow Moving Stock Report',
            'inventory_dead_stock' => 'Dead Stock Report',
            'inventory_movements' => 'Stock Movement Ledger Report',
            'inventory_adjustments' => 'Stock Adjustments Log Report',
            'inventory_transfers' => 'Stock Transfer Audit Report',
            'inventory_damage' => 'Stock Damage Report',
            'payment_summary' => 'Payment & Collections Summary',
            'payment_datewise' => 'Date-Wise Payment Report',
            'payment_collections' => 'Collections Ledger Report',
            'payment_refunds' => 'Payment Refunds Report',
            'purchase_summary' => 'Purchase & Spend Report',
            'purchase_orders' => 'Purchase Orders Report',
            'purchase_bills' => 'Purchase Bills Report',
            'purchase_grn' => 'Goods Receive (GRN) Report',
            'purchase_returns' => 'Purchase Returns Report',
            'customer_summary' => 'Customer Directory & Analytics',
        ];

        return $titles[$type] ?? 'Admin ERP Report';
    }

    protected function generateFilename(string $type, array $filters): string
    {
        $slug = str_replace('_', '-', $type);
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        if ($dateFrom && $dateTo) {
            return "RUPSA-{$slug}-{$dateFrom}-to-{$dateTo}.pdf";
        }

        if ($dateFrom) {
            return "RUPSA-{$slug}-{$dateFrom}.pdf";
        }

        return "RUPSA-{$slug}-" . Carbon::now()->format('Y-m-d') . ".pdf";
    }
}
