<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ReturnSale;
use App\Models\ReturnItem;
use App\Models\Expense;
use App\Models\InvoicePayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

$start = Carbon::parse('2026-09-03')->startOfDay();
$end = Carbon::parse('2026-09-03')->endOfDay();

echo "=========================================\n";
echo "RECONCILIATION TEST FOR 2026-09-03\n";
echo "=========================================\n\n";

// Valid invoices: status != 'cancelled'
$invQuery = Invoice::where('status', '!=', 'cancelled')
    ->whereBetween('created_at', [$start, $end]);

$invoiceCount = (int) (clone $invQuery)->count();
$grossSales = (float) (clone $invQuery)->sum('grand_total');
$subtotal = (float) (clone $invQuery)->sum('subtotal');
$discounts = (float) (clone $invQuery)->sum('discount_amount');
$paidAmount = (float) (clone $invQuery)->sum('paid_amount');
$outstanding = (float) (clone $invQuery)->sum(DB::raw('COALESCE(grand_total, 0) - COALESCE(paid_amount, 0)'));

$validInvoiceIds = (clone $invQuery)->pluck('id');
$totalItemsSold = (int) InvoiceItem::whereIn('invoice_id', $validInvoiceIds)->sum('quantity');

// Returns
$retQuery = ReturnSale::whereBetween('created_at', [$start, $end]);
$returnsRefunds = (float) (clone $retQuery)->sum('total_refund_amount');

// Return Items
$validReturnIds = (clone $retQuery)->pluck('id');
$totalReturnedItems = (int) ReturnItem::whereIn('return_id', $validReturnIds)->sum('quantity');

$netSales = max(0.0, round($grossSales - $discounts - $returnsRefunds, 2));

// COGS
$invoiceCogs = (float) DB::table('invoice_items')
    ->whereIn('invoice_id', $validInvoiceIds)
    ->select(DB::raw('SUM(quantity * cost_price) as cogs'))
    ->value('cogs') ?? 0.00;

$returnedCogs = (float) DB::table('return_items')
    ->join('invoice_items', 'return_items.invoice_item_id', '=', 'invoice_items.id')
    ->whereIn('return_items.return_id', $validReturnIds)
    ->select(DB::raw('SUM(return_items.quantity * invoice_items.cost_price) as ret_cogs'))
    ->value('ret_cogs') ?? 0.00;

$netCogs = max(0.0, round($invoiceCogs - $returnedCogs, 2));
$grossProfit = round($netSales - $netCogs, 2);

// Expenses
$expenses = (float) Expense::whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])->sum('amount');
$netProfit = round($grossProfit - $expenses, 2);

// Payments Breakdown
$paymentQuery = InvoicePayment::whereBetween('created_at', [$start, $end])
    ->whereHas('invoice', fn($q) => $q->where('status', '!=', 'cancelled'));

$cash = (float) (clone $paymentQuery)->where('payment_method', 'cash')->sum('amount');
$upi = (float) (clone $paymentQuery)->where('payment_method', 'upi')->sum('amount');
$card = (float) (clone $paymentQuery)->where('payment_method', 'card')->sum('amount');
$bank = (float) (clone $paymentQuery)->whereIn('payment_method', ['bank_transfer', 'cheque', 'net_banking'])->sum('amount');
$other = (float) (clone $paymentQuery)->whereNotIn('payment_method', ['cash', 'card', 'upi', 'bank_transfer', 'cheque', 'net_banking'])->sum('amount');
$totalCollections = (float) (clone $paymentQuery)->sum('amount');

echo "Invoices Count: $invoiceCount\n";
echo "Gross Sales: ₹$grossSales\n";
echo "Discounts: ₹$discounts\n";
echo "Returns/Refunds: ₹$returnsRefunds\n";
echo "Net Sales: ₹$netSales\n";
echo "Total Items Sold (Qty): $totalItemsSold\n";
echo "Returned Items (Qty): $totalReturnedItems\n";
echo "Invoice COGS: ₹$invoiceCogs\n";
echo "Returned COGS: ₹$returnedCogs\n";
echo "Net COGS: ₹$netCogs\n";
echo "Gross Profit: ₹$grossProfit\n";
echo "Expenses: ₹$expenses\n";
echo "Net Profit: ₹$netProfit\n";
echo "Paid Amount: ₹$paidAmount\n";
echo "Outstanding Balance: ₹$outstanding\n";
echo "Cash Collections: ₹$cash\n";
echo "UPI Collections: ₹$upi\n";
echo "Card Collections: ₹$card\n";
echo "Total Collections: ₹$totalCollections\n";
echo "=========================================\n";
