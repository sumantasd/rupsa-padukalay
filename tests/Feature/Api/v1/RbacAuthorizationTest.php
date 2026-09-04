<?php

namespace Tests\Feature\Api\v1;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RbacAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Register a temporary test route for testing RBAC permission middleware
        Route::get('/api/v1/test-protected', function () {
            return response()->json([
                'success' => true,
                'message' => 'Access Granted',
            ]);
        })->middleware(['auth:sanctum', 'permission:inventory.view']);
    }

    public function test_user_with_required_permission_can_access_protected_endpoint(): void
    {
        $permission = Permission::create([
            'name' => 'inventory.view',
            'guard_name' => 'web',
            'module_group' => 'Inventory',
            'display_name' => 'View Inventory',
        ]);

        $role = Role::create([
            'name' => 'Inventory Manager',
            'guard_name' => 'web',
        ]);

        $role->permissions()->attach($permission->id);

        $user = User::create([
            'name' => 'Manager User',
            'username' => 'inv_manager',
            'email' => 'inv_manager@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $user->roles()->attach($role->id, ['model_type' => User::class]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/test-protected');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Access Granted',
            ]);
    }

    public function test_user_without_required_permission_is_denied(): void
    {
        $user = User::create([
            'name' => 'Basic Cashier',
            'username' => 'cashier_noperm',
            'email' => 'cashier_noperm@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/test-protected');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Forbidden: You do not have permission [inventory.view] to perform this action.',
            ]);
    }

    public function test_unauthenticated_user_cannot_bypass_permission_protection(): void
    {
        $response = $this->getJson('/api/v1/test-protected');

        $response->assertStatus(401);
    }

    public function test_direct_permission_assignment_works(): void
    {
        $permission = Permission::create([
            'name' => 'inventory.view',
            'guard_name' => 'web',
            'module_group' => 'Inventory',
            'display_name' => 'View Inventory',
        ]);

        $user = User::create([
            'name' => 'Special Staff',
            'username' => 'special_staff',
            'email' => 'staff@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $user->permissions()->attach($permission->id, ['model_type' => User::class]);

        $this->assertTrue($user->hasPermissionTo('inventory.view'));

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/test-protected');
        $response->assertStatus(200);
    }

    public function test_super_admin_role_has_universal_access(): void
    {
        $role = Role::create([
            'name' => 'Super Admin',
            'guard_name' => 'web',
        ]);

        $user = User::create([
            'name' => 'Admin User',
            'username' => 'superadmin',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $user->roles()->attach($role->id, ['model_type' => User::class]);

        $this->assertTrue($user->hasPermissionTo('any.random.permission'));

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/test-protected');
        $response->assertStatus(200);
    }

    public function test_deactivated_user_is_denied_permission_check(): void
    {
        $permission = Permission::create([
            'name' => 'inventory.view',
            'guard_name' => 'web',
            'module_group' => 'Inventory',
            'display_name' => 'View Inventory',
        ]);

        $user = User::create([
            'name' => 'Deactivated User',
            'username' => 'inactive_user',
            'email' => 'inactive@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => false,
        ]);

        $user->permissions()->attach($permission->id, ['model_type' => User::class]);

        $this->assertFalse($user->hasPermissionTo('inventory.view'));
    }

    public function test_middleware_returns_standardized_api_error_response(): void
    {
        $user = User::create([
            'name' => 'Unprivileged User',
            'username' => 'unprivileged',
            'email' => 'unprivileged@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/test-protected');

        $response->assertStatus(403)
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ]);
    }
}
