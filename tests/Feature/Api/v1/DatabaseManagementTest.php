<?php

namespace Tests\Feature\Api\v1;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\DatabaseBackup;
use App\Models\DatabaseResetLog;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Permissions & Roles
        $permManage = Permission::firstOrCreate(['name' => 'database.manage'], ['guard_name' => 'web', 'module_group' => 'System', 'display_name' => 'Database Management']);
        $permSettings = Permission::firstOrCreate(['name' => 'system.settings'], ['guard_name' => 'web', 'module_group' => 'System', 'display_name' => 'System Settings']);

        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin'], ['guard_name' => 'web']);
        $superAdminRole->permissions()->syncWithoutDetaching([$permManage->id, $permSettings->id]);

        $cashierRole = Role::firstOrCreate(['name' => 'POS Cashier'], ['guard_name' => 'web']);

        // 2. Admin User
        $this->adminUser = User::create([
            'name' => 'DB Super Admin',
            'username' => 'db_admin_' . rand(1000, 9999),
            'email' => 'db_admin_' . rand(1000, 9999) . '@rupsa.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($superAdminRole->id, ['model_type' => User::class]);

        // 3. Regular Cashier User
        $this->regularUser = User::create([
            'name' => 'Regular Cashier',
            'username' => 'cashier_' . rand(1000, 9999),
            'email' => 'cashier_' . rand(1000, 9999) . '@rupsa.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->regularUser->roles()->attach($cashierRole->id, ['model_type' => User::class]);

        $this->store = Store::create([
            'code' => 'STR-001',
            'name' => 'RUPSA PADUKALAYA - Main Outlet',
            'phone' => '+91 9735125112',
            'address' => 'DHANTALA BAZAR, DHANTALA, NADIA - 741202, WEST BENGAL, INDIA',
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_database_management_endpoints()
    {
        $this->getJson('/api/v1/database/backups')->assertStatus(401);
        $this->postJson('/api/v1/database/backups')->assertStatus(401);
        $this->getJson('/api/v1/database/reset/categories')->assertStatus(401);
        $this->postJson('/api/v1/database/reset', [])->assertStatus(401);
    }

    public function test_unauthorized_user_without_permission_cannot_access_database_management()
    {
        $this->actingAs($this->regularUser, 'sanctum')
            ->getJson('/api/v1/database/backups')
            ->assertStatus(403);

        $this->actingAs($this->regularUser, 'sanctum')
            ->postJson('/api/v1/database/backups')
            ->assertStatus(403);

        $this->actingAs($this->regularUser, 'sanctum')
            ->postJson('/api/v1/database/reset', [
                'reset_type' => 'demo_data_reset',
                'categories' => ['sales'],
                'confirmation_text' => 'CLEAR DATABASE',
            ])->assertStatus(403);
    }

    public function test_authorized_admin_can_create_database_backup()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/database/backups');

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'completed');

        $backupId = $response->json('data.id');
        $filename = $response->json('data.filename');

        $this->assertDatabaseHas('database_backups', ['id' => $backupId, 'status' => 'completed']);
        $this->assertGreaterThan(0, File::size(storage_path('app/private/backups/' . $filename)));
    }

    public function test_authorized_admin_can_list_and_download_database_backup()
    {
        // 1. Create Backup
        $createRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/database/backups');

        $backupId = $createRes->json('data.id');

        // 2. List Backups
        $listRes = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/database/backups');

        $listRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // 3. Download Backup
        $downloadRes = $this->actingAs($this->adminUser, 'sanctum')
            ->get("/api/v1/database/backups/{$backupId}/download");

        $downloadRes->assertStatus(200);
        $downloadRes->assertHeader('content-type', 'application/x-sql');
    }

    public function test_reset_requires_exact_confirmation_phrase()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/database/reset', [
                'reset_type' => 'demo_data_reset',
                'categories' => ['sales'],
                'confirmation_text' => 'WRONG PHRASE',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_demo_data_reset_executes_safely_and_creates_backup_and_audit()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/database/reset', [
                'reset_type' => 'demo_data_reset',
                'categories' => ['sales', 'inventory', 'expenses'],
                'confirmation_text' => 'CLEAR DATABASE',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'success');

        // Verify protected data preserved
        $this->assertDatabaseHas('users', ['id' => $this->adminUser->id]);
        $this->assertDatabaseHas('stores', ['id' => $this->store->id]);

        // Verify database reset audit log created
        $this->assertDatabaseHas('database_reset_logs', [
            'user_id' => $this->adminUser->id,
            'reset_type' => 'demo_data_reset',
            'status' => 'success',
        ]);
    }
}
