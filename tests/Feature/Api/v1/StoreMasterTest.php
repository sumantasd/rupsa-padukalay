<?php

namespace Tests\Feature\Api\v1;

use App\Models\CmsSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreMasterTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $storeManager;
    protected User $unauthorizedUser;

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

        // Store Manager Role
        $managerRole = Role::create(['name' => 'Store Manager', 'guard_name' => 'web']);
        $managerRole->permissions()->attach($permUsers->id);

        $this->storeManager = User::create([
            'name' => 'Store Manager',
            'username' => 'store_mgr',
            'email' => 'manager@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->storeManager->roles()->attach($managerRole->id, ['model_type' => User::class]);

        // Unauthorized User
        $this->unauthorizedUser = User::create([
            'name' => 'Regular Cashier',
            'username' => 'reg_cashier',
            'email' => 'cashier@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_store_request_is_rejected(): void
    {
        $this->getJson('/api/v1/stores')->assertStatus(401);
    }

    public function test_unauthorized_user_without_users_manage_permission_is_denied(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);
        $this->getJson('/api/v1/stores')->assertStatus(403);
    }

    public function test_store_creation_rejected_when_toggle_setting_is_off_default(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Setting is OFF by default ('0')
        $this->assertEquals('0', CmsSetting::getSetting('allow_new_store_creation', '0'));

        $res = $this->postJson('/api/v1/stores', [
            'code' => 'ST-KOL',
            'name' => 'Kolkata Central Store',
            'phone' => '03322110099',
            'email' => 'kolkata@example.com',
            'city' => 'Kolkata',
            'pincode' => '700001',
        ]);

        $res->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'New store creation is currently disabled in system module settings.',
            ]);
    }

    public function test_successful_store_creation_listing_and_viewing_when_toggle_is_on(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Turn setting ON
        CmsSetting::setSetting('allow_new_store_creation', '1');

        $res = $this->postJson('/api/v1/stores', [
            'code' => 'ST-KOL',
            'name' => 'Kolkata Central Store',
            'phone' => '03322110099',
            'email' => 'kolkata@example.com',
            'city' => 'Kolkata',
            'pincode' => '700001',
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'ST-KOL',
                    'name' => 'Kolkata Central Store',
                    'city' => 'Kolkata',
                ],
            ]);

        $storeId = $res->json('data.id');

        $this->getJson("/api/v1/stores/{$storeId}")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['code' => 'ST-KOL']]);

        $this->getJson('/api/v1/stores')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_store_creation_rejected_when_user_lacks_rbac_permission_even_if_toggle_is_on(): void
    {
        // Turn setting ON
        CmsSetting::setSetting('allow_new_store_creation', '1');

        // Act as unauthorized user without users.manage permission
        Sanctum::actingAs($this->unauthorizedUser);

        $this->postJson('/api/v1/stores', [
            'code' => 'ST-DEL',
            'name' => 'Delhi Branch Store',
        ])->assertStatus(403);
    }

    public function test_duplicate_store_code_is_rejected(): void
    {
        Sanctum::actingAs($this->superAdmin);

        Store::create(['code' => 'ST-DEL', 'name' => 'Delhi Store']);

        $this->postJson('/api/v1/stores', [
            'code' => 'ST-DEL',
            'name' => 'Duplicate Delhi Store',
        ])->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_successful_store_update_and_status_toggle(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $store = Store::create(['code' => 'ST-MUM', 'name' => 'Mumbai Store', 'is_active' => true]);

        // Update
        $this->putJson("/api/v1/stores/{$store->id}", [
            'code' => 'ST-MUM',
            'name' => 'Mumbai Main Store',
            'city' => 'Mumbai',
        ])->assertStatus(200)
        ->assertJson(['success' => true, 'data' => ['name' => 'Mumbai Main Store']]);

        // Toggle Status
        $this->patchJson("/api/v1/stores/{$store->id}/status")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['is_active' => false]]);
    }

    public function test_store_user_assignment_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $store = Store::create(['code' => 'ST-KOL', 'name' => 'Kolkata Store']);

        $this->postJson("/api/v1/stores/{$store->id}/users", [
            'user_ids' => [$this->storeManager->id, $this->unauthorizedUser->id],
        ])->assertStatus(200)
        ->assertJsonCount(2, 'data.users');

        $this->assertCount(2, $store->fresh()->users);
    }

    public function test_store_access_isolation_for_non_super_admin(): void
    {
        $store1 = Store::create(['code' => 'ST-001', 'name' => 'Store 1']);
        $store2 = Store::create(['code' => 'ST-002', 'name' => 'Store 2']);

        // Assign only store1 to storeManager
        $this->storeManager->stores()->attach($store1->id);

        Sanctum::actingAs($this->storeManager);

        // Store listing for manager returns only assigned store (1 store)
        $res = $this->getJson('/api/v1/stores')
            ->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals('ST-001', $res->json('data.0.code'));

        // Accessing unassigned store 2 directly returns 403 Forbidden via store.access middleware
        $this->getJson("/api/v1/stores/{$store2->id}")
            ->assertStatus(403);
    }
}
