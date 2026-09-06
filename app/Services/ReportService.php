<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\LoyaltyAccount;
use App\Models\PurchaseBill;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\ReturnSale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    protected function applyDateBounds($query, array $filters, string $column = 'created_at'): void
    {
        if (! empty($filters['date_from'])) {
            $start = Carbon::parse($filters['date_from'])->startOfDay();
            $query->where($column, '>=', $start);
        }

        if (! empty($filters['date_to'])) {
            $end = Carbon::parse($filters['date_to'])->endOfDay();
            $query->where($column, '<=', $end);
        }
    }

    public function getSalesSummary(User $user, array $filters): array
    {
        $query = Invoice::query();

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $query->whereIn('store_id', $userStoreIds);
        }

        if (! empty($filters['store_id'])) {
            $query->where('store_id', (int) $filters['store_id']);
        }

        $this->applyDateBounds($query, $filters, 'created_at');

        if (! empty($filters['cashier_id'])) {
            $query->where('created_by', (int) $filters['cashier_id']);
        }

        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', (int) $filters['customer_id']);
        }

        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', trim((string) $filters['payment_status']));
        }

        if (! empty($filters['status'])) {
            $query->where('status', trim((string) $filters['status']));
        } else {
            $query->where('status', '!=', 'cancelled');
        }

        $salesCount = (int) $query->count();
        $subtotal = (float) $query->sum('subtotal');
        $discountTotal = (float) $query->sum('discount_amount');
        $taxableAmount = (float) $query->sum('taxable_amount');
        $cgst = (float) $query->sum('total_cgst');
        $sgst = (float) $query->sum('total_sgst');
        $igst = (float) $query->sum('total_igst');
        $totalTax = (float) $query->sum('total_tax');
        $grandTotal = (float) $query->sum('grand_total');
        $paidAmount = (float) $query->sum('paid_amount');
        $outstandingAmount = max(0.0, round($grandTotal - $paidAmount, 2));

        $validInvoiceIds = (clone $query)->pluck('id');
        $totalItemsSold = (int) InvoiceItem::whereIn('invoice_id', $validInvoiceIds)->sum('quantity');

        // Returns & Refund Total Query
        $returnQuery = ReturnSale::query();
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $returnQuery->whereIn('store_id', $userStoreIds);
        }

        if (! empty($filters['store_id'])) {
            $returnQuery->where('store_id', (int) $filters['store_id']);
        }

        $this->applyDateBounds($returnQuery, $filters, 'created_at');

        $refundAmount = (float) $returnQuery->sum('total_refund_amount');

        return [
            'total_sales_count' => $salesCount,
            'total_items_sold' => $totalItemsSold,
            'total_subtotal' => round($subtotal, 2),
            'total_discount' => round($discountTotal, 2),
            'total_taxable_amount' => round($taxableAmount, 2),
            'total_cgst' => round($cgst, 2),
            'total_sgst' => round($sgst, 2),
            'total_igst' => round($igst, 2),
            'total_tax' => round($totalTax, 2),
            'total_grand_total' => round($grandTotal, 2),
            'total_paid_amount' => round($paidAmount, 2),
            'total_outstanding_amount' => round($outstandingAmount, 2),
            'total_refund_amount' => round($refundAmount, 2),
            'returns' => round($refundAmount, 2),
            'net_revenue' => max(0.0, round($grandTotal - $refundAmount, 2)),
            'net_sales' => max(0.0, round($grandTotal - $refundAmount, 2)),
        ];
    }

    public function getStorePerformance(User $user, array $filters): array
    {
        $storeQuery = Store::query();

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $storeQuery->whereIn('id', $userStoreIds);
        } else {
            if (! empty($filters['store_id'])) {
                $storeQuery->where('id', (int) $filters['store_id']);
            }
        }

        $stores = $storeQuery->orderBy('name', 'asc')->get();
        $performanceData = [];

        foreach ($stores as $st) {
            $invQuery = Invoice::where('store_id', $st->id);
            $this->applyDateBounds($invQuery, $filters, 'created_at');

            $salesCount = (int) $invQuery->count();
            $grossSales = (float) $invQuery->sum('subtotal');
            $discounts = (float) $invQuery->sum('discount_amount');
            $tax = (float) $invQuery->sum('total_tax');
            $netSales = (float) $invQuery->sum('grand_total');
            $paidCollection = (float) $invQuery->sum('paid_amount');
            $outstanding = max(0.0, round($netSales - $paidCollection, 2));

            $retQuery = ReturnSale::where('store_id', $st->id);
            $this->applyDateBounds($retQuery, $filters, 'created_at');

            $returnsCount = (int) $retQuery->count();
            $refundsTotal = (float) $retQuery->sum('total_refund_amount');
            $finalSalesValue = max(0.0, round($netSales - $refundsTotal, 2));

            $performanceData[] = [
                'store_id' => $st->id,
                'store_code' => $st->code,
                'store_name' => $st->name,
                'sales_count' => $salesCount,
                'gross_sales' => round($grossSales, 2),
                'discounts' => round($discounts, 2),
                'tax' => round($tax, 2),
                'net_sales' => round($netSales, 2),
                'returns_count' => $returnsCount,
                'refunds_total' => round($refundsTotal, 2),
                'final_sales_value' => round($finalSalesValue, 2),
                'paid_collection' => round($paidCollection, 2),
                'outstanding_amount' => round($outstanding, 2),
            ];
        }

        return [
            'total_stores' => count($performanceData),
            'stores' => $performanceData,
        ];
    }

    public function getStockValuation(User $user, array $filters): array
    {
        $query = InventoryStock::with([
            'variantSize.variant.product.brand',
            'variantSize.variant.product.category',
            'variantSize.variant.color',
            'variantSize.size',
            'store',
            'warehouse',
        ]);

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $query->whereIn('store_id', $userStoreIds);
        } else {
            if (! empty($filters['store_id'])) {
                $query->where('store_id', (int) $filters['store_id']);
            }
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        if (! empty($filters['category_id'])) {
            $catId = (int) $filters['category_id'];
            $query->whereHas('variantSize.variant.product', function ($pq) use ($catId) {
                $pq->where('category_id', $catId);
            });
        }

        if (! empty($filters['brand_id'])) {
            $brandId = (int) $filters['brand_id'];
            $query->whereHas('variantSize.variant.product', function ($pq) use ($brandId) {
                $pq->where('brand_id', $brandId);
            });
        }

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->whereHas('variantSize', function ($vq) use ($search) {
                $vq->where('sku', 'LIKE', "%{$search}%")
                    ->orWhereHas('variant.product', function ($pq) use ($search) {
                        $pq->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('article_number', 'LIKE', "%{$search}%");
                    });
            });
        }

        $stocks = $query->get();

        $items = [];
        $totalQuantity = 0;
        $totalCostValue = 0.0;
        $totalMrpValue = 0.0;
        $totalSellingValue = 0.0;

        foreach ($stocks as $stock) {
            $vs = $stock->variantSize;
            if (! $vs) {
                continue;
            }

            $qty = (int) $stock->stock_quantity;
            $sellingPrice = (float) ($vs->selling_price ?? 0.00);
            $costPrice = (float) ($vs->cost_price ?? 0.00);
            $mrp = (float) ($vs->mrp ?? 0.00);

            $costVal = round($qty * $costPrice, 2);
            $mrpVal = round($qty * $mrp, 2);
            $sellingVal = round($qty * $sellingPrice, 2);

            $totalQuantity += $qty;
            $totalCostValue += $costVal;
            $totalMrpValue += $mrpVal;
            $totalSellingValue += $sellingVal;

            $product = $vs->variant?->product;

            $items[] = [
                'id' => $stock->id,
                'sku' => $vs->sku,
                'article_number' => $product?->article_number ?? 'N/A',
                'product_name' => $product?->name ?? 'N/A',
                'brand_name' => $product?->brand?->name ?? 'N/A',
                'category_name' => $product?->category?->name ?? 'N/A',
                'color' => $vs->variant?->color?->name ?? 'N/A',
                'size' => $vs->size?->size_number ?? 'N/A',
                'store_id' => $stock->store_id,
                'store_name' => $stock->store?->name,
                'warehouse_id' => $stock->warehouse_id,
                'warehouse_name' => $stock->warehouse?->name,
                'stock_quantity' => $qty,
                'cost_price' => $costPrice,
                'mrp' => $mrp,
                'selling_price' => $sellingPrice,
                'total_cost_value' => $costVal,
                'total_mrp_value' => $mrpVal,
                'total_selling_value' => $sellingVal,
            ];
        }

        return [
            'summary' => [
                'total_items_count' => count($items),
                'total_stock_quantity' => $totalQuantity,
                'total_inventory_cost_value' => round($totalCostValue, 2),
                'total_inventory_mrp_value' => round($totalMrpValue, 2),
                'total_inventory_selling_value' => round($totalSellingValue, 2),
            ],
            'items' => $items,
        ];
    }

    public function getTaxSummary(User $user, array $filters): array
    {
        $invQuery = Invoice::query();

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $invQuery->whereIn('store_id', $userStoreIds);
        }

        if (! empty($filters['store_id'])) {
            $invQuery->where('store_id', (int) $filters['store_id']);
        }

        $this->applyDateBounds($invQuery, $filters, 'created_at');

        $invoiceIds = $invQuery->pluck('id');

        $itemQuery = InvoiceItem::whereIn('invoice_id', $invoiceIds);

        $taxableValue = (float) $itemQuery->sum('taxable_value');
        $cgstAmount = (float) $itemQuery->sum('cgst_amount');
        $sgstAmount = (float) $itemQuery->sum('sgst_amount');
        $igstAmount = (float) $itemQuery->sum('igst_amount');
        $totalTaxAmount = (float) $itemQuery->sum('total_tax_amount');

        // HSN-wise tax breakdown
        $hsnBreakdown = InvoiceItem::whereIn('invoice_id', $invoiceIds)
            ->selectRaw('hsn_code_snapshot, tax_rate_percentage, SUM(quantity) as total_qty, SUM(taxable_value) as taxable_val, SUM(cgst_amount) as cgst_val, SUM(sgst_amount) as sgst_val, SUM(igst_amount) as igst_val, SUM(total_tax_amount) as tax_val')
            ->groupBy('hsn_code_snapshot', 'tax_rate_percentage')
            ->get();

        $hsnItems = [];
        foreach ($hsnBreakdown as $hsn) {
            $hsnItems[] = [
                'hsn_code' => $hsn->hsn_code_snapshot ?? 'N/A',
                'tax_rate_percentage' => (float) $hsn->tax_rate_percentage,
                'total_quantity' => (int) $hsn->total_qty,
                'taxable_value' => round((float) $hsn->taxable_val, 2),
                'cgst_amount' => round((float) $hsn->cgst_val, 2),
                'sgst_amount' => round((float) $hsn->sgst_val, 2),
                'igst_amount' => round((float) $hsn->igst_val, 2),
                'total_tax_amount' => round((float) $hsn->tax_val, 2),
            ];
        }

        return [
            'total_invoices_count' => count($invoiceIds),
            'taxable_value' => round($taxableValue, 2),
            'cgst_amount' => round($cgstAmount, 2),
            'sgst_amount' => round($sgstAmount, 2),
            'igst_amount' => round($igstAmount, 2),
            'total_tax_amount' => round($totalTaxAmount, 2),
            'hsn_breakdown' => $hsnItems,
        ];
    }

    public function getPurchaseSummary(User $user, array $filters): array
    {
        $billQuery = PurchaseBill::query();
        $poQuery = PurchaseOrder::query();
        $returnQuery = PurchaseReturn::query();

        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $billQuery->whereIn('store_id', $userStoreIds);
            $poQuery->whereIn('store_id', $userStoreIds);
            $returnQuery->whereIn('store_id', $userStoreIds);
        }

        if (! empty($filters['store_id'])) {
            $storeId = (int) $filters['store_id'];
            $billQuery->where('store_id', $storeId);
            $poQuery->where('store_id', $storeId);
            $returnQuery->where('store_id', $storeId);
        }

        $this->applyDateBounds($billQuery, $filters, 'bill_date');
        $this->applyDateBounds($poQuery, $filters, 'order_date');
        $this->applyDateBounds($returnQuery, $filters, 'return_date');

        $totalPurchases = (float) $billQuery->sum('grand_total');
        $purchaseBillsCount = (int) $billQuery->count();
        $pendingPosCount = (int) $poQuery->whereIn('status', ['pending', 'draft', 'ordered', 'partial', 'submitted', 'partially_received', 'PENDING', 'DRAFT', 'ORDERED', 'PARTIAL', 'SUBMITTED', 'PARTIALLY_RECEIVED'])->count();
        $purchaseReturnsTotal = (float) $returnQuery->sum('total_return_amount');
        $outstandingSupplierAmount = (float) $billQuery->sum('due_amount');

        return [
            'total_purchases' => round($totalPurchases, 2),
            'purchase_bills_count' => $purchaseBillsCount,
            'pending_pos_count' => $pendingPosCount,
            'purchase_returns_total' => round($purchaseReturnsTotal, 2),
            'outstanding_supplier_amount' => round($outstandingSupplierAmount, 2),
        ];
    }

    public function getCustomerSummary(User $user, array $filters): array
    {
        $invQuery = Invoice::query();
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $invQuery->whereIn('store_id', $userStoreIds);
        }

        if (! empty($filters['store_id'])) {
            $invQuery->where('store_id', (int) $filters['store_id']);
        }
        
        $this->applyDateBounds($invQuery, $filters, 'created_at');

        $totalCustomers = (int) Customer::count();
        $totalPurchaseAmount = (float) (clone $invQuery)->sum('grand_total');
        $totalPaidAmount = (float) (clone $invQuery)->sum('paid_amount');
        $totalOutstandingAmount = max(0.0, round($totalPurchaseAmount - $totalPaidAmount, 2));
        $totalLoyaltyPoints = (int) LoyaltyAccount::sum('available_points');

        return [
            'total_customers' => $totalCustomers,
            'total_purchase_amount' => round($totalPurchaseAmount, 2),
            'total_paid_amount' => round($totalPaidAmount, 2),
            'total_outstanding_amount' => round($totalOutstandingAmount, 2),
            'total_loyalty_points' => $totalLoyaltyPoints,
        ];
    }

    public function getPaymentSummary(User $user, array $filters): array
    {
        $paymentQuery = InvoicePayment::query();
        $invQuery = Invoice::query();

        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $invQuery->whereIn('store_id', $userStoreIds);
        }

        if (! empty($filters['store_id'])) {
            $invQuery->where('store_id', (int) $filters['store_id']);
        }
        
        $this->applyDateBounds($invQuery, $filters, 'created_at');

        $validInvIds = $invQuery->pluck('id');
        $paymentQuery->whereIn('invoice_id', $validInvIds);

        $cash = (float) (clone $paymentQuery)->where('payment_method', 'cash')->sum('amount');
        $card = (float) (clone $paymentQuery)->where('payment_method', 'card')->sum('amount');
        $upi = (float) (clone $paymentQuery)->where('payment_method', 'upi')->sum('amount');
        $bank = (float) (clone $paymentQuery)->whereIn('payment_method', ['bank_transfer', 'cheque', 'net_banking'])->sum('amount');
        $other = (float) (clone $paymentQuery)->whereNotIn('payment_method', ['cash', 'card', 'upi', 'bank_transfer', 'cheque', 'net_banking'])->sum('amount');
        $totalCollections = (float) (clone $paymentQuery)->sum('amount');

        $custPayQuery = CustomerPayment::query();
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $custPayQuery->whereIn('store_id', $userStoreIds);
        }
        if (! empty($filters['store_id'])) {
            $custPayQuery->where('store_id', (int) $filters['store_id']);
        }
        $this->applyDateBounds($custPayQuery, $filters, 'payment_date');

        $directCollections = (float) $custPayQuery->sum('amount');

        $retQuery = ReturnSale::query();
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $retQuery->whereIn('store_id', $userStoreIds);
        }
        if (! empty($filters['store_id'])) {
            $retQuery->where('store_id', (int) $filters['store_id']);
        }
        $this->applyDateBounds($retQuery, $filters, 'created_at');

        $totalRefunds = (float) $retQuery->sum('total_refund_amount');

        return [
            'cash' => round($cash, 2),
            'card' => round($card, 2),
            'upi' => round($upi, 2),
            'bank' => round($bank, 2),
            'other' => round($other, 2),
            'total_collections' => round($totalCollections + $directCollections, 2),
            'total_refunds' => round($totalRefunds, 2),
        ];
    }

    public function getItemWiseSales(User $user, array $filters): array
    {
        $query = DB::table('invoice_items')
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->select(
                'invoice_items.sku_snapshot as sku',
                'invoice_items.article_number_snapshot as article_number',
                'invoice_items.product_name_snapshot as product_name',
                'invoice_items.color_name_snapshot as color',
                'invoice_items.size_number_snapshot as size',
                'invoice_items.hsn_code_snapshot as hsn_code',
                DB::raw('SUM(invoice_items.quantity) as qty_sold'),
                DB::raw('SUM(invoice_items.subtotal + invoice_items.discount_amount) as gross_sales'),
                DB::raw('SUM(invoice_items.discount_amount) as discount'),
                DB::raw('SUM(invoice_items.subtotal) as net_sales'),
                DB::raw('SUM(invoice_items.quantity * invoice_items.cost_price) as cogs')
            )
            ->groupBy('invoice_items.sku_snapshot', 'invoice_items.article_number_snapshot', 'invoice_items.product_name_snapshot', 'invoice_items.color_name_snapshot', 'invoice_items.size_number_snapshot', 'invoice_items.hsn_code_snapshot');

        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id')->toArray();
            $query->whereIn('invoices.store_id', $userStoreIds);
        }

        if (! empty($filters['store_id'])) {
            $query->where('invoices.store_id', (int) $filters['store_id']);
        }
        $query->where('invoices.status', '!=', 'cancelled');
        $this->applyDateBounds($query, $filters, 'invoices.created_at');

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('invoice_items.sku_snapshot', 'LIKE', "%{$search}%")
                  ->orWhere('invoice_items.article_number_snapshot', 'LIKE', "%{$search}%")
                  ->orWhere('invoice_items.product_name_snapshot', 'LIKE', "%{$search}%");
            });
        }

        $sortBy = $filters['sort_by'] ?? 'highest_sales';
        if ($sortBy === 'highest_qty') {
            $query->orderBy(DB::raw('SUM(invoice_items.quantity)'), 'desc');
        } elseif ($sortBy === 'highest_profit') {
            $query->orderBy(DB::raw('SUM(invoice_items.subtotal) - SUM(invoice_items.quantity * invoice_items.cost_price)'), 'desc');
        } elseif ($sortBy === 'lowest_profit') {
            $query->orderBy(DB::raw('SUM(invoice_items.subtotal) - SUM(invoice_items.quantity * invoice_items.cost_price)'), 'asc');
        } elseif ($sortBy === 'highest_margin') {
            $query->orderBy(DB::raw('(SUM(invoice_items.subtotal) - SUM(invoice_items.quantity * invoice_items.cost_price)) / NULLIF(SUM(invoice_items.subtotal), 0)'), 'desc');
        } elseif ($sortBy === 'lowest_margin') {
            $query->orderBy(DB::raw('(SUM(invoice_items.subtotal) - SUM(invoice_items.quantity * invoice_items.cost_price)) / NULLIF(SUM(invoice_items.subtotal), 0)'), 'asc');
        } else {
            $query->orderBy(DB::raw('SUM(invoice_items.subtotal)'), 'desc');
        }

        $rows = $query->get();

        $items = [];
        $totQty = 0;
        $totGross = 0.0;
        $totDiscount = 0.0;
        $totNet = 0.0;
        $totCogs = 0.0;
        $totProfit = 0.0;

        foreach ($rows as $row) {
            $qty = (int) $row->qty_sold;
            $gross = (float) $row->gross_sales;
            $disc = (float) $row->discount;
            $net = (float) $row->net_sales;
            $cg = (float) $row->cogs;
            $profit = round($net - $cg, 2);
            $margin = $net > 0 ? round(($profit / $net) * 100.0, 2) : 0.00;
            $avgPrice = $qty > 0 ? round($net / $qty, 2) : 0.00;

            $totQty += $qty;
            $totGross += $gross;
            $totDiscount += $disc;
            $totNet += $net;
            $totCogs += $cg;
            $totProfit += $profit;

            $items[] = [
                'sku' => $row->sku,
                'article_number' => $row->article_number ?? 'N/A',
                'product_name' => $row->product_name ?? 'N/A',
                'color' => $row->color ?? 'N/A',
                'size' => $row->size ?? 'N/A',
                'hsn_code' => $row->hsn_code ?? 'N/A',
                'qty_sold' => $qty,
                'gross_sales' => round($gross, 2),
                'discount' => round($disc, 2),
                'return_qty' => 0,
                'return_amount' => 0.00,
                'net_sales' => round($net, 2),
                'avg_selling_price' => $avgPrice,
                'cogs' => round($cg, 2),
                'gross_profit' => $profit,
                'margin_pct' => $margin,
                'is_profit' => $profit >= 0,
            ];
        }

        $totMargin = $totNet > 0 ? round(($totProfit / $totNet) * 100.0, 2) : 0.00;

        return [
            'summary' => [
                'total_items_types' => count($items),
                'total_qty_sold' => $totQty,
                'total_gross_sales' => round($totGross, 2),
                'total_discount' => round($totDiscount, 2),
                'total_net_sales' => round($totNet, 2),
                'total_cogs' => round($totCogs, 2),
                'total_gross_profit' => round($totProfit, 2),
                'total_margin_pct' => $totMargin,
                'is_profit' => $totProfit >= 0,
            ],
            'items' => $items,
        ];
    }

    public function getDateWiseSales(User $user, array $filters): array
    {
        $groupBy = $filters['group_by'] ?? 'day';

        if ($groupBy === 'year') {
            $dateExpr = "DATE_FORMAT(created_at, '%Y')";
        } elseif ($groupBy === 'month') {
            $dateExpr = "DATE_FORMAT(created_at, '%Y-%m')";
        } elseif ($groupBy === 'week') {
            $dateExpr = "DATE_FORMAT(created_at, '%Y-W%u')";
        } else {
            $dateExpr = "DATE(created_at)";
        }

        $query = DB::table('invoices')
            ->select(
                DB::raw("{$dateExpr} as period_label"),
                DB::raw('COUNT(id) as invoice_count'),
                DB::raw('SUM(subtotal + discount_amount) as gross_sales'),
                DB::raw('SUM(discount_amount) as discount'),
                DB::raw('SUM(grand_total) as net_sales')
            )
            ->groupBy(DB::raw($dateExpr))
            ->orderBy(DB::raw($dateExpr), 'desc');

        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id')->toArray();
            $query->whereIn('store_id', $userStoreIds);
        }

        if (! empty($filters['store_id'])) {
            $query->where('store_id', (int) $filters['store_id']);
        }
        $query->where('status', '!=', 'cancelled');
        $this->applyDateBounds($query, $filters, 'created_at');

        $rows = $query->get();

        $items = [];
        $totInvoices = 0;
        $totGross = 0.0;
        $totDiscount = 0.0;
        $totNet = 0.0;

        foreach ($rows as $row) {
            $invCount = (int) $row->invoice_count;
            $gross = (float) $row->gross_sales;
            $disc = (float) $row->discount;
            $net = (float) $row->net_sales;

            $totInvoices += $invCount;
            $totGross += $gross;
            $totDiscount += $disc;
            $totNet += $net;

            $items[] = [
                'period_label' => $row->period_label,
                'invoice_count' => $invCount,
                'gross_sales' => round($gross, 2),
                'discount' => round($disc, 2),
                'returns' => 0.00,
                'net_sales' => round($net, 2),
            ];
        }

        return [
            'summary' => [
                'total_periods' => count($items),
                'total_invoices' => $totInvoices,
                'total_gross_sales' => round($totGross, 2),
                'total_discount' => round($totDiscount, 2),
                'total_returns' => 0.00,
                'total_net_sales' => round($totNet, 2),
            ],
            'items' => $items,
        ];
    }

    public function getDateWisePayments(User $user, array $filters): array
    {
        $groupBy = $filters['group_by'] ?? 'day';

        if ($groupBy === 'year') {
            $dateExpr = "DATE_FORMAT(payment_time, '%Y')";
        } elseif ($groupBy === 'month') {
            $dateExpr = "DATE_FORMAT(payment_time, '%Y-%m')";
        } elseif ($groupBy === 'week') {
            $dateExpr = "DATE_FORMAT(payment_time, '%Y-W%u')";
        } else {
            $dateExpr = "DATE(payment_time)";
        }

        $query = DB::table('invoice_payments')
            ->join('invoices', 'invoice_payments.invoice_id', '=', 'invoices.id')
            ->select(
                DB::raw("{$dateExpr} as period_label"),
                DB::raw("SUM(CASE WHEN invoice_payments.payment_method = 'cash' THEN invoice_payments.amount ELSE 0 END) as cash_amt"),
                DB::raw("SUM(CASE WHEN invoice_payments.payment_method = 'card' THEN invoice_payments.amount ELSE 0 END) as card_amt"),
                DB::raw("SUM(CASE WHEN invoice_payments.payment_method = 'upi' THEN invoice_payments.amount ELSE 0 END) as upi_amt"),
                DB::raw("SUM(CASE WHEN invoice_payments.payment_method IN ('bank_transfer', 'cheque', 'net_banking') THEN invoice_payments.amount ELSE 0 END) as bank_amt"),
                DB::raw("SUM(CASE WHEN invoice_payments.payment_method NOT IN ('cash', 'card', 'upi', 'bank_transfer', 'cheque', 'net_banking') THEN invoice_payments.amount ELSE 0 END) as other_amt"),
                DB::raw("SUM(invoice_payments.amount) as total_collected")
            )
            ->groupBy(DB::raw($dateExpr))
            ->orderBy(DB::raw($dateExpr), 'desc');

        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id')->toArray();
            $query->whereIn('invoices.store_id', $userStoreIds);
        }

        if (! empty($filters['store_id'])) {
            $query->where('invoices.store_id', (int) $filters['store_id']);
        }
        $this->applyDateBounds($query, $filters, 'invoice_payments.payment_time');

        $rows = $query->get();

        $items = [];
        $totCash = 0.0;
        $totCard = 0.0;
        $totUpi = 0.0;
        $totBank = 0.0;
        $totOther = 0.0;
        $totCol = 0.0;

        foreach ($rows as $row) {
            $c = (float) $row->cash_amt;
            $cd = (float) $row->card_amt;
            $u = (float) $row->upi_amt;
            $b = (float) $row->bank_amt;
            $o = (float) $row->other_amt;
            $tc = (float) $row->total_collected;

            $totCash += $c;
            $totCard += $cd;
            $totUpi += $u;
            $totBank += $b;
            $totOther += $o;
            $totCol += $tc;

            $items[] = [
                'period_label' => $row->period_label,
                'cash' => round($c, 2),
                'card' => round($cd, 2),
                'upi' => round($u, 2),
                'bank' => round($b, 2),
                'other' => round($o, 2),
                'total_collection' => round($tc, 2),
                'refund' => 0.00,
                'net_collection' => round($tc, 2),
            ];
        }

        return [
            'summary' => [
                'total_cash' => round($totCash, 2),
                'total_card' => round($totCard, 2),
                'total_upi' => round($totUpi, 2),
                'total_bank' => round($totBank, 2),
                'total_other' => round($totOther, 2),
                'total_collection' => round($totCol, 2),
                'total_refund' => 0.00,
                'net_collection' => round($totCol, 2),
            ],
            'items' => $items,
        ];
    }

    public function getDateWiseProfitLoss(User $user, array $filters): array
    {
        $groupBy = $filters['group_by'] ?? 'day';

        if ($groupBy === 'year') {
            $dateExpr = "DATE_FORMAT(invoices.created_at, '%Y')";
        } elseif ($groupBy === 'month') {
            $dateExpr = "DATE_FORMAT(invoices.created_at, '%Y-%m')";
        } elseif ($groupBy === 'week') {
            $dateExpr = "DATE_FORMAT(invoices.created_at, '%Y-W%u')";
        } else {
            $dateExpr = "DATE(invoices.created_at)";
        }

        $query = DB::table('invoices')
            ->join('invoice_items', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->select(
                DB::raw("{$dateExpr} as period_label"),
                DB::raw('SUM(invoice_items.subtotal + invoice_items.discount_amount) as gross_sales'),
                DB::raw('SUM(invoice_items.discount_amount) as discount'),
                DB::raw('SUM(invoice_items.subtotal) as net_sales'),
                DB::raw('SUM(invoice_items.quantity * invoice_items.cost_price) as cogs')
            )
            ->where('invoices.status', '!=', 'cancelled')
            ->groupBy(DB::raw($dateExpr))
            ->orderBy(DB::raw($dateExpr), 'desc');

        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id')->toArray();
            $query->whereIn('invoices.store_id', $userStoreIds);
        }

        if (! empty($filters['store_id'])) {
            $query->where('invoices.store_id', (int) $filters['store_id']);
        }
        $this->applyDateBounds($query, $filters, 'invoices.created_at');

        $rows = $query->get();

        $items = [];
        $totSales = 0.0;
        $totCogs = 0.0;
        $totGrossProfit = 0.0;

        foreach ($rows as $row) {
            $sales = (float) $row->net_sales;
            $cg = (float) $row->cogs;
            $profit = round($sales - $cg, 2);
            $isProfit = $profit >= 0;
            $margin = $sales > 0 ? round(($profit / $sales) * 100.0, 2) : 0.00;

            $totSales += $sales;
            $totCogs += $cg;
            $totGrossProfit += $profit;

            $items[] = [
                'period_label' => $row->period_label,
                'gross_sales' => round((float) $row->gross_sales, 2),
                'discount' => round((float) $row->discount, 2),
                'returns' => 0.00,
                'net_sales' => round($sales, 2),
                'cogs' => round($cg, 2),
                'gross_profit_loss' => $profit,
                'is_profit' => $isProfit,
                'expenses' => 0.00,
                'net_profit_loss' => $profit,
                'net_margin_pct' => $margin,
            ];
        }

        $totMargin = $totSales > 0 ? round(($totGrossProfit / $totSales) * 100.0, 2) : 0.00;

        return [
            'summary' => [
                'total_net_sales' => round($totSales, 2),
                'total_cogs' => round($totCogs, 2),
                'total_gross_profit_loss' => round($totGrossProfit, 2),
                'is_profit' => $totGrossProfit >= 0,
                'total_expenses' => 0.00,
                'total_net_profit_loss' => round($totGrossProfit, 2),
                'total_net_margin_pct' => $totMargin,
            ],
            'items' => $items,
        ];
    }
}
