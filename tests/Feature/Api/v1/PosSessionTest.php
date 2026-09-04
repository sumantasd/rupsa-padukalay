<?php

namespace Tests\Feature\Api\v1;

use App\Models\Permission;
use App\Models\PosSession;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosSessionTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $storeManager;
    protected User $noPermUser;
    protected Store $store1;
    protected Store $store2;

    protected function setUp(): void
    {
        parent::setUp();

        $permView = Permission::create(['name' => 'products.view', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Products']);
        $permCreate = Permission::create(['name' => 'products.create', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Create Products']);
        $permEdit = Permission::create(['name' => 'products.edit', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Edit Products']);

        $superAdminRole = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdminRole->permissions()->attach([$permView->id, $permCreate->id, $permEdit->id]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->superAdmin->roles()->attach($superAdminRole->id, ['model_type' => User::class]);

        $managerRole = Role::create(['name' => 'Store Manager', 'guard_name' => 'web']);
        $managerRole->permissions()->attach([$permView->id, $permCreate->id, $permEdit->id]);

        $this->store1 = Store::create(['code' => 'ST-001', 'name' => 'Main Store', 'is_active' => true]);
        $this->store2 = Store::create(['code' => 'ST-002', 'name' => 'Branch Store', 'is_active' => true]);

        $this->storeManager = User::create([
            'name' => 'Store Manager',
            'username' => 'store_manager',
            'email' => 'manager@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->storeManager->roles()->attach($managerRole->id, ['model_type' => User::class]);
        $this->storeManager->stores()->attach($this->store1->id);

        $this->noPermUser = User::create([
            'name' => 'No Perm User',
            'username' => 'noperm_user',
            'email' => 'noperm@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
    }

    public function test_1_unauthenticated_user_rejected(): void
    {
        $this->postJson('/api/v1/pos/sessions', [])->assertStatus(401);
    }

    public function test_2_user_without_pos_session_permission_rejected(): void
    {
        Sanctum::actingAs($this->noPermUser);
        $this->postJson('/api/v1/pos/sessions', ['store_id' => $this->store1->id, 'opening_cash' => 1000.00])->assertStatus(403);
    }

    public function test_3_authorized_user_can_open_pos_session(): void
    {
        Sanctum::actingAs($this->storeManager);

        $res = $this->postJson('/api/v1/pos/sessions', [
            'store_id' => $this->store1->id,
            'opening_cash' => 1500.00,
            'notes' => 'Morning shift open',
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'store_id' => $this->store1->id,
                    'opening_cash' => 1500.00,
                    'status' => 'open',
                    'notes' => 'Morning shift open',
                ],
            ]);

        $this->assertDatabaseHas('pos_sessions', [
            'store_id' => $this->store1->id,
            'user_id' => $this->storeManager->id,
            'opening_cash' => 1500.00,
            'status' => 'open',
        ]);
    }

    public function test_4_opening_balance_stored_correctly(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->postJson('/api/v1/pos/sessions', [
            'store_id' => $this->store1->id,
            'opening_cash' => 2500.50,
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'data' => [
                    'opening_cash' => 2500.50,
                    'closing_cash_system' => 2500.50,
                ],
            ]);
    }

    public function test_5_unauthorized_store_session_creation_rejected(): void
    {
        Sanctum::actingAs($this->storeManager);

        // storeManager is attached ONLY to store1, NOT store2
        $this->postJson('/api/v1/pos/sessions', [
            'store_id' => $this->store2->id,
            'opening_cash' => 1000.00,
        ])->assertStatus(403);
    }

    public function test_6_current_active_session_endpoint_works(): void
    {
        Sanctum::actingAs($this->storeManager);

        $session = PosSession::create([
            'store_id' => $this->store1->id,
            'user_id' => $this->storeManager->id,
            'opened_at' => now(),
            'opening_cash' => 1000.00,
            'status' => 'open',
        ]);

        $res = $this->getJson('/api/v1/pos/sessions/current');

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $session->id,
                    'status' => 'open',
                ],
            ]);
    }

    public function test_7_session_listing_works(): void
    {
        Sanctum::actingAs($this->storeManager);

        PosSession::create([
            'store_id' => $this->store1->id,
            'user_id' => $this->storeManager->id,
            'opened_at' => now(),
            'opening_cash' => 1000.00,
            'status' => 'open',
        ]);

        $this->getJson('/api/v1/pos/sessions')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_8_store_filtering_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        PosSession::create([
            'store_id' => $this->store1->id,
            'user_id' => $this->superAdmin->id,
            'opened_at' => now(),
            'opening_cash' => 1000.00,
            'status' => 'open',
        ]);

        PosSession::create([
            'store_id' => $this->store2->id,
            'user_id' => $this->superAdmin->id,
            'opened_at' => now(),
            'opening_cash' => 2000.00,
            'status' => 'open',
        ]);

        $this->getJson("/api/v1/pos/sessions?store_id={$this->store1->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_9_session_detail_works(): void
    {
        Sanctum::actingAs($this->storeManager);

        $session = PosSession::create([
            'store_id' => $this->store1->id,
            'user_id' => $this->storeManager->id,
            'opened_at' => now(),
            'opening_cash' => 1000.00,
            'status' => 'open',
        ]);

        $this->getJson("/api/v1/pos/sessions/{$session->id}")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['id' => $session->id]]);
    }

    public function test_10_active_session_can_be_closed(): void
    {
        Sanctum::actingAs($this->storeManager);

        $session = PosSession::create([
            'store_id' => $this->store1->id,
            'user_id' => $this->storeManager->id,
            'opened_at' => now(),
            'opening_cash' => 1000.00,
            'closing_cash_system' => 1000.00,
            'status' => 'open',
        ]);

        $res = $this->postJson("/api/v1/pos/sessions/{$session->id}/close", [
            'closing_cash_actual' => 1000.00,
            'notes' => 'Day end close',
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'closed',
                    'closing_cash_actual' => 1000.00,
                    'cash_difference' => 0.00,
                ],
            ]);
    }

    public function test_11_closing_balance_stored_correctly(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $session = PosSession::create([
            'store_id' => $this->store1->id,
            'user_id' => $this->superAdmin->id,
            'opened_at' => now(),
            'opening_cash' => 500.00,
            'closing_cash_system' => 500.00,
            'status' => 'open',
        ]);

        $this->postJson("/api/v1/pos/sessions/{$session->id}/close", [
            'closing_cash_actual' => 500.00,
        ])->assertStatus(200)->assertJson([
            'data' => [
                'closing_cash_actual' => 500.00,
            ],
        ]);
    }

    public function test_12_expected_actual_variance_calculation_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $session = PosSession::create([
            'store_id' => $this->store1->id,
            'user_id' => $this->superAdmin->id,
            'opened_at' => now(),
            'opening_cash' => 1000.00,
            'closing_cash_system' => 1000.00,
            'status' => 'open',
        ]);

        // Actual cash count is 950 (shortage of 50)
        $res = $this->postJson("/api/v1/pos/sessions/{$session->id}/close", [
            'closing_cash_actual' => 950.00,
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'closing_cash_system' => 1000.00,
                    'closing_cash_actual' => 950.00,
                    'cash_difference' => -50.00,
                ],
            ]);
    }

    public function test_13_closed_session_cannot_be_closed_again(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $session = PosSession::create([
            'store_id' => $this->store1->id,
            'user_id' => $this->superAdmin->id,
            'opened_at' => now(),
            'closed_at' => now(),
            'opening_cash' => 1000.00,
            'closing_cash_system' => 1000.00,
            'closing_cash_actual' => 1000.00,
            'status' => 'closed',
        ]);

        $this->postJson("/api/v1/pos/sessions/{$session->id}/close", [
            'closing_cash_actual' => 1000.00,
        ])->assertStatus(422);
    }

    public function test_14_invalid_session_transition_rejected(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $session = PosSession::create([
            'store_id' => $this->store1->id,
            'user_id' => $this->superAdmin->id,
            'opened_at' => now(),
            'closed_at' => now(),
            'status' => 'closed',
        ]);

        $this->postJson("/api/v1/pos/sessions/{$session->id}/close", [
            'closing_cash_actual' => 500.00,
        ])->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_15_unauthorized_user_cannot_close_another_stores_session(): void
    {
        Sanctum::actingAs($this->storeManager);

        $session = PosSession::create([
            'store_id' => $this->store2->id,
            'user_id' => $this->superAdmin->id,
            'opened_at' => now(),
            'opening_cash' => 1000.00,
            'status' => 'open',
        ]);

        $this->postJson("/api/v1/pos/sessions/{$session->id}/close", [
            'closing_cash_actual' => 1000.00,
        ])->assertStatus(403);
    }

    public function test_16_super_admin_can_manage_sessions_across_stores(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->postJson('/api/v1/pos/sessions', [
            'store_id' => $this->store2->id,
            'opening_cash' => 3000.00,
        ]);

        $res->assertStatus(201);
        $sessionId = $res->json('data.id');

        $this->postJson("/api/v1/pos/sessions/{$sessionId}/close", [
            'closing_cash_actual' => 3000.00,
        ])->assertStatus(200);
    }

    public function test_17_duplicate_active_session_protection_works(): void
    {
        Sanctum::actingAs($this->storeManager);

        // Open first session
        $this->postJson('/api/v1/pos/sessions', [
            'store_id' => $this->store1->id,
            'opening_cash' => 1000.00,
        ])->assertStatus(201);

        // Attempting to open a second active session for same user/store
        $this->postJson('/api/v1/pos/sessions', [
            'store_id' => $this->store1->id,
            'opening_cash' => 2000.00,
        ])->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_18_inactive_user_cannot_open_session(): void
    {
        $inactiveManager = User::create([
            'name' => 'Inactive Manager',
            'username' => 'inact_mgr',
            'email' => 'inact@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => false,
        ]);
        $inactiveManager->roles()->attach(Role::where('name', 'Store Manager')->first()->id, ['model_type' => User::class]);
        $inactiveManager->stores()->attach($this->store1->id);

        Sanctum::actingAs($inactiveManager);

        $this->postJson('/api/v1/pos/sessions', [
            'store_id' => $this->store1->id,
            'opening_cash' => 1000.00,
        ])->assertStatus(403);
    }

    public function test_19_nonexistent_session_returns_404(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->getJson('/api/v1/pos/sessions/99999')->assertStatus(404);
        $this->postJson('/api/v1/pos/sessions/99999/close', ['closing_cash_actual' => 1000.00])->assertStatus(404);
    }

    public function test_20_standardized_api_responses_are_maintained(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/pos/sessions');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'items',
                    'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ]);
    }
}
