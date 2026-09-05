<?php

namespace Tests\Feature\Api\v1;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $normalAdmin;
    protected Role $superAdminRole;
    protected Role $storeManagerRole;
    protected Store $mainStore;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Core Permissions
        $permissions = [
            'users.manage', 'users.view', 'users.create', 'users.edit', 'users.delete',
            'roles.manage', 'stores.manage', 'stores.view', 'products.view', 'system.settings'
        ];
        $permIds = [];
        foreach ($permissions as $p) {
            $perm = Permission::firstOrCreate(['name' => $p], ['guard_name' => 'web', 'module_group' => 'RBAC', 'display_name' => $p]);
            $permIds[] = $perm->id;
        }

        // 2. Create Roles
        $this->superAdminRole = Role::firstOrCreate(['name' => 'Super Admin'], ['guard_name' => 'web', 'description' => 'Super Admin']);
        $this->superAdminRole->permissions()->sync($permIds);

        $this->storeManagerRole = Role::firstOrCreate(['name' => 'Store Manager'], ['guard_name' => 'web', 'description' => 'Store Manager']);
        $this->storeManagerRole->permissions()->sync($permIds);

        // 3. Create Store
        $this->mainStore = Store::firstOrCreate(
            ['code' => 'STR-001'],
            ['name' => 'Main Outlet', 'phone' => '9876543210', 'email' => 'store1@test.com', 'address' => 'Test', 'city' => 'Kolkata', 'pincode' => '700001', 'is_active' => true]
        );

        // 4. Create Protected Super Admin
        $this->superAdmin = User::create([
            'name' => 'System Owner',
            'username' => 'system_owner',
            'email' => 'owner@test.com',
            'phone' => '9876543210',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
            'is_protected' => true,
        ]);
        $this->superAdmin->roles()->syncWithPivotValues([$this->superAdminRole->id], ['model_type' => User::class]);
        $this->superAdmin->stores()->sync([$this->mainStore->id => ['is_default' => true]]);

        // 5. Create Normal Admin User
        $this->normalAdmin = User::create([
            'name' => 'Normal Admin',
            'username' => 'normal_admin',
            'email' => 'normal@test.com',
            'phone' => '9876543211',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
            'is_protected' => false,
        ]);
        $this->normalAdmin->roles()->syncWithPivotValues([$this->storeManagerRole->id], ['model_type' => User::class]);
        $this->normalAdmin->stores()->sync([$this->mainStore->id => ['is_default' => true]]);
    }

    public function test_authorized_admin_can_list_users()
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/v1/users');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_normal_admin_cannot_see_protected_system_admin_in_users_list()
    {
        $response = $this->actingAs($this->normalAdmin, 'sanctum')
            ->getJson('/api/v1/users');

        $response->assertStatus(200);
        $items = $response->json('data.items');
        
        $protectedUserFound = collect($items)->contains('id', $this->superAdmin->id);
        $this->assertFalse($protectedUserFound, 'Protected System Admin user must not be exposed in user list to normal admins.');
    }

    public function test_authorized_admin_can_create_user_with_role_and_stores()
    {
        $payload = [
            'name' => 'Jane Cashier',
            'username' => 'jane_cashier',
            'email' => 'jane@test.com',
            'phone' => '9876543212',
            'password' => 'SecretPass@123',
            'password_confirmation' => 'SecretPass@123',
            'role_id' => $this->storeManagerRole->id,
            'is_active' => true,
            'store_ids' => [$this->mainStore->id],
        ];

        $response = $this->actingAs($this->normalAdmin, 'sanctum')
            ->postJson('/api/v1/users', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Jane Cashier')
            ->assertJsonPath('data.username', 'jane_cashier');

        $this->assertDatabaseHas('users', [
            'email' => 'jane@test.com',
            'username' => 'jane_cashier',
            'is_protected' => false,
        ]);
    }

    public function test_password_is_never_exposed_in_user_api_response()
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson("/api/v1/users/{$this->normalAdmin->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
    }

    public function test_authorized_admin_can_edit_user()
    {
        $targetUser = User::create([
            'name' => 'Old Name',
            'username' => 'old_user',
            'email' => 'old@test.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
            'is_protected' => false,
        ]);

        $payload = [
            'name' => 'Updated Name',
            'username' => 'updated_user',
            'email' => 'updated@test.com',
            'role_id' => $this->storeManagerRole->id,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->normalAdmin, 'sanctum')
            ->putJson("/api/v1/users/{$targetUser->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'name' => 'Updated Name',
            'username' => 'updated_user',
        ]);
    }

    public function test_authorized_admin_can_activate_and_deactivate_user()
    {
        $targetUser = User::create([
            'name' => 'Status User',
            'username' => 'status_user',
            'email' => 'status@test.com',
            'password' => Hash::make('Password@123'),
            'is_active' => true,
            'is_protected' => false,
        ]);

        $response = $this->actingAs($this->normalAdmin, 'sanctum')
            ->patchJson("/api/v1/users/{$targetUser->id}/status");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'is_active' => false,
        ]);
    }

    public function test_inactive_user_cannot_login()
    {
        $inactiveUser = User::create([
            'name' => 'Disabled User',
            'username' => 'disabled_user',
            'email' => 'disabled@test.com',
            'password' => Hash::make('Password@123'),
            'is_active' => false,
            'is_protected' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'disabled_user',
            'password' => 'Password@123',
        ]);

        $response->assertStatus(403);
    }

    public function test_normal_admin_cannot_modify_or_delete_protected_system_admin()
    {
        // 1. Try viewing protected admin
        $viewRes = $this->actingAs($this->normalAdmin, 'sanctum')
            ->getJson("/api/v1/users/{$this->superAdmin->id}");
        $viewRes->assertStatus(403);

        // 2. Try editing protected admin
        $editRes = $this->actingAs($this->normalAdmin, 'sanctum')
            ->putJson("/api/v1/users/{$this->superAdmin->id}", [
                'name' => 'Hacked Admin',
                'username' => 'hacked_admin',
                'email' => 'hacked@test.com',
            ]);
        $editRes->assertStatus(403);

        // 3. Try deleting protected admin
        $deleteRes = $this->actingAs($this->normalAdmin, 'sanctum')
            ->deleteJson("/api/v1/users/{$this->superAdmin->id}");
        $deleteRes->assertStatus(403);

        // 4. Try deactivating protected admin
        $statusRes = $this->actingAs($this->normalAdmin, 'sanctum')
            ->patchJson("/api/v1/users/{$this->superAdmin->id}/status");
        $statusRes->assertStatus(403);

        // Database assertion: System Admin details remain untouched
        $this->assertDatabaseHas('users', [
            'id' => $this->superAdmin->id,
            'name' => 'System Owner',
            'is_active' => true,
            'is_protected' => true,
        ]);
    }

    public function test_user_can_update_own_profile_and_change_own_password()
    {
        // 1. Profile Update
        $profileRes = $this->actingAs($this->normalAdmin, 'sanctum')
            ->putJson('/api/v1/auth/profile', [
                'name' => 'New Self Name',
                'email' => 'normal@test.com',
                'phone' => '9998887776',
            ]);
        $profileRes->assertStatus(200)
            ->assertJsonPath('data.name', 'New Self Name');

        // 2. Password Change (Correct password)
        $passwordRes = $this->actingAs($this->normalAdmin, 'sanctum')
            ->putJson('/api/v1/auth/change-password', [
                'current_password' => 'Password@123',
                'new_password' => 'NewSecurePassword@123',
                'new_password_confirmation' => 'NewSecurePassword@123',
            ]);
        $passwordRes->assertStatus(200);

        // Verify login works with new password
        $loginRes = $this->postJson('/api/v1/auth/login', [
            'login' => 'normal_admin',
            'password' => 'NewSecurePassword@123',
        ]);
        $loginRes->assertStatus(200);
    }

    public function test_change_password_rejects_incorrect_current_password()
    {
        $passwordRes = $this->actingAs($this->normalAdmin, 'sanctum')
            ->putJson('/api/v1/auth/change-password', [
                'current_password' => 'WrongPassword',
                'new_password' => 'NewSecurePassword@123',
                'new_password_confirmation' => 'NewSecurePassword@123',
            ]);
        $passwordRes->assertStatus(422);
    }

    public function test_duplicate_email_or_username_validation()
    {
        $response = $this->actingAs($this->normalAdmin, 'sanctum')
            ->postJson('/api/v1/users', [
                'name' => 'Duplicate User',
                'username' => 'normal_admin', // Duplicate username
                'email' => 'normal@test.com', // Duplicate email
                'password' => 'SecretPass@123',
                'password_confirmation' => 'SecretPass@123',
                'role_id' => $this->storeManagerRole->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['username', 'email']);
    }

    public function test_user_management_actions_generate_audit_logs()
    {
        $this->actingAs($this->normalAdmin, 'sanctum')
            ->postJson('/api/v1/users', [
                'name' => 'Audit User',
                'username' => 'audit_user',
                'email' => 'audit@test.com',
                'password' => 'SecretPass@123',
                'password_confirmation' => 'SecretPass@123',
                'role_id' => $this->storeManagerRole->id,
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'user_created',
        ]);
    }
}
