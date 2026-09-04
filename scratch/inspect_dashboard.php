<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Services\ExecutiveDashboardService;

$user = User::whereHas('roles', fn($q) => $q->where('name', 'Super Admin'))->first() ?? User::first();
$service = app(ExecutiveDashboardService::class);

echo "=== EXECUTIVE KPI SUMMARY ===\n";
echo json_encode($service->getExecutiveKpiSummary([], $user), JSON_PRETTY_PRINT) . "\n\n";

echo "=== INVENTORY KPIS ===\n";
echo json_encode($service->getInventoryKpis([], $user), JSON_PRETTY_PRINT) . "\n\n";

echo "=== POS REGISTER KPIS ===\n";
echo json_encode($service->getPosRegisterKpis([], $user), JSON_PRETTY_PRINT) . "\n\n";

echo "=== CUSTOMER KPIS ===\n";
echo json_encode($service->getCustomerKpis([], $user), JSON_PRETTY_PRINT) . "\n\n";

echo "=== STORE PERFORMANCE ===\n";
echo json_encode($service->getStorePerformanceRankings([], $user)->toArray(), JSON_PRETTY_PRINT) . "\n\n";

echo "=== PRODUCT PERFORMANCE ===\n";
echo json_encode($service->getProductPerformanceRankings([], $user), JSON_PRETTY_PRINT) . "\n\n";

echo "=== SALES TRENDS ===\n";
echo json_encode($service->getSalesTrends([], $user)->toArray(), JSON_PRETTY_PRINT) . "\n\n";

