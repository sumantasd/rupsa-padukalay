<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Role;
use App\Models\User;

$roles = Role::withCount(['users', 'permissions'])->with('permissions')->get();

echo "=== ROLES SUMMARY ===\n";
foreach ($roles as $r) {
    echo "ID: {$r->id} | Name: {$r->name} | Users Count: {$r->users_count} | Permissions Count: {$r->permissions_count}\n";
}

echo "\n=== USERS SUMMARY ===\n";
$users = User::with(['roles', 'stores'])->get();
foreach ($users as $u) {
    $rolesStr = implode(', ', $u->roles->pluck('name')->toArray());
    $storesStr = implode(', ', $u->stores->pluck('name')->toArray());
    echo "User ID: {$u->id} | Username: {$u->username} | Name: {$u->name} | Roles: [{$rolesStr}] | Stores: [{$storesStr}]\n";
}
