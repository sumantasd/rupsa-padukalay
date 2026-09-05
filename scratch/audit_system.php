<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Role;
use App\Models\Store;
use App\Models\Product;
use App\Models\CmsSetting;

echo "===============================================\n";
echo "1. DATABASE ROW COUNTS & STRUCTURE\n";
echo "===============================================\n";

$tables = Schema::getTableListing();
foreach ($tables as $table) {
    if (in_array($table, ['migrations', 'failed_jobs', 'personal_access_tokens'])) continue;
    $count = DB::table($table)->count();
    echo str_pad($table, 30) . ": {$count} rows\n";
}

echo "\n===============================================\n";
echo "2. STORES AUDIT\n";
echo "===============================================\n";

$stores = Store::withTrashed()->get();
foreach ($stores as $s) {
    echo "ID: {$s->id} | Code: {$s->code} | Name: {$s->name} | Active: " . ($s->is_active ? 'YES' : 'NO') . " | Deleted: " . ($s->deleted_at ? $s->deleted_at : 'NO') . "\n";
}

echo "\n===============================================\n";
echo "3. USERS AUDIT\n";
echo "===============================================\n";

$users = User::withTrashed()->with(['roles', 'stores'])->get();
foreach ($users as $u) {
    $roles = implode(', ', $u->roles->pluck('name')->toArray());
    $stores = implode(', ', $u->stores->pluck('name')->toArray());
    echo "ID: {$u->id} | User: {$u->username} | Name: {$u->name} | Protected: " . ($u->is_protected ? 'YES' : 'NO') . " | Active: " . ($u->is_active ? 'YES' : 'NO') . " | Roles: [{$roles}] | Stores: [{$stores}]\n";
}

echo "\n===============================================\n";
echo "4. CMS SETTINGS AUDIT\n";
echo "===============================================\n";

$settings = CmsSetting::all(['id', 'key_name']);
foreach ($settings as $st) {
    echo "ID: {$st->id} | Key: {$st->key_name}\n";
}
