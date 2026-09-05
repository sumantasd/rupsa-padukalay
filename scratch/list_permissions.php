<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Permission;

$permissions = Permission::all(['id', 'name', 'module_group', 'display_name']);

echo "=== PERMISSIONS LIST (" . $permissions->count() . ") ===\n";
$grouped = $permissions->groupBy('module_group');

foreach ($grouped as $group => $items) {
    echo "\nGroup: [" . ($group ?: 'Unassigned') . "]\n";
    foreach ($items as $item) {
        echo "  - {$item->name} ({$item->display_name})\n";
    }
}
