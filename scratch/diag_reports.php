<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Customer;
use App\Models\PurchaseBill;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\CustomerPayment;
use App\Services\ReportService;

echo "=== USER CHECK ===\n";
$user = User::whereHas('roles', fn($q) => $q->where('name', 'Super Admin'))->first() ?? User::first();
echo "Testing as User: {$user->name} (ID: {$user->id})\n\n";

echo "=== 1. PURCHASE REPORT CHECK ===\n";
try {
    $service = app(ReportService::class);
    $res = $service->getPurchaseSummary($user, []);
    echo "Purchase Summary Response: " . json_encode($res, JSON_PRETTY_PRINT) . "\n";
} catch (\Throwable $e) {
    echo "Purchase Summary ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}

echo "\nChecking Purchase Bills:\n";
echo "PurchaseBill Count: " . PurchaseBill::count() . "\n";
echo "PurchaseOrder Count: " . PurchaseOrder::count() . "\n";
echo "PurchaseReturn Count: " . PurchaseReturn::count() . "\n";

echo "\n=== 2. CUSTOMER REPORT CHECK ===\n";
try {
    $res = $service->getCustomerSummary($user, []);
    echo "Customer Summary Response: " . json_encode($res, JSON_PRETTY_PRINT) . "\n";
} catch (\Throwable $e) {
    echo "Customer Summary ERROR: " . $e->getMessage() . "\n";
}

echo "\nCustomers List Sample:\n";
$customers = Customer::take(5)->get();
foreach ($customers as $c) {
    echo "Customer: {$c->name} (ID: {$c->id}, Mobile: {$c->mobile_number})\n";
    echo " - Invoices count: " . $c->invoices()->count() . "\n";
    echo " - CustomerPayments count: " . $c->payments()->count() . "\n";
}

echo "\n=== 3. PAYMENT COLLECTION CHECK ===\n";
echo "InvoicePayments count: " . InvoicePayment::count() . "\n";
echo "CustomerPayments count: " . CustomerPayment::count() . "\n";

