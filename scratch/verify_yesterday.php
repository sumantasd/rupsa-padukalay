<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Services\ReportService;
use App\Services\FinancialReportService;

$user = User::first();
$filters = ['date_from' => '2026-09-03', 'date_to' => '2026-09-03'];

$reportService = app(ReportService::class);
$financialService = app(FinancialReportService::class);

$sales = $reportService->getSalesSummary($user, $filters);
$payments = $reportService->getPaymentSummary($user, $filters);

$start = \Carbon\Carbon::parse('2026-09-03 00:00:00');
$end = \Carbon\Carbon::parse('2026-09-03 23:59:59');
$pl = $financialService->calculateFinancialsForStore(1, $start, $end, $filters);

echo "=== YESTERDAY (2026-09-03) RECONCILED FINANCIAL REPORT ===\n";
echo "Gross Sales: ₹" . number_format($sales['total_grand_total'], 2) . "\n";
echo "Invoices: " . $sales['total_sales_count'] . "\n";
echo "Items Sold: " . $sales['total_items_sold'] . "\n";
echo "Discounts: ₹" . number_format($sales['total_discount'], 2) . "\n";
echo "Returns: ₹" . number_format($sales['total_refund_amount'], 2) . "\n";
echo "Net Sales: ₹" . number_format($sales['net_sales'], 2) . "\n";
echo "COGS: ₹" . number_format($pl['cogs'], 2) . "\n";
echo "Gross Profit: ₹" . number_format($pl['gross_profit'], 2) . "\n";
echo "Expenses: ₹" . number_format($pl['operating_expenses'], 2) . "\n";
echo "Net Profit/Loss: ₹" . number_format($pl['net_profit'], 2) . "\n";
echo "Cash Payments: ₹" . number_format($payments['cash'], 2) . "\n";
echo "UPI Payments: ₹" . number_format($payments['upi'], 2) . "\n";
echo "Other Payments: ₹" . number_format($payments['card'] + $payments['bank'] + $payments['other'], 2) . "\n";
echo "Outstanding/Due: ₹" . number_format($sales['total_outstanding_amount'], 2) . "\n";
