<?php

namespace Tests\Feature\Api\v1;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrinterSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $permView = Permission::firstOrCreate(['name' => 'products.view'], ['guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Products']);
        $permEdit = Permission::firstOrCreate(['name' => 'products.edit'], ['guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Edit Products']);

        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin'], ['guard_name' => 'web']);
        $superAdminRole->permissions()->syncWithoutDetaching([$permView->id, $permEdit->id]);

        $this->adminUser = User::create([
            'name' => 'Printer Settings Admin',
            'username' => 'prn_admin_' . rand(1000, 9999),
            'email' => 'prn_admin_' . rand(1000, 9999) . '@rupsa.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($superAdminRole->id, ['model_type' => User::class]);
    }

    public function test_get_printer_settings_returns_defaults()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/settings/printer');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.printer_enabled', true)
            ->assertJsonPath('data.printer_width', '80mm')
            ->assertJsonPath('data.show_store_name', true)
            ->assertJsonPath('data.show_developed_by_credit', true);
    }

    public function test_update_printer_settings_persists_changes()
    {
        $payload = [
            'printer_width' => '58mm',
            'show_customer_mobile' => false,
            'show_qr_code' => false,
            'thank_you_message' => 'Custom RUPSA Thank You Message',
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/settings/printer', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.printer_width', '58mm')
            ->assertJsonPath('data.show_customer_mobile', false)
            ->assertJsonPath('data.show_qr_code', false)
            ->assertJsonPath('data.thank_you_message', 'Custom RUPSA Thank You Message');

        // Verify persistence via GET
        $getRes = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/settings/printer');

        $getRes->assertStatus(200)
            ->assertJsonPath('data.printer_width', '58mm')
            ->assertJsonPath('data.show_customer_mobile', false);
    }

    public function test_test_print_endpoint_returns_sample_payloads()
    {
        foreach (['invoice', 'return', 'exchange', 'payment'] as $docType) {
            $response = $this->actingAs($this->adminUser, 'sanctum')
                ->postJson('/api/v1/settings/printer/test-print', ['doc_type' => $docType]);

            $response->assertStatus(200)
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.doc_type', $docType)
                ->assertJsonPath('data.sample_data.store.code', 'STR-001')
                ->assertJsonPath('data.sample_data.store.name', 'RUPSA PADUKALAYA - Main Outlet');
        }
    }
}
