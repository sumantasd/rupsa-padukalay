<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\InvoicePayment;
use App\Services\ReportService;
use App\Http\Resources\CustomerResource;

$user = User::whereHas('roles', fn($q) => $q->where('name', 'Super Admin'))->first() ?? User::first();

echo "=== 1. PURCHASE REPORT ===\n";
$service = app(ReportService::class);
$summary = $service->getPurchaseSummary($user, []);
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n\n";

echo "=== 2. CUSTOMER REPORT ===\n";
$custSummary = $service->getCustomerSummary($user, []);
echo "Summary: " . json_encode($custSummary, JSON_PRETTY_PRINT) . "\n";

$customers = Customer::query()
    ->with(['loyaltyAccount'])
    ->withCount(['invoices as total_purchases_count' => fn($q) => $q->whereNotIn('status', ['cancelled', 'CANCELLED'])])
    ->withSum(['invoices as total_spent_amount' => fn($q) => $q->whereNotIn('status', ['cancelled', 'CANCELLED'])], 'grand_total')
    ->withSum(['invoices as invoice_paid_amount' => fn($q) => $q->whereNotIn('status', ['cancelled', 'CANCELLED'])], 'paid_amount')
    ->withSum('payments as direct_paid_amount', 'amount')
    ->get();

$customerArray = CustomerResource::collection($customers)->resolve();
echo "Customer Rows:\n" . json_encode($customerArray, JSON_PRETTY_PRINT) . "\n\n";

echo "=== 3. PAYMENT COLLECTIONS LIST ===\n";
$paymentController = app(\App\Http\Controllers\Api\v1\PaymentCollectionController::class);
$req = \Illuminate\Http\Request::create('/api/v1/payments/collections', 'GET');
$req->setUserResolver(fn() => $user);
$res = $paymentController->index($req);
echo json_encode($res->getData(true), JSON_PRETTY_PRINT) . "\n";

