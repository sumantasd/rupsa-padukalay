<?php

namespace Tests\Feature\Api\v1;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarehouseMasterTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $storeManager;
    protected User $noPermUser;
    protected User $deactivatedUser;
    protected Store $store1;
    protected Store $store2;

    protected function setUp(): void
    {
        parent::setUp();

        $permUsers = Permission::create(['name' => 'users.manage', 'guard_name' => 'web', 'module_group' => 'Users', 'display_name' => 'Manage Users']);

        // Super Admin Role
        $superAdminRole = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdminRole->permissions()->attach($permUsers->id);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->superAdmin->roles()->attach($superAdminRole->id, ['model_type' => User::class]);

        // Manager Role
        $managerRole = Role::create(['name' => 'Warehouse Manager', 'guard_name' => 'web']);
        $managerRole->permissions()->attach($permUsers->id);

        $this->storeManager = User::create([
            'name' => 'Store Manager',
            'username' => 'store_mgr',
            'email' => 'manager@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->storeManager->roles()->attach($managerRole->id, ['model_type' => User::class]);

        // No Perm User
        $this->noPermUser = User::create([
            'name' => 'No Perm User',
            'username' => 'noperm_user',
            'email' => 'noperm@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        // Deactivated User
        $this->deactivatedUser = User::create([
            'name' => 'Deactivated Manager',
            'username' => 'deact_mgr',
            'email' => 'deact@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => false,
        ]);

        $this->store1 = Store::create(['code' => 'ST-001', 'name' => 'Store 1', 'is_active' => true]);
        $this->store2 = Store::create(['code' => 'ST-002', 'name' => 'Store 2', 'is_active' => true]);

        // Assign store1 to storeManager
        $this->storeManager->stores()->attach($this->store1->id);
    }

    public function test_1_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/warehouses')->assertStatus(401);
    }

    public function test_2_unauthorized_user_is_denied(): void
    {
        Sanctum::actingAs($this->noPermUser);
        $this->getJson('/api/v1/warehouses')->assertStatus(403);
    }

    public function test_3_warehouse_creation_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->postJson('/api/v1/warehouses', [
            'code' => 'WH-KOL-01',
            'name' => 'Central Kolkata Warehouse',
            'city' => 'Kolkata',
            'pincode' => '700001',
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'WH-KOL-01',
                    'name' => 'Central Kolkata Warehouse',
                ],
            ]);
    }

    public function test_4_duplicate_warehouse_code_is_rejected(): void
    {
        Sanctum::actingAs($this->superAdmin);

        Warehouse::create(['code' => 'WH-01', 'name' => 'Warehouse 1']);

        $this->postJson('/api/v1/warehouses', [
            'code' => 'WH-01',
            'name' => 'Duplicate Warehouse',
        ])->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_5_warehouse_listing_and_updating_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $wh = Warehouse::create(['code' => 'WH-02', 'name' => 'Warehouse 2']);

        $this->getJson('/api/v1/warehouses')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->putJson("/api/v1/warehouses/{$wh->id}", [
            'code' => 'WH-02',
            'name' => 'Warehouse 2 Updated',
        ])->assertStatus(200)
        ->assertJson(['success' => true, 'data' => ['name' => 'Warehouse 2 Updated']]);
    }

    public function test_6_warehouse_status_toggle_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $wh = Warehouse::create(['code' => 'WH-03', 'name' => 'Warehouse 3', 'is_active' => true]);

        $this->patchJson("/api/v1/warehouses/{$wh->id}/status")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['is_active' => false]]);
    }

    public function test_7_warehouse_store_assignment_and_duplicate_prevention(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $wh1 = Warehouse::create(['code' => 'WH-A', 'name' => 'Warehouse A']);
        $wh2 = Warehouse::create(['code' => 'WH-B', 'name' => 'Warehouse B']);

        $res = $this->postJson("/api/v1/stores/{$this->store1->id}/warehouses", [
            'warehouse_ids' => [$wh1->id, $wh2->id, $wh1->id], // Includes duplicate WH ID
        ]);

        $res->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $this->assertCount(2, $this->store1->fresh()->warehouses);
    }

    public function test_8_non_super_admin_cannot_access_unauthorized_store_warehouse(): void
    {
        $wh1 = Warehouse::create(['code' => 'WH-S1', 'name' => 'Warehouse Store 1']);
        $wh2 = Warehouse::create(['code' => 'WH-S2', 'name' => 'Warehouse Store 2']);

        $this->store1->warehouses()->attach($wh1->id);
        $this->store2->warehouses()->attach($wh2->id);

        Sanctum::actingAs($this->storeManager);

        // Manager can view authorized WH-1
        $this->getJson("/api/v1/warehouses/{$wh1->id}")
            ->assertStatus(200);

        // Manager cannot view unauthorized WH-2 (belongs to Store 2)
        $this->getJson("/api/v1/warehouses/{$wh2->id}")
            ->assertStatus(403);
    }

    public function test_9_super_admin_can_access_all_authorized_warehouses(): void
    {
        $wh2 = Warehouse::create(['code' => 'WH-S2', 'name' => 'Warehouse Store 2']);
        $this->store2->warehouses()->attach($wh2->id);

        Sanctum::actingAs($this->superAdmin);

        $this->getJson("/api/v1/warehouses/{$wh2->id}")
            ->assertStatus(200);
    }

    public function test_10_deactivated_user_access_is_rejected(): void
    {
        Sanctum::actingAs($this->deactivatedUser);

        $this->getJson('/api/v1/warehouses')
            ->assertStatus(403);
    }
}
