<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\InvoicePayment;
use App\Models\CustomerPayment;

echo "=== INVOICE PAYMENTS ===\n";
$invPayments = InvoicePayment::with(['invoice.customer', 'invoice.store'])->get();
echo "Count: " . $invPayments->count() . "\n";
foreach ($invPayments as $ip) {
    $method = is_object($ip->payment_method) ? $ip->payment_method->value : $ip->payment_method;
    echo "ID: {$ip->id}, Invoice: " . ($ip->invoice?->invoice_number ?? 'N/A') . ", Method: {$method}, Amount: {$ip->amount}, Customer: " . ($ip->invoice?->customer?->name ?? 'Walk-in') . ", Time: {$ip->payment_time}\n";
}

echo "\n=== CUSTOMER PAYMENTS ===\n";
$custPayments = CustomerPayment::with(['customer', 'invoice', 'store'])->get();
echo "Count: " . $custPayments->count() . "\n";
foreach ($custPayments as $cp) {
    echo "ID: {$cp->id}, Payment #: {$cp->payment_number}, Method: {$cp->payment_method}, Amount: {$cp->amount}, Customer: " . ($cp->customer?->name ?? 'N/A') . ", Date: {$cp->payment_date}\n";
}

