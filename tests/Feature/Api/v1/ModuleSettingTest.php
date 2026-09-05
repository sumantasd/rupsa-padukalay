<?php

namespace Tests\Feature\Api\v1;

use App\Models\CmsSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModuleSettingTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $permSettings = Permission::create([
            'name' => 'system.settings',
            'guard_name' => 'web',
            'module_group' => 'System',
            'display_name' => 'System Settings',
        ]);

        $adminRole = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $adminRole->permissions()->attach($permSettings->id);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'username' => 'admin_setting',
            'email' => 'adminsetting@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($adminRole->id, ['model_type' => User::class]);

        $this->regularUser = User::create([
            'name' => 'Regular User',
            'username' => 'regular_user',
            'email' => 'regular@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
    }

    public function test_get_module_settings_returns_default_allow_new_store_creation_as_false(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/v1/module-settings');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'allow_new_store_creation' => false,
                ],
            ]);
    }

    public function test_update_allow_new_store_creation_toggle_persists_setting(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Turn ON
        $response = $this->putJson('/api/v1/module-settings', [
            'allow_new_store_creation' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'allow_new_store_creation' => true,
                ],
            ]);

        $this->assertEquals('1', CmsSetting::getSetting('allow_new_store_creation'));

        // Turn OFF
        $responseOff = $this->putJson('/api/v1/module-settings', [
            'allow_new_store_creation' => false,
        ]);

        $responseOff->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'allow_new_store_creation' => false,
                ],
            ]);

        $this->assertEquals('0', CmsSetting::getSetting('allow_new_store_creation'));
    }

    public function test_unauthorized_user_cannot_update_module_settings(): void
    {
        Sanctum::actingAs($this->regularUser);

        $this->putJson('/api/v1/module-settings', [
            'allow_new_store_creation' => true,
        ])->assertStatus(403);
    }
}
