<?php

namespace Tests\Feature\Api\v1;

use App\Models\User;
use Database\Seeders\ProductionAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductionAdminUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductionAdminUserSeeder::class);
    }

    public function test_production_admin_user_exists_and_is_configured_correctly(): void
    {
        $admin = User::where('email', 'admin@rupsapadukalaya.in')->first();

        $this->assertNotNull($admin, 'Admin user admin@rupsapadukalaya.in should exist in database.');
        $this->assertTrue($admin->is_active, 'Admin user should be active.');
        $this->assertTrue(Hash::check('RupsaAdmin#2026!', $admin->password), 'Admin password should match configured secret.');
        
        // Verify Super Admin Role
        $this->assertTrue(
            $admin->roles()->where('name', 'Super Admin')->exists(),
            'Admin user should have Super Admin role assigned.'
        );

        // Verify Full Permission Check
        $this->assertTrue(
            $admin->hasPermissionTo('users.manage'),
            'Super Admin user should have users.manage permission.'
        );
        $this->assertTrue(
            $admin->hasPermissionTo('system.settings'),
            'Super Admin user should have system.settings permission.'
        );

        // Verify Store Access
        $this->assertGreaterThanOrEqual(1, $admin->stores()->count(), 'Admin user should be assigned to stores.');
    }

    public function test_production_admin_user_can_login_via_sanctum_api(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'admin@rupsapadukalaya.in',
            'password' => 'RupsaAdmin#2026!',
            'device_name' => 'Admin ERP Portal',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'admin@rupsapadukalaya.in');

        $token = $response->json('data.access_token');
        $this->assertNotEmpty($token);

        // Verify GET /api/v1/auth/me with Bearer token
        $meResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $meResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'admin@rupsapadukalaya.in');
    }
}
