<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Permission;
use App\Models\Role;
use App\Models\CmsSetting;

echo "--- TABLES --- \n";
$tables = ['roles', 'permissions', 'role_has_permissions', 'stores', 'store_users', 'cms_settings', 'tax_settings', 'audit_logs', 'users'];
foreach ($tables as $t) {
    echo "$t exists: " . (Schema::hasTable($t) ? 'YES' : 'NO') . "\n";
    if (Schema::hasTable($t)) {
        echo "   Columns: " . implode(', ', Schema::getColumnListing($t)) . "\n";
    }
}

echo "\n--- CMS SETTINGS KEYS --- \n";
if (Schema::hasTable('cms_settings')) {
    $keys = CmsSetting::pluck('key_name')->toArray();
    echo "Keys: " . implode(', ', $keys) . "\n";
}

echo "\n--- PERMISSION GROUPS / COUNT --- \n";
if (Schema::hasTable('permissions')) {
    $perms = Permission::pluck('name')->toArray();
    echo "Total permissions: " . count($perms) . "\n";
    echo "Sample permissions: " . implode(', ', array_slice($perms, 0, 25)) . "\n";
}

echo "\n--- ROLES --- \n";
if (Schema::hasTable('roles')) {
    $roles = Role::all(['id', 'name', 'is_system']);
    foreach ($roles as $r) {
        echo "Role: {$r->id} | {$r->name} | System: " . ($r->is_system ? 'YES' : 'NO') . "\n";
    }
}
