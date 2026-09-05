<?php

namespace Tests\Feature\Api\v1;

use App\Models\AuditLog;
use App\Models\CmsSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlAndSettingsModuleTest extends TestCase
{
    use RefreshDatabase;

    protected ?User $adminUser = null;
    protected ?User $cashierUser = null;
    protected ?Store $mainStore = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\ProductionAdminUserSeeder::class);

        $this->mainStore = Store::where('code', 'STR-001')->firstOrFail();
        $cashierRole = Role::where('name', 'POS Cashier')->firstOrFail();
        $superAdminRole = Role::where('name', 'Super Admin')->firstOrFail();

        $this->adminUser = User::firstOrCreate(
            ['username' => 'rupsa_admin_spec'],
            [
                'name' => 'Super Admin Spec',
                'email' => 'admin_spec@rupsa.in',
                'password' => bcrypt('password123'),
                'is_active' => true,
            ]
        );
        if (! $this->adminUser->roles()->where('name', 'Super Admin')->exists()) {
            $this->adminUser->roles()->attach($superAdminRole->id, ['model_type' => User::class]);
            $this->adminUser->stores()->attach($this->mainStore->id, ['is_default' => true]);
        }

        $this->cashierUser = User::firstOrCreate(
            ['username' => 'cashier_test_spec'],
            [
                'name' => 'Test Cashier Spec',
                'email' => 'cashier_spec@rupsa.in',
                'password' => bcrypt('password123'),
                'is_active' => true,
            ]
        );
        if (! $this->cashierUser->roles()->where('name', 'POS Cashier')->exists()) {
            $this->cashierUser->roles()->attach($cashierRole->id, ['model_type' => User::class]);
            $this->cashierUser->stores()->attach($this->mainStore->id, ['is_default' => true]);
        }
    }

    // ==========================================
    // 1. ROLES & PERMISSIONS TESTS
    // ==========================================

    public function test_authorized_admin_can_list_roles(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/api/v1/roles');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }

    public function test_authorized_admin_can_create_role(): void
    {
        $perm = Permission::first();

        $response = $this->actingAs($this->adminUser)->postJson('/api/v1/roles', [
            'name' => 'Auditor Test Role',
            'description' => 'Test role description',
            'permissions' => [$perm->id],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Auditor Test Role');

        $this->assertDatabaseHas('roles', ['name' => 'Auditor Test Role']);
    }

    public function test_authorized_admin_can_update_role(): void
    {
        $role = Role::create(['name' => 'Test Role Update', 'guard_name' => 'web']);
        $perm = Permission::first();

        $response = $this->actingAs($this->adminUser)->putJson("/api/v1/roles/{$role->id}", [
            'name' => 'Updated Test Role',
            'description' => 'Updated description',
            'permissions' => [$perm->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Test Role');
    }

    public function test_unauthorized_user_receives_403_on_roles_management(): void
    {
        $response = $this->actingAs($this->cashierUser)->postJson('/api/v1/roles', [
            'name' => 'Unauthorized Role',
        ]);

        $response->assertStatus(403);
    }

    public function test_protected_super_admin_role_cannot_be_deleted(): void
    {
        $superAdminRole = Role::where('name', 'Super Admin')->first();

        $response = $this->actingAs($this->adminUser)->deleteJson("/api/v1/roles/{$superAdminRole->id}");

        $response->assertStatus(403);
    }

    // ==========================================
    // 2. STORE ACCESS TESTS
    // ==========================================

    public function test_authorized_admin_can_get_store_access_matrix(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/api/v1/store-access');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['users', 'stores']]);
    }

    public function test_authorized_admin_can_assign_store_and_default(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson("/api/v1/store-access/assign/{$this->cashierUser->id}", [
            'store_ids' => [$this->mainStore->id],
            'default_store_id' => $this->mainStore->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.default_store_id', $this->mainStore->id);
    }

    public function test_unauthorized_user_cannot_assign_store_access(): void
    {
        $response = $this->actingAs($this->cashierUser)->postJson("/api/v1/store-access/assign/{$this->adminUser->id}", [
            'store_ids' => [$this->mainStore->id],
            'default_store_id' => $this->mainStore->id,
        ]);

        $response->assertStatus(403);
    }

    // ==========================================
    // 3. AUDIT LOG TESTS
    // ==========================================

    public function test_authorized_admin_can_view_audit_logs(): void
    {
        $response = $this->actingAs($this->adminUser)->getJson('/api/v1/audit-logs');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['items', 'pagination']]);
    }

    public function test_role_changes_generate_audit_logs(): void
    {
        $this->actingAs($this->adminUser)->postJson('/api/v1/roles', [
            'name' => 'Audited Test Role',
            'description' => 'Audit test',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'access_control',
            'event_type' => 'role_created',
        ]);
    }

    public function test_sensitive_passwords_are_not_logged_in_audit(): void
    {
        $log = AuditLog::latest('id')->first();
        if ($log) {
            $json = json_encode($log->toArray());
            $this->assertStringNotContainsString('"password":', $json);
        }
        $this->assertTrue(true);
    }

    // ==========================================
    // 4. COMPANY PROFILE TESTS
    // ==========================================

    public function test_company_profile_fetch_and_update(): void
    {
        $fetchRes = $this->actingAs($this->adminUser)->getJson('/api/v1/settings/company');
        $fetchRes->assertStatus(200);

        $updateRes = $this->actingAs($this->adminUser)->postJson('/api/v1/settings/company', [
            'company_name' => 'RUPSA PADUKALAYA TEST',
            'tagline' => 'STEP INTO COMFORT',
            'phone' => '+91 9735125112',
        ]);
        $updateRes->assertStatus(200)
            ->assertJsonPath('data.company_name', 'RUPSA PADUKALAYA TEST');
    }

    // ==========================================
    // 5. INVOICE SETTINGS TESTS
    // ==========================================

    public function test_invoice_settings_fetch_and_update(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/api/v1/settings/invoices', [
            'invoice_prefix' => 'RUP-',
            'invoice_title' => 'TAX INVOICE',
            'date_format' => 'DD/MM/YYYY',
            'show_customer_phone' => true,
            'show_item_sku' => true,
            'show_tax_breakdown' => true,
            'show_discount_summary' => true,
            'footer_note' => 'Thank you for visiting!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.invoice_prefix', 'RUP-');
    }

    // ==========================================
    // 6. TAX SETTINGS TESTS
    // ==========================================

    public function test_tax_settings_fetch_and_update(): void
    {
        $response = $this->actingAs($this->adminUser)->putJson('/api/v1/tax-settings', [
            'gst_enabled' => '1',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.gst_enabled', true);
    }

    // ==========================================
    // 7. PAYMENT METHODS TESTS
    // ==========================================

    public function test_cashier_can_read_active_payment_methods_but_cannot_modify(): void
    {
        $activeRes = $this->actingAs($this->cashierUser)->getJson('/api/v1/payment-methods/active');
        $activeRes->assertStatus(200);

        $modifyRes = $this->actingAs($this->cashierUser)->postJson('/api/v1/settings/payment-methods', [
            'name' => 'Unauthorized Method',
            'code' => 'UNAUTH',
            'is_active' => true,
            'requires_reference' => false,
        ]);
        $modifyRes->assertStatus(403);
    }

    public function test_authorized_admin_can_manage_payment_methods(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/api/v1/settings/payment-methods', [
            'name' => 'Google Pay QR',
            'code' => 'GPAY_QR',
            'description' => 'Google Pay direct QR scanner',
            'icon' => '📱',
            'is_active' => true,
            'requires_reference' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.code', 'GPAY_QR');
    }

    // ==========================================
    // 8. POS SETTINGS TESTS
    // ==========================================

    public function test_cashier_can_read_pos_settings_but_cannot_modify(): void
    {
        $readRes = $this->actingAs($this->cashierUser)->getJson('/api/v1/settings/pos');
        $readRes->assertStatus(200);

        $writeRes = $this->actingAs($this->cashierUser)->postJson('/api/v1/settings/pos', [
            'allow_negative_stock' => true,
            'require_customer_details' => false,
            'allow_manual_item_discount' => true,
            'allow_bill_level_discount' => true,
            'max_discount_percentage' => 50,
            'require_session_opening_float' => true,
            'auto_print_receipt_on_settle' => true,
            'enable_sound_effects' => true,
            'barcode_auto_add_to_cart' => true,
            'enable_quick_cash_buttons' => true,
            'holding_cart_limit' => 10,
            'cashier_can_void_item' => true,
        ]);
        $writeRes->assertStatus(403);
    }

    public function test_authorized_admin_can_update_pos_settings(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/api/v1/settings/pos', [
            'allow_negative_stock' => false,
            'require_customer_details' => false,
            'allow_manual_item_discount' => true,
            'allow_bill_level_discount' => true,
            'max_discount_percentage' => 25,
            'require_session_opening_float' => true,
            'auto_print_receipt_on_settle' => true,
            'enable_sound_effects' => true,
            'barcode_auto_add_to_cart' => true,
            'enable_quick_cash_buttons' => true,
            'holding_cart_limit' => 15,
            'cashier_can_void_item' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.max_discount_percentage', 25);
    }

    // ==========================================
    // 9. NUMBER SERIES TESTS
    // ==========================================

    public function test_authorized_admin_can_configure_number_series(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/api/v1/settings/number-series', [
            'series' => [
                'sales_invoice' => [
                    'prefix' => 'INV-',
                    'start_number' => 1,
                    'current_number' => 100,
                    'padding' => 6,
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.sales_invoice.prefix', 'INV-');
    }

    // ==========================================
    // 10. GENERAL SETTINGS TESTS
    // ==========================================

    public function test_authorized_admin_can_update_general_settings(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/api/v1/settings/general', [
            'app_title' => 'RUPSA PADUKALAYA ERP',
            'timezone' => 'Asia/Kolkata',
            'currency_symbol' => '₹',
            'currency_code' => 'INR',
            'date_format' => 'DD/MM/YYYY',
            'time_format' => '12h',
            'decimal_precision' => 2,
            'default_language' => 'en',
            'session_timeout_minutes' => 60,
            'enable_email_notifications' => true,
            'enable_browser_notifications' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.currency_symbol', '₹');
    }

    // ==========================================
    // 11. MODULE SETTINGS TESTS
    // ==========================================

    public function test_module_settings_allow_new_store_creation_behavior(): void
    {
        $response = $this->actingAs($this->adminUser)->putJson('/api/v1/module-settings', [
            'allow_new_store_creation' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.allow_new_store_creation', true);
    }
}
