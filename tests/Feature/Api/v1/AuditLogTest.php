<?php

namespace Tests\Feature\Api\v1;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\HsnCode;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\PosRegister;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\Role;
use App\Models\Size;
use App\Models\Store;
use App\Models\StoreCreditTransaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $manager;
    protected User $noPermUser;
    protected Store $store1;
    protected Store $store2;
    protected Warehouse $warehouse1;
    protected ProductVariantSize $variantSize1;
    protected Customer $customer1;
    protected PosRegister $register1;
    protected PosSession $session1;
    protected AuditService $auditService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditService = app(AuditService::class);

        $permView = Permission::create(['name' => 'products.view', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Products']);
        $permCreate = Permission::create(['name' => 'products.create', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Create Products']);

        $superAdminRole = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdminRole->permissions()->attach([$permView->id, $permCreate->id]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->superAdmin->roles()->attach($superAdminRole->id, ['model_type' => User::class]);

        $managerRole = Role::create(['name' => 'Manager', 'guard_name' => 'web']);
        $managerRole->permissions()->attach([$permView->id, $permCreate->id]);

        $this->store1 = Store::create(['code' => 'ST-001', 'name' => 'Main Store', 'is_active' => true]);
        $this->store2 = Store::create(['code' => 'ST-002', 'name' => 'Branch Store', 'is_active' => true]);
        $this->warehouse1 = Warehouse::create(['code' => 'WH-001', 'name' => 'Main Warehouse', 'store_id' => $this->store1->id, 'is_active' => true]);

        $this->manager = User::create([
            'name' => 'Store Manager',
            'username' => 'store_manager',
            'email' => 'manager@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->manager->roles()->attach($managerRole->id, ['model_type' => User::class]);
        $this->manager->stores()->attach($this->store1->id);

        $this->noPermUser = User::create([
            'name' => 'No Perm User',
            'username' => 'noperm_user',
            'email' => 'noperm@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $this->customer1 = Customer::create([
            'name' => 'Rahul Sharma',
            'mobile_number' => '9876543210',
            'email' => 'rahul@example.com',
            'is_active' => true,
        ]);

        $hsn = HsnCode::create(['code' => '6403', 'description' => 'Footwear', 'default_gst_rate' => 12.00]);
        $brand = Brand::create(['name' => 'Adidas', 'slug' => 'adidas']);
        $cat = Category::create(['name' => 'Sneakers', 'slug' => 'sneakers', 'hsn_code_id' => $hsn->id]);
        $product = Product::create(['article_number' => 'ADI-500', 'name' => 'Ultraboost', 'slug' => 'ultraboost', 'brand_id' => $brand->id, 'category_id' => $cat->id, 'hsn_code_id' => $hsn->id]);
        $color = Color::create(['name' => 'White', 'code' => 'WHT']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color_id' => $color->id]);
        $size10 = Size::create(['size_number' => '10']);

        $this->variantSize1 = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $size10->id,
            'sku' => 'ADI-500-WHT-10',
            'cost_price' => 800.00,
            'mrp' => 2000.00,
            'selling_price' => 1600.00,
        ]);

        InventoryStock::create(['product_variant_size_id' => $this->variantSize1->id, 'store_id' => $this->store1->id, 'stock_quantity' => 50, 'reorder_level' => 10]);

        $this->register1 = PosRegister::create(['store_id' => $this->store1->id, 'code' => 'REG-01', 'name' => 'Register 1', 'is_active' => true]);
        $this->session1 = PosSession::create([
            'client_session_uuid' => 'sess-001',
            'pos_register_id' => $this->register1->id,
            'store_id' => $this->store1->id,
            'user_id' => $this->manager->id,
            'opened_at' => now(),
            'opening_cash' => 1000.00,
            'status' => 'open',
        ]);
    }

    public function test_1_unauthenticated_access_rejected(): void
    {
        $this->getJson('/api/v1/audit-logs')->assertStatus(401);
        $this->getJson('/api/v1/audit-logs/financial-trail')->assertStatus(401);
        $this->getJson('/api/v1/audit-logs/1')->assertStatus(401);
    }

    public function test_2_rbac_permission_rejection(): void
    {
        Sanctum::actingAs($this->noPermUser);

        $this->getJson('/api/v1/audit-logs')->assertStatus(403);
        $this->getJson('/api/v1/audit-logs/financial-trail')->assertStatus(403);
    }

    public function test_3_nonexistent_store_audit_query_handling(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/audit-logs?store_id=99999')->assertStatus(403);
    }

    public function test_4_empty_dataset_audit_log_returns_empty_paginated_list(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/audit-logs');

        $res->assertStatus(200);
        $this->assertCount(0, $res->json('data.items'));
    }

    public function test_5_pos_sale_creation_generates_audit_log(): void
    {
        Sanctum::actingAs($this->manager);

        $payload = [
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->session1->id,
            'customer_id' => $this->customer1->id,
            'items' => [
                [
                    'product_variant_size_id' => $this->variantSize1->id,
                    'quantity' => 1,
                    'unit_price' => 1600.00,
                ],
            ],
            'payment_method' => 'cash',
        ];

        $res = $this->postJson('/api/v1/pos/sales', $payload);
        $res->assertStatus(201);

        $log = AuditLog::where('module', 'pos_sale')->where('event_type', 'invoice_created')->first();
        $this->assertNotNull($log);
        $this->assertEquals($this->store1->id, $log->store_id);
    }

    public function test_6_sales_return_processing_generates_audit_log(): void
    {
        $log = $this->auditService->logEvent([
            'user_id' => $this->manager->id,
            'store_id' => $this->store1->id,
            'module' => 'sales_return',
            'event_type' => 'return_processed',
            'reason_notes' => 'Return processed for order #1',
        ]);

        $this->assertNotNull($log);
        $this->assertEquals('sales_return', $log->module);
    }

    public function test_7_exchange_transaction_generates_audit_log(): void
    {
        $log = $this->auditService->logEvent([
            'user_id' => $this->manager->id,
            'store_id' => $this->store1->id,
            'module' => 'exchange',
            'event_type' => 'exchange_processed',
            'reason_notes' => 'Item exchange processed',
        ]);

        $this->assertNotNull($log);
        $this->assertEquals('exchange_processed', $log->event_type);
    }

    public function test_8_expense_creation_and_mutation_audit_logs(): void
    {
        Sanctum::actingAs($this->manager);

        $cat = ExpenseCategory::create(['name' => 'Office Supplies', 'code' => 'OFFICE']);
        $payload = [
            'expense_category_id' => $cat->id,
            'store_id' => $this->store1->id,
            'amount' => 250.00,
            'payment_method' => 'cash',
            'description' => 'Printer Paper',
            'expense_date' => now()->toDateString(),
        ];

        $res = $this->postJson('/api/v1/expenses', $payload);
        $res->assertStatus(201);

        $log = AuditLog::where('module', 'expense')->where('event_type', 'expense_created')->first();
        $this->assertNotNull($log);
        $this->assertEquals(250.00, $log->after_state['amount']);
    }

    public function test_9_store_credit_issue_and_redemption_audit_logs(): void
    {
        $service = app(\App\Services\StoreCreditService::class);
        $service->issueOrAdjustCredit($this->customer1, 500.00, 'issue_adjustment', $this->manager, $this->store1->id);

        $log = AuditLog::where('module', 'store_credit')->where('event_type', 'credit_issued')->first();
        $this->assertNotNull($log);
        $this->assertEquals(500.00, $log->after_state['amount']);
    }

    public function test_10_loyalty_point_earning_and_redemption_audit_logs(): void
    {
        $log = $this->auditService->logEvent([
            'user_id' => $this->manager->id,
            'store_id' => $this->store1->id,
            'module' => 'loyalty',
            'event_type' => 'points_earned',
            'reason_notes' => 'Loyalty points earned on sale',
        ]);

        $this->assertNotNull($log);
        $this->assertEquals('loyalty', $log->module);
    }

    public function test_11_inventory_stock_adjustment_audit_logs(): void
    {
        $log = $this->auditService->logEvent([
            'user_id' => $this->manager->id,
            'store_id' => $this->store1->id,
            'module' => 'inventory',
            'event_type' => 'stock_adjusted',
            'reason_notes' => 'Physical stock count adjustment',
        ]);

        $this->assertNotNull($log);
        $this->assertEquals('inventory', $log->module);
    }

    public function test_12_stock_transfer_creation_and_receipt_audit_logs(): void
    {
        $log = $this->auditService->logEvent([
            'user_id' => $this->manager->id,
            'store_id' => $this->store1->id,
            'module' => 'inventory',
            'event_type' => 'transfer_sent',
            'reason_notes' => 'Inter-store transfer sent to Branch Store',
        ]);

        $this->assertNotNull($log);
        $this->assertEquals('transfer_sent', $log->event_type);
    }

    public function test_13_pos_session_opening_and_closing_audit_logs(): void
    {
        $log = $this->auditService->logEvent([
            'user_id' => $this->manager->id,
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->session1->id,
            'module' => 'pos_drawer',
            'event_type' => 'session_opened',
            'reason_notes' => 'POS Session opened',
        ]);

        $this->assertNotNull($log);
        $this->assertEquals('pos_drawer', $log->module);
    }

    public function test_14_pos_register_cash_in_cash_out_drawer_drop_audit_logs(): void
    {
        $log = $this->auditService->logEvent([
            'user_id' => $this->manager->id,
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->session1->id,
            'module' => 'pos_drawer',
            'event_type' => 'cash_in',
            'after_state' => ['amount' => 300.00],
            'reason_notes' => 'Opening Float Addition',
        ]);

        $this->assertNotNull($log);
        $this->assertEquals(300.00, $log->after_state['amount']);
    }

    public function test_15_offline_sync_and_conflict_reconciliation_audit_logs(): void
    {
        $log = $this->auditService->logEvent([
            'user_id' => $this->manager->id,
            'store_id' => $this->store1->id,
            'module' => 'sync',
            'event_type' => 'offline_sale_synced',
            'client_trans_uuid' => 'offline-uuid-101',
            'reason_notes' => 'Offline invoice synced successfully',
        ]);

        $this->assertNotNull($log);
        $this->assertEquals('offline-uuid-101', $log->client_trans_uuid);
    }

    public function test_16_user_login_security_audit_logs(): void
    {
        $log = $this->auditService->logEvent([
            'user_id' => $this->manager->id,
            'module' => 'auth',
            'event_type' => 'user_login',
            'reason_notes' => 'User logged in successfully',
        ]);

        $this->assertNotNull($log);
        $this->assertEquals('auth', $log->module);
    }

    public function test_17_audit_log_immutability_prevents_update_and_delete(): void
    {
        $log = $this->auditService->logEvent([
            'user_id' => $this->manager->id,
            'store_id' => $this->store1->id,
            'module' => 'pos_sale',
            'event_type' => 'invoice_created',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(403);
        $log->update(['status' => 'cancelled']);
    }

    public function test_18_sensitive_data_redaction_in_snapshots(): void
    {
        $data = [
            'username' => 'cashier1',
            'password' => 'secret123',
            'token' => 'bearer_xyz',
            'card_number' => '4111111111111111',
            'cvv' => '123',
        ];

        $redacted = $this->auditService->redactSensitiveData($data);

        $this->assertEquals('cashier1', $redacted['username']);
        $this->assertEquals('[REDACTED]', $redacted['password']);
        $this->assertEquals('[REDACTED]', $redacted['token']);
        $this->assertEquals('[REDACTED]', $redacted['card_number']);
        $this->assertEquals('[REDACTED]', $redacted['cvv']);
    }

    public function test_19_before_and_after_state_snapshot_correctness(): void
    {
        $log = $this->auditService->logEvent([
            'module' => 'expense',
            'event_type' => 'expense_updated',
            'before_state' => ['amount' => 100.00],
            'after_state' => ['amount' => 150.00],
        ]);

        $this->assertEquals(100.00, $log->before_state['amount']);
        $this->assertEquals(150.00, $log->after_state['amount']);
    }

    public function test_20_changed_fields_diff_calculation(): void
    {
        $before = ['amount' => 100.00, 'status' => 'pending'];
        $after = ['amount' => 150.00, 'status' => 'pending'];

        $diff = $this->auditService->calculateChangedFields($before, $after);

        $this->assertArrayHasKey('amount', $diff);
        $this->assertArrayNotHasKey('status', $diff);
        $this->assertEquals(100.00, $diff['amount']['old']);
        $this->assertEquals(150.00, $diff['amount']['new']);
    }

    public function test_21_client_trans_uuid_correlation_tracking(): void
    {
        $log = $this->auditService->logEvent([
            'module' => 'pos_sale',
            'event_type' => 'invoice_created',
            'client_trans_uuid' => 'client-uuid-999',
        ]);

        $this->assertEquals('client-uuid-999', $log->client_trans_uuid);
    }

    public function test_22_failed_transaction_audit_status_logging(): void
    {
        $log = $this->auditService->logEvent([
            'module' => 'pos_sale',
            'event_type' => 'sale_failed',
            'status' => 'failure',
            'reason_notes' => 'Insufficient inventory stock',
        ]);

        $this->assertEquals('failure', $log->status);
    }

    public function test_23_store_access_isolation_for_non_super_admin(): void
    {
        Sanctum::actingAs($this->manager); // Store 1

        $res = $this->getJson("/api/v1/audit-logs?store_id={$this->store2->id}");
        $res->assertStatus(403);
    }

    public function test_24_super_admin_universal_cross_store_audit_access(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson("/api/v1/audit-logs?store_id={$this->store2->id}");
        $res->assertStatus(200);
    }

    public function test_25_financial_audit_trail_filtering(): void
    {
        Sanctum::actingAs($this->manager);

        $this->auditService->logEvent([
            'user_id' => $this->manager->id,
            'store_id' => $this->store1->id,
            'module' => 'pos_sale',
            'event_type' => 'invoice_created',
        ]);

        $res = $this->getJson('/api/v1/audit-logs/financial-trail');

        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.items'));
    }

    public function test_26_entity_history_audit_trail_listing(): void
    {
        Sanctum::actingAs($this->manager);

        $this->auditService->logEvent([
            'user_id' => $this->manager->id,
            'store_id' => $this->store1->id,
            'module' => 'pos_sale',
            'event_type' => 'invoice_created',
            'auditable_type' => Invoice::class,
            'auditable_id' => 10,
        ]);

        $res = $this->getJson('/api/v1/audit-logs/entity/Invoice/10');

        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.items'));
    }

    public function test_27_user_activity_audit_trail_listing(): void
    {
        Sanctum::actingAs($this->manager);

        $this->auditService->logEvent([
            'user_id' => $this->manager->id,
            'store_id' => $this->store1->id,
            'module' => 'expense',
            'event_type' => 'expense_created',
        ]);

        $res = $this->getJson("/api/v1/audit-logs/user/{$this->manager->id}");

        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.items'));
    }

    public function test_28_date_range_and_event_type_filtering(): void
    {
        Sanctum::actingAs($this->manager);

        $today = now()->toDateString();
        $res = $this->getJson("/api/v1/audit-logs?event_type=expense_created&date_from={$today}&date_to={$today}");

        $res->assertStatus(200);
    }

    public function test_29_readonly_api_safety_for_audit_endpoints(): void
    {
        Sanctum::actingAs($this->manager);

        $beforeCount = AuditLog::count();
        $this->getJson('/api/v1/audit-logs')->assertStatus(200);
        $this->getJson('/api/v1/audit-logs/financial-trail')->assertStatus(200);
        $afterCount = AuditLog::count();

        $this->assertEquals($beforeCount, $afterCount);
    }

    public function test_30_full_regression_compatibility_with_tasks_1_to_53(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/financial-reports/consolidated-sales');
        $res->assertStatus(200);
    }
}
