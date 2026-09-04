<?php

namespace Tests\Feature\Api\v1;

use App\Models\Permission;
use App\Models\Role;
use App\Models\StockLocation;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockLocationMasterTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $storeManager;
    protected User $noPermUser;
    protected Store $store1;
    protected Store $store2;
    protected Warehouse $warehouse1;

    protected function setUp(): void
    {
        parent::setUp();

        $permUsers = Permission::create(['name' => 'users.manage', 'guard_name' => 'web', 'module_group' => 'Users', 'display_name' => 'Manage Users']);

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

        $this->noPermUser = User::create([
            'name' => 'No Perm User',
            'username' => 'noperm_user',
            'email' => 'noperm@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $this->store1 = Store::create(['code' => 'ST-001', 'name' => 'Store 1', 'is_active' => true]);
        $this->store2 = Store::create(['code' => 'ST-002', 'name' => 'Store 2', 'is_active' => true]);
        $this->warehouse1 = Warehouse::create(['code' => 'WH-001', 'name' => 'Warehouse 1', 'is_active' => true]);

        $this->storeManager->stores()->attach($this->store1->id);
        $this->store1->warehouses()->attach($this->warehouse1->id);
    }

    public function test_1_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/stock-locations')->assertStatus(401);
    }

    public function test_2_unauthorized_user_is_denied(): void
    {
        Sanctum::actingAs($this->noPermUser);
        $this->getJson('/api/v1/stock-locations')->assertStatus(403);
    }

    public function test_3_stock_location_creation_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->postJson('/api/v1/stock-locations', [
            'store_id' => $this->store1->id,
            'code' => 'RACK-A1',
            'name' => 'Main Display Rack A1',
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => 'RACK-A1',
                    'name' => 'Main Display Rack A1',
                    'store_id' => $this->store1->id,
                ],
            ]);
    }

    public function test_4_valid_store_warehouse_relationship_required(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Missing both store_id and warehouse_id
        $this->postJson('/api/v1/stock-locations', [
            'code' => 'RACK-X',
            'name' => 'Orphan Rack',
        ])->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_5_invalid_warehouse_store_relationship_rejected(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $inactiveStore = Store::create(['code' => 'ST-OFF', 'name' => 'Closed Store', 'is_active' => false]);

        $this->postJson('/api/v1/stock-locations', [
            'store_id' => $inactiveStore->id,
            'code' => 'RACK-Y',
            'name' => 'Rack Y',
        ])->assertStatus(422)->assertJsonValidationErrors(['store_id']);
    }

    public function test_6_duplicate_location_within_same_scope_is_rejected(): void
    {
        Sanctum::actingAs($this->superAdmin);

        StockLocation::create([
            'store_id' => $this->store1->id,
            'code' => 'RACK-A1',
            'name' => 'Rack A1',
        ]);

        // Duplicate in same store
        $this->postJson('/api/v1/stock-locations', [
            'store_id' => $this->store1->id,
            'code' => 'RACK-A1',
            'name' => 'Duplicate Rack A1',
        ])->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_7_listing_and_filtering_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        StockLocation::create(['store_id' => $this->store1->id, 'code' => 'SHELF-01', 'name' => 'Shelf 1']);
        StockLocation::create(['store_id' => $this->store2->id, 'code' => 'SHELF-02', 'name' => 'Shelf 2']);

        $this->getJson("/api/v1/stock-locations?store_id={$this->store1->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_8_update_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $loc = StockLocation::create(['store_id' => $this->store1->id, 'code' => 'FLOOR-1', 'name' => 'Section 1']);

        $this->putJson("/api/v1/stock-locations/{$loc->id}", [
            'store_id' => $this->store1->id,
            'code' => 'FLOOR-1',
            'name' => 'Section 1 Updated',
        ])->assertStatus(200)
        ->assertJson(['success' => true, 'data' => ['name' => 'Section 1 Updated']]);
    }

    public function test_9_status_toggle_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $loc = StockLocation::create(['store_id' => $this->store1->id, 'code' => 'FLOOR-2', 'name' => 'Section 2']);

        $this->patchJson("/api/v1/stock-locations/{$loc->id}/status")
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_10_non_super_admin_cannot_access_another_stores_locations(): void
    {
        $loc1 = StockLocation::create(['store_id' => $this->store1->id, 'code' => 'RACK-1', 'name' => 'Store 1 Rack']);
        $loc2 = StockLocation::create(['store_id' => $this->store2->id, 'code' => 'RACK-2', 'name' => 'Store 2 Rack']);

        Sanctum::actingAs($this->storeManager);

        // Store 1 -> OK
        $this->getJson("/api/v1/stock-locations/{$loc1->id}")->assertStatus(200);

        // Store 2 -> 403 Forbidden
        $this->getJson("/api/v1/stock-locations/{$loc2->id}")->assertStatus(403);
    }

    public function test_11_super_admin_can_access_authorized_locations(): void
    {
        $loc2 = StockLocation::create(['store_id' => $this->store2->id, 'code' => 'RACK-2', 'name' => 'Store 2 Rack']);

        Sanctum::actingAs($this->superAdmin);

        $this->getJson("/api/v1/stock-locations/{$loc2->id}")->assertStatus(200);
    }

    public function test_12_standardized_api_responses_are_returned(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->getJson('/api/v1/stock-locations')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);
    }
}
