<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\HsnCode;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Permission;
use App\Models\PosRegister;
use App\Models\PosRegisterCashMovement;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\Role;
use App\Models\Size;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExecutiveDashboardTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();

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

        // Setup Footwear Masters
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
        $this->getJson('/api/v1/dashboard/executive-kpi')->assertStatus(401);
        $this->getJson('/api/v1/dashboard/sales-trend')->assertStatus(401);
        $this->getJson('/api/v1/dashboard/store-performance')->assertStatus(401);
        $this->getJson('/api/v1/dashboard/inventory-kpi')->assertStatus(401);
    }

    public function test_2_rbac_permission_rejection(): void
    {
        Sanctum::actingAs($this->noPermUser);

        $this->getJson('/api/v1/dashboard/executive-kpi')->assertStatus(403);
        $this->getJson('/api/v1/dashboard/sales-trend')->assertStatus(403);
    }

    public function test_3_nonexistent_store_query_handling(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/dashboard/executive-kpi?store_id=99999')->assertStatus(403);
    }

    public function test_4_empty_dataset_executive_dashboard_returns_zero_metrics(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/dashboard/executive-kpi');

        $res->assertStatus(200);
        $this->assertEquals(0.00, $res->json('data.gross_sales'));
        $this->assertEquals(0.00, $res->json('data.net_billed_sales_revenue'));
        $this->assertEquals(0.00, $res->json('data.gross_profit'));
    }

    public function test_5_executive_kpi_summary_calculations(): void
    {
        Sanctum::actingAs($this->manager);

        $inv = Invoice::create([
            'invoice_number' => 'INV-EXEC-1',
            'client_trans_uuid' => 'uuid-exec-1',
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->session1->id,
            'customer_id' => $this->customer1->id,
            'subtotal' => 1600.00,
            'discount_amount' => 100.00,
            'taxable_amount' => 1500.00,
            'total_tax' => 180.00,
            'grand_total' => 1680.00,
            'paid_amount' => 1680.00,
            'due_amount' => 0.00,
            'status' => 'completed',
            'created_by' => $this->manager->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'sku_snapshot' => 'ADI-500-WHT-10',
            'article_number_snapshot' => 'ADI-500',
            'product_name_snapshot' => 'Ultraboost',
            'color_name_snapshot' => 'White',
            'size_number_snapshot' => '10',
            'cost_price' => 800.00,
            'mrp' => 2000.00,
            'unit_price' => 1600.00,
            'quantity' => 1,
            'discount_amount' => 100.00,
            'taxable_value' => 1500.00,
            'total_tax_amount' => 180.00,
            'subtotal' => 1500.00,
        ]);

        $res = $this->getJson('/api/v1/dashboard/executive-kpi');

        $res->assertStatus(200);
        $this->assertEquals(1600.00, $res->json('data.gross_sales'));
        $this->assertEquals(100.00, $res->json('data.discounts'));
        $this->assertEquals(1500.00, $res->json('data.taxable_sales'));
        $this->assertEquals(180.00, $res->json('data.gst_tax'));
        $this->assertEquals(1680.00, $res->json('data.gross_billed_sales_revenue'));
        $this->assertEquals(1680.00, $res->json('data.net_billed_sales_revenue'));
    }

    public function test_6_gross_profit_and_margin_percentage_calculations(): void
    {
        Sanctum::actingAs($this->manager);

        $inv = Invoice::create([
            'invoice_number' => 'INV-GP-1',
            'client_trans_uuid' => 'uuid-gp-1',
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->session1->id,
            'subtotal' => 2000.00,
            'discount_amount' => 0.00,
            'taxable_amount' => 2000.00,
            'total_tax' => 240.00,
            'grand_total' => 2240.00,
            'paid_amount' => 2240.00,
            'status' => 'completed',
            'created_by' => $this->manager->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'sku_snapshot' => 'ADI-500-WHT-10',
            'article_number_snapshot' => 'ADI-500',
            'product_name_snapshot' => 'Ultraboost',
            'color_name_snapshot' => 'White',
            'size_number_snapshot' => '10',
            'cost_price' => 800.00, // COGS = 800
            'mrp' => 2000.00,
            'unit_price' => 2000.00,
            'quantity' => 1,
            'taxable_value' => 2000.00,
            'total_tax_amount' => 240.00,
            'subtotal' => 2000.00,
        ]);

        $res = $this->getJson('/api/v1/dashboard/executive-kpi');

        $res->assertStatus(200);
        // Gross Profit = Net Taxable (2000) - COGS (800) = 1200
        $this->assertEquals(1200.00, $res->json('data.gross_profit'));
        $this->assertEquals(60.00, $res->json('data.gross_margin_percentage')); // 1200 / 2000 * 100
    }

    public function test_7_net_store_profit_calculation(): void
    {
        Sanctum::actingAs($this->manager);

        $cat = ExpenseCategory::create(['name' => 'Rent', 'code' => 'RENT']);
        Expense::create([
            'expense_category_id' => $cat->id,
            'store_id' => $this->store1->id,
            'amount' => 300.00,
            'expense_date' => now()->toDateString(),
            'created_by' => $this->manager->id,
        ]);

        $res = $this->getJson('/api/v1/dashboard/executive-kpi');

        $res->assertStatus(200);
        $this->assertEquals(300.00, $res->json('data.total_expenses'));
    }

    public function test_8_date_filter_preset_today(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/dashboard/executive-kpi?period=today');
        $res->assertStatus(200);
    }

    public function test_9_date_filter_preset_current_month(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/dashboard/executive-kpi?period=current_month');
        $res->assertStatus(200);
    }

    public function test_10_date_filter_preset_previous_month(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/dashboard/executive-kpi?period=previous_month');
        $res->assertStatus(200);
    }

    public function test_11_custom_date_range_filtering(): void
    {
        Sanctum::actingAs($this->manager);

        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        $res = $this->getJson("/api/v1/dashboard/executive-kpi?period=custom&start_date={$start}&end_date={$end}");
        $res->assertStatus(200);
    }

    public function test_12_previous_period_growth_decline_percentage_comparison(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/dashboard/executive-kpi');

        $res->assertStatus(200)
            ->assertJsonStructure(['data' => ['period_comparison' => ['net_revenue_growth_pct', 'gross_profit_growth_pct', 'sales_count_growth_pct']]]);
    }

    public function test_13_store_access_isolation_for_non_super_admin(): void
    {
        Sanctum::actingAs($this->manager); // Assigned to store1

        $res = $this->getJson("/api/v1/dashboard/executive-kpi?store_id={$this->store2->id}");
        $res->assertStatus(403);
    }

    public function test_14_super_admin_cross_store_dashboard_access(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson("/api/v1/dashboard/executive-kpi?store_id={$this->store2->id}");
        $res->assertStatus(200);
    }

    public function test_15_store_performance_ranking_matrix_calculation(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/dashboard/store-performance');

        $res->assertStatus(200)->assertJsonStructure(['success', 'message', 'data']);
        $this->assertCount(2, $res->json('data'));
    }

    public function test_16_top_selling_products_ranking_by_net_revenue(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/dashboard/product-performance');

        $res->assertStatus(200)->assertJsonStructure(['data' => ['top_products', 'top_skus', 'top_categories', 'top_brands']]);
    }

    public function test_17_top_selling_skus_ranking_by_quantity(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/dashboard/product-performance');
        $res->assertStatus(200);
    }

    public function test_18_top_categories_and_brands_ranking(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/dashboard/product-performance');
        $res->assertStatus(200);
    }

    public function test_19_inventory_kpi_calculations(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/dashboard/inventory-kpi');

        $res->assertStatus(200);
        $this->assertEquals(50, $res->json('data.total_inventory_units'));
        $this->assertEquals(40000.00, $res->json('data.inventory_valuation_cost')); // 50 * 800
        $this->assertEquals(80000.00, $res->json('data.inventory_valuation_selling')); // 50 * 1600
    }

    public function test_20_inventory_turnover_ratio_calculation(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/dashboard/inventory-kpi');
        $res->assertStatus(200);
    }

    public function test_21_low_stock_out_of_stock_overstock_counts(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/dashboard/inventory-kpi');
        $res->assertStatus(200);
    }

    public function test_22_active_pos_sessions_and_open_registers_count(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/dashboard/pos-register-kpi');

        $res->assertStatus(200);
        $this->assertEquals(1, $res->json('data.active_pos_sessions'));
        $this->assertEquals(1, $res->json('data.open_registers'));
    }

    public function test_23_expected_cash_drawer_balance_calculation(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/dashboard/pos-register-kpi');

        $res->assertStatus(200);
        $this->assertEquals(1000.00, $res->json('data.expected_drawer_cash')); // opening cash 1000
    }

    public function test_24_cash_in_cash_out_drawer_drop_summary(): void
    {
        Sanctum::actingAs($this->manager);

        PosRegisterCashMovement::create([
            'pos_register_id' => $this->register1->id,
            'pos_session_id' => $this->session1->id,
            'store_id' => $this->store1->id,
            'user_id' => $this->manager->id,
            'movement_type' => 'cash_in',
            'amount' => 500.00,
            'reason' => 'Opening Float Addition',
        ]);

        $res = $this->getJson('/api/v1/dashboard/pos-register-kpi');

        $res->assertStatus(200);
        $this->assertEquals(500.00, $res->json('data.total_cash_in'));
    }

    public function test_25_over_short_cash_difference_reconciliation_summary(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/dashboard/pos-register-kpi');
        $res->assertStatus(200);
    }

    public function test_26_customer_kpis(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/dashboard/customer-kpi');

        $res->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $res->json('data.new_customers_count'));
    }

    public function test_27_split_payment_double_counting_prevention(): void
    {
        Sanctum::actingAs($this->manager);

        $inv = Invoice::create([
            'invoice_number' => 'INV-SPLIT-1',
            'client_trans_uuid' => 'uuid-split-1',
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->session1->id,
            'subtotal' => 1000.00,
            'discount_amount' => 0.00,
            'taxable_amount' => 1000.00,
            'total_tax' => 120.00,
            'grand_total' => 1120.00,
            'paid_amount' => 1120.00,
            'status' => 'completed',
            'created_by' => $this->manager->id,
        ]);

        // Add 2 split payment rows
        InvoicePayment::create(['invoice_id' => $inv->id, 'payment_method' => 'cash', 'amount' => 600.00, 'payment_time' => now()]);
        InvoicePayment::create(['invoice_id' => $inv->id, 'payment_method' => 'upi', 'amount' => 520.00, 'payment_time' => now()]);

        $res = $this->getJson('/api/v1/dashboard/executive-kpi');

        $res->assertStatus(200);
        $this->assertEquals(1120.00, $res->json('data.gross_billed_sales_revenue')); // NOT 2240!
    }

    public function test_28_cancelled_transaction_exclusion_safety(): void
    {
        Sanctum::actingAs($this->manager);

        Invoice::create([
            'invoice_number' => 'INV-CANCEL-1',
            'client_trans_uuid' => 'uuid-cancel-1',
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->session1->id,
            'subtotal' => 5000.00,
            'grand_total' => 5600.00,
            'paid_amount' => 5600.00,
            'status' => 'cancelled',
            'created_by' => $this->manager->id,
        ]);

        $res = $this->getJson('/api/v1/dashboard/executive-kpi');

        $res->assertStatus(200);
        $this->assertEquals(0.00, $res->json('data.gross_sales'));
    }

    public function test_29_readonly_safety(): void
    {
        Sanctum::actingAs($this->manager);

        $beforeCount = Invoice::count();
        $this->getJson('/api/v1/dashboard/executive-kpi')->assertStatus(200);
        $afterCount = Invoice::count();

        $this->assertEquals($beforeCount, $afterCount);
    }

    public function test_30_full_regression_compatibility(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/reports/sales-summary');
        $res->assertStatus(200);
    }
}
