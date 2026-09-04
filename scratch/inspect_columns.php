<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

function showColumns($table) {
    echo "=== COLUMNS FOR {$table} ===\n";
    if (!Schema::hasTable($table)) {
        echo "Table {$table} does NOT exist!\n\n";
        return;
    }
    $columns = Schema::getColumnListing($table);
    echo implode(', ', $columns) . "\n\n";
}

showColumns('purchase_returns');
showColumns('purchase_bills');
showColumns('purchase_orders');
showColumns('goods_receives');
showColumns('customers');
showColumns('customer_payments');
showColumns('invoice_payments');
showColumns('invoices');
showColumns('return_sales');
showColumns('loyalty_accounts');
showColumns('loyalty_transactions');

