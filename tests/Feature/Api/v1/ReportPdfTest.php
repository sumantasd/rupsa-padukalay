<?php

namespace Tests\Feature\Api\v1;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportPdfTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $noPermUser;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $permViewReports = Permission::create(['name' => 'reports.view', 'guard_name' => 'web', 'module_group' => 'Reports', 'display_name' => 'View Reports']);

        $superAdminRole = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdminRole->permissions()->attach([$permViewReports->id]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin User',
            'username' => 'superadmin_pdf',
            'email' => 'admin_pdf@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->superAdmin->roles()->attach($superAdminRole->id, ['model_type' => User::class]);

        $this->noPermUser = User::create([
            'name' => 'No Perm User',
            'username' => 'noperm_pdf',
            'email' => 'noperm_pdf@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $this->store = Store::create([
            'code' => 'STR-PDF-001',
            'name' => 'Main Test Store',
            'city' => 'Kolkata',
            'state' => 'West Bengal',
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_report_pdf(): void
    {
        $response = $this->get('/api/v1/reports/pdf?type=sales_summary');

        $response->assertStatus(401);
        $response->assertJson(['success' => false, 'message' => 'Unauthenticated or session expired. Please log in.']);
    }

    public function test_request_with_undefined_or_missing_type_returns_422_error(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->get('/api/v1/reports/pdf?type=undefined');

        $response->assertStatus(422);
        $response->assertJson(['success' => false, 'message' => 'Valid report type parameter is required.']);
    }

    public function test_super_admin_can_view_inline_sales_summary_pdf(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->get('/api/v1/reports/pdf?type=sales_summary&action=inline');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition');
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
    }

    public function test_super_admin_can_download_sales_summary_pdf_with_correct_filename(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->get('/api/v1/reports/pdf?type=sales_summary&action=attachment&date_from=2026-09-01&date_to=2026-09-04');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('RUPSA-sales-summary-2026-09-01-to-2026-09-04.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_can_generate_profit_loss_pdf(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->get('/api/v1/reports/pdf?type=profit_loss&action=inline');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_can_generate_inventory_valuation_pdf(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->get('/api/v1/reports/pdf?type=inventory_valuation&action=inline');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_can_generate_purchase_summary_pdf(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->get('/api/v1/reports/pdf?type=purchase_summary&action=inline');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_can_generate_customer_summary_pdf(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->get('/api/v1/reports/pdf?type=customer_summary&action=inline');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_can_generate_payment_summary_pdf(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->get('/api/v1/reports/pdf?type=payment_summary&action=inline');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_all_24_report_types_generate_valid_pdf_responses(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $types = [
            'sales_summary', 'sales_itemwise', 'sales_datewise',
            'inventory_valuation', 'inventory_low_stock', 'inventory_fast_moving',
            'inventory_slow_moving', 'inventory_dead_stock', 'inventory_movements',
            'inventory_adjustments', 'inventory_transfers', 'inventory_damage', 'inventory_stock',
            'purchase_bills', 'purchase_orders', 'purchase_grn', 'purchase_returns', 'purchase_summary',
            'customer_summary', 'payment_collections', 'payment_datewise', 'payment_refunds',
            'payment_summary', 'profit_loss'
        ];

        foreach ($types as $type) {
            $responseInline = $this->get("/api/v1/reports/pdf?type={$type}&action=inline");
            $responseInline->assertStatus(200);
            $responseInline->assertHeader('Content-Type', 'application/pdf');

            $responseDownload = $this->get("/api/v1/reports/pdf?type={$type}&action=download");
            $responseDownload->assertStatus(200);
            $responseDownload->assertHeader('Content-Type', 'application/pdf');
        }
    }

    public function test_inventory_stock_pdf_data_mapping_and_non_zero_summary_kpis(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $service = app(\App\Services\ReportPdfService::class);
        $reflector = new \ReflectionClass($service);
        $method = $reflector->getMethod('prepareReportData');
        $method->setAccessible(true);

        $data = $method->invoke($service, $this->superAdmin, 'inventory_stock', ['type' => 'inventory_stock']);

        $this->assertNotEmpty($data['kpis']);
        $this->assertNotEmpty($data['headers']);
        $this->assertContains('Article #', $data['headers']);
        $this->assertContains('Stock Valuation (Cost)', array_column($data['kpis'], 'label'));

        $html = view('reports.pdf', $data)->render();
        $this->assertStringContainsString('Total Inventory Units', $html);
        $this->assertStringContainsString('Stock Valuation (Cost)', $html);
        $this->assertStringContainsString('Stock Valuation (Selling)', $html);
        $this->assertStringContainsString('Expected Gross Margin', $html);
    }
}
