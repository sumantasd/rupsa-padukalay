<?php

namespace Tests\Feature\Api\v1;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected Store $mainStore;
    protected Role $storeManagerRole;
    protected Role $posCashierRole;
    protected Role $accountantRole;
    protected Role $superAdminRole;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed Permissions & Roles
        $permissions = [
            'products.view', 'products.create', 'products.edit', 'inventory.view',
            'pos.billing', 'pos.sessions', 'pos.returns', 'reports.view', 'users.manage'
        ];
        $permIds = [];
        foreach ($permissions as $p) {
            $perm = Permission::firstOrCreate(['name' => $p], ['guard_name' => 'web', 'module_group' => 'RBAC', 'display_name' => $p]);
            $permIds[] = $perm->id;
        }

        $this->superAdminRole = Role::firstOrCreate(['name' => 'Super Admin'], ['guard_name' => 'web', 'description' => 'Super Admin']);
        $this->superAdminRole->permissions()->sync($permIds);

        $this->storeManagerRole = Role::firstOrCreate(['name' => 'Store Manager'], ['guard_name' => 'web', 'description' => 'Store Manager']);
        $this->storeManagerRole->permissions()->sync($permIds);

        $this->posCashierRole = Role::firstOrCreate(['name' => 'POS Cashier'], ['guard_name' => 'web', 'description' => 'POS Cashier']);
        $cashierPerms = Permission::whereIn('name', ['pos.billing', 'pos.sessions', 'products.view'])->pluck('id')->toArray();
        $this->posCashierRole->permissions()->sync($cashierPerms);

        $this->accountantRole = Role::firstOrCreate(['name' => 'Accountant'], ['guard_name' => 'web', 'description' => 'Accountant']);
        $accountantPerms = Permission::whereIn('name', ['reports.view', 'products.view'])->pluck('id')->toArray();
        $this->accountantRole->permissions()->sync($accountantPerms);

        // 2. Create Store
        $this->mainStore = Store::firstOrCreate(
            ['code' => 'STR-001'],
            ['name' => 'Main Outlet', 'phone' => '9876543210', 'email' => 'store1@test.com', 'address' => 'Test', 'city' => 'Kolkata', 'pincode' => '700001', 'is_active' => true]
        );
    }

    public function test_newly_created_store_manager_user_can_login()
    {
        $user = User::create([
            'name' => 'New Store Manager',
            'username' => 'new_manager',
            'email' => 'manager@test.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
            'is_protected' => false,
        ]);
        $user->roles()->syncWithPivotValues([$this->storeManagerRole->id], ['model_type' => User::class]);
        $user->stores()->sync([$this->mainStore->id => ['is_default' => true]]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'new_manager',
            'password' => 'Password@123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', 'new_manager')
            ->assertJsonPath('data.user.roles.0.name', 'Store Manager');

        $this->assertNotEmpty($response->json('data.access_token'));
    }

    public function test_newly_created_pos_cashier_user_can_login()
    {
        $user = User::create([
            'name' => 'New POS Cashier',
            'username' => 'new_cashier',
            'email' => 'cashier@test.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
            'is_protected' => false,
        ]);
        $user->roles()->syncWithPivotValues([$this->posCashierRole->id], ['model_type' => User::class]);
        $user->stores()->sync([$this->mainStore->id => ['is_default' => true]]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'new_cashier',
            'password' => 'Password@123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', 'new_cashier')
            ->assertJsonPath('data.user.roles.0.name', 'POS Cashier');

        $rolesData = $response->json('data.user.roles');
        $this->assertNotEmpty($rolesData[0]['permissions']);
    }

    public function test_newly_created_accountant_user_can_login()
    {
        $user = User::create([
            'name' => 'New Accountant',
            'username' => 'new_accountant',
            'email' => 'accountant@test.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
            'is_protected' => false,
        ]);
        $user->roles()->syncWithPivotValues([$this->accountantRole->id], ['model_type' => User::class]);
        $user->stores()->sync([$this->mainStore->id => ['is_default' => true]]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'new_accountant',
            'password' => 'Password@123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_inactive_new_user_login_is_rejected()
    {
        $user = User::create([
            'name' => 'Disabled User',
            'username' => 'disabled_new',
            'email' => 'disabled_new@test.com',
            'password' => Hash::make('Password@123'),
            'is_active' => false,
            'is_protected' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'disabled_new',
            'password' => 'Password@123',
        ]);

        $response->assertStatus(403);
    }

    public function test_authenticated_me_endpoint_returns_user_with_roles_and_permissions()
    {
        $user = User::create([
            'name' => 'Auth Me User',
            'username' => 'auth_me_user',
            'email' => 'me@test.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
            'is_protected' => false,
        ]);
        $user->roles()->syncWithPivotValues([$this->posCashierRole->id], ['model_type' => User::class]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.username', 'auth_me_user');
    }
}
