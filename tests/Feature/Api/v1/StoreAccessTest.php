<?php

namespace Tests\Feature\Api\v1;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Register a temporary test route for testing Store Access middleware
        Route::get('/api/v1/test-store-protected', function () {
            return response()->json([
                'success' => true,
                'message' => 'Store Access Granted',
            ]);
        })->middleware(['auth:sanctum', 'store.access']);
    }

    public function test_user_with_access_to_store_a_can_access_store_a_resources(): void
    {
        $storeA = Store::create([
            'code' => 'ST-001',
            'name' => 'Main Store Kolkata',
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Store Cashier',
            'username' => 'store_cashier',
            'email' => 'cashier_a@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $user->stores()->attach($storeA->id, ['is_default' => true]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/test-store-protected?store_id={$storeA->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Store Access Granted',
            ]);
    }

    public function test_user_without_access_to_store_a_is_denied(): void
    {
        $storeA = Store::create([
            'code' => 'ST-001',
            'name' => 'Main Store Kolkata',
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Unassigned User',
            'username' => 'unassigned_user',
            'email' => 'unassigned@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/test-store-protected?store_id={$storeA->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => "Forbidden: You are not authorized to access Store ID {$storeA->id}.",
            ]);
    }

    public function test_user_assigned_to_store_a_cannot_access_store_b(): void
    {
        $storeA = Store::create(['code' => 'ST-001', 'name' => 'Store A', 'is_active' => true]);
        $storeB = Store::create(['code' => 'ST-002', 'name' => 'Store B', 'is_active' => true]);

        $user = User::create([
            'name' => 'Store A Staff',
            'username' => 'staff_a',
            'email' => 'staff_a@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $user->stores()->attach($storeA->id, ['is_default' => true]);

        Sanctum::actingAs($user);

        // Access Store A -> Succeeds
        $this->getJson("/api/v1/test-store-protected?store_id={$storeA->id}")
            ->assertStatus(200);

        // Access Store B -> Denied
        $this->getJson("/api/v1/test-store-protected?store_id={$storeB->id}")
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => "Forbidden: You are not authorized to access Store ID {$storeB->id}.",
            ]);
    }

    public function test_user_assigned_to_multiple_stores_can_access_only_assigned_stores(): void
    {
        $storeA = Store::create(['code' => 'ST-001', 'name' => 'Store A', 'is_active' => true]);
        $storeB = Store::create(['code' => 'ST-002', 'name' => 'Store B', 'is_active' => true]);
        $storeC = Store::create(['code' => 'ST-003', 'name' => 'Store C', 'is_active' => true]);

        $user = User::create([
            'name' => 'Multi Store Manager',
            'username' => 'multi_mgr',
            'email' => 'multi@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $user->stores()->attach([$storeA->id, $storeB->id]);

        Sanctum::actingAs($user);

        // Store A -> OK
        $this->getJson("/api/v1/test-store-protected?store_id={$storeA->id}")->assertStatus(200);

        // Store B -> OK
        $this->getJson("/api/v1/test-store-protected?store_id={$storeB->id}")->assertStatus(200);

        // Store C -> Denied
        $this->getJson("/api/v1/test-store-protected?store_id={$storeC->id}")->assertStatus(403);
    }

    public function test_super_admin_can_access_all_stores(): void
    {
        $storeA = Store::create(['code' => 'ST-001', 'name' => 'Store A', 'is_active' => true]);
        $storeB = Store::create(['code' => 'ST-002', 'name' => 'Store B', 'is_active' => true]);

        $role = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);

        $user = User::create([
            'name' => 'Super Admin User',
            'username' => 'admin_user',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $user->roles()->attach($role->id, ['model_type' => User::class]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/test-store-protected?store_id={$storeA->id}")->assertStatus(200);
        $this->getJson("/api/v1/test-store-protected?store_id={$storeB->id}")->assertStatus(200);
    }

    public function test_deactivated_user_cannot_access_store_protected_resources(): void
    {
        $storeA = Store::create(['code' => 'ST-001', 'name' => 'Store A', 'is_active' => true]);

        $user = User::create([
            'name' => 'Deactivated Staff',
            'username' => 'inactive_staff',
            'email' => 'inactive@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => false,
        ]);

        $user->stores()->attach($storeA->id);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/test-store-protected?store_id={$storeA->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Forbidden: Account is deactivated.',
            ]);
    }

    public function test_unauthenticated_user_cannot_bypass_store_access_protection(): void
    {
        $storeA = Store::create(['code' => 'ST-001', 'name' => 'Store A', 'is_active' => true]);

        $response = $this->getJson("/api/v1/test-store-protected?store_id={$storeA->id}");

        $response->assertStatus(401);
    }

    public function test_middleware_returns_standardized_api_error_response(): void
    {
        $storeA = Store::create(['code' => 'ST-001', 'name' => 'Store A', 'is_active' => true]);

        $user = User::create([
            'name' => 'Unauthorized User',
            'username' => 'unauth_user',
            'email' => 'unauth@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/test-store-protected?store_id={$storeA->id}");

        $response->assertStatus(403)
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ]);
    }
}
