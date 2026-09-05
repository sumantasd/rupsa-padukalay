<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Route;

$user = User::where('username', 'rupsa_admin')->first();
auth()->login($user);

echo "=== TESTING NEW BACKEND API ENDPOINTS ===\n";

$endpoints = [
    ['GET', '/api/v1/roles'],
    ['GET', '/api/v1/permissions'],
    ['GET', '/api/v1/store-access'],
    ['GET', '/api/v1/settings/invoices'],
    ['GET', '/api/v1/payment-methods/active'],
    ['GET', '/api/v1/settings/payment-methods'],
    ['GET', '/api/v1/settings/pos'],
    ['GET', '/api/v1/settings/number-series'],
    ['GET', '/api/v1/settings/general'],
];

foreach ($endpoints as [$method, $uri]) {
    $request = \Illuminate\Http\Request::create($uri, $method);
    $request->setUserResolver(fn() => $user);
    $response = $app->handle($request);
    echo "[{$method}] {$uri} => Status: " . $response->getStatusCode() . "\n";
    if ($response->getStatusCode() !== 200) {
        echo "   Response: " . substr($response->getContent(), 0, 150) . "\n";
    }
}
