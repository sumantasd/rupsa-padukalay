<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\StockMovement;
use App\Services\FinancialReportService;
use App\Services\ImageUrlService;

echo "===============================================\n";
echo "1. FINANCIAL CALCULATIONS AUDIT & RECONCILIATION\n";
echo "===============================================\n";

$invoices = Invoice::with('items')->get();
echo "Total Invoices in DB: " . $invoices->count() . "\n";

$sumGross = 0;
$sumDiscount = 0;
$sumTax = 0;
$sumNet = 0;

foreach ($invoices as $inv) {
    $typeStr = is_object($inv->sale_type) ? $inv->sale_type->value : $inv->sale_type;
    $statusStr = is_object($inv->status) ? $inv->status->value : $inv->status;
    echo "Invoice #{$inv->invoice_number} | Type: {$typeStr} | Grand Total: ₹{$inv->grand_total} | Status: {$statusStr}\n";
    $sumGross += $inv->subtotal;
    $sumDiscount += $inv->discount_amount;
    $sumTax += $inv->total_tax;
    $sumNet += $inv->grand_total;
}

echo "\nAGGREGATED INVOICES:\n";
echo "Gross: ₹{$sumGross} | Discount: ₹{$sumDiscount} | Tax: ₹{$sumTax} | Net: ₹{$sumNet}\n";

$financialService = app(FinancialReportService::class);
$user = \App\Models\User::where('username', 'rupsa_admin')->first();
$consolidated = $financialService->getConsolidatedSales([], $user);

echo "\nFINANCIAL SERVICE CONSOLIDATED SALES REPORT:\n";
echo "Gross Sales: ₹" . ($consolidated['summary']['gross_sales'] ?? 0) . "\n";
echo "Net Sales: ₹" . ($consolidated['summary']['net_sales'] ?? 0) . "\n";
echo "Stores Count: " . ($consolidated['summary']['total_stores'] ?? 0) . "\n";

echo "\n===============================================\n";
echo "2. IMAGE URL FORMATTING & STORAGE PATH AUDIT\n";
echo "===============================================\n";

$pathsToTest = [
    'company/logo.png',
    'products/shoe.jpg',
    '/storage/company/logo.png',
    'http://localhost:8000/storage/company/logo.png',
    null,
];

foreach ($pathsToTest as $p) {
    $formatted = ImageUrlService::format($p);
    echo "Input: " . var_export($p, true) . " => Formatted: " . var_export($formatted, true) . "\n";
    if (str_contains($formatted ?? '', '/storage/storage/')) {
        echo "❌ ERROR: Duplicate /storage/storage/ detected!\n";
    } else {
        echo "✓ CLEAN URL\n";
    }
}
