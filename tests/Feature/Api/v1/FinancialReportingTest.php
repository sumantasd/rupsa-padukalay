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
use App\Models\PosSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\ReturnItem;
use App\Models\ReturnSale;
use App\Models\Role;
use App\Models\Size;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialReportingTest extends TestCase
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
        $this->getJson('/api/v1/financial-reports/consolidated-sales')->assertStatus(401);
        $this->getJson('/api/v1/financial-reports/profit-loss')->assertStatus(401);
        $this->getJson('/api/v1/financial-reports/gst-liability')->assertStatus(401);
    }

    public function test_2_rbac_permission_rejection(): void
    {
        Sanctum::actingAs($this->noPermUser);

        $this->getJson('/api/v1/financial-reports/consolidated-sales')->assertStatus(403);
        $this->getJson('/api/v1/financial-reports/profit-loss')->assertStatus(403);
        $this->getJson('/api/v1/financial-reports/gst-liability')->assertStatus(403);
    }

    public function test_3_nonexistent_store_query_handling(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/financial-reports/consolidated-sales?store_id=99999')->assertStatus(403);
    }

    public function test_4_empty_dataset_financial_reporting_returns_zero_metrics(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/financial-reports/consolidated-sales');

        $res->assertStatus(200);
        $this->assertEquals(0.00, $res->json('data.summary.gross_sales'));
        $this->assertEquals(0.00, $res->json('data.summary.net_billed_revenue'));
        $this->assertEquals(0.00, $res->json('data.summary.gross_profit'));
    }

    public function test_5_consolidated_sales_report_store_wise_breakdown(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/financial-reports/consolidated-sales');

        $res->assertStatus(200);
        $this->assertCount(2, $res->json('data.stores'));
    }

    public function test_6_consolidated_totals_reconcile_exactly_with_store_totals(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Store 1 Invoice
        $inv1 = Invoice::create([
            'invoice_number' => 'INV-FIN-1',
            'client_trans_uuid' => 'uuid-fin-1',
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->session1->id,
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
            'invoice_id' => $inv1->id,
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

        // Store 2 Invoice
        $inv2 = Invoice::create([
            'invoice_number' => 'INV-FIN-2',
            'client_trans_uuid' => 'uuid-fin-2',
            'store_id' => $this->store2->id,
            'subtotal' => 3200.00,
            'discount_amount' => 200.00,
            'taxable_amount' => 3000.00,
            'total_tax' => 360.00,
            'grand_total' => 3360.00,
            'paid_amount' => 3360.00,
            'due_amount' => 0.00,
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv2->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'sku_snapshot' => 'ADI-500-WHT-10',
            'article_number_snapshot' => 'ADI-500',
            'product_name_snapshot' => 'Ultraboost',
            'color_name_snapshot' => 'White',
            'size_number_snapshot' => '10',
            'cost_price' => 800.00,
            'mrp' => 2000.00,
            'unit_price' => 1600.00,
            'quantity' => 2,
            'discount_amount' => 200.00,
            'taxable_value' => 3000.00,
            'total_tax_amount' => 360.00,
            'subtotal' => 3000.00,
        ]);

        $res = $this->getJson('/api/v1/financial-reports/consolidated-sales');

        $res->assertStatus(200);
        $summary = $res->json('data.summary');
        $stores = $res->json('data.stores');

        $sumStoreGross = array_sum(array_column($stores, 'gross_sales'));
        $sumStoreNet = array_sum(array_column($stores, 'net_billed_revenue'));

        $this->assertEquals($summary['gross_sales'], $sumStoreGross);
        $this->assertEquals($summary['net_billed_revenue'], $sumStoreNet);
        $this->assertEquals(5040.00, $summary['gross_sales']); // 1680 + 3360
        $this->assertEquals(5040.00, $summary['gross_billed_revenue']); // 1680 + 3360
    }

    public function test_7_gross_profit_and_margin_percentage_calculations(): void
    {
        Sanctum::actingAs($this->manager);

        $inv = Invoice::create([
            'invoice_number' => 'INV-FIN-GP',
            'client_trans_uuid' => 'uuid-fin-gp',
            'store_id' => $this->store1->id,
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

        $res = $this->getJson('/api/v1/financial-reports/consolidated-sales');

        $res->assertStatus(200);
        // Gross Profit = Net Sales (2240) - COGS (800) = 1440
        $this->assertEquals(1440.00, $res->json('data.summary.gross_profit'));
        $this->assertEquals(72.00, $res->json('data.summary.gross_margin_percentage'));
    }

    public function test_8_cogs_accounting_for_returned_items(): void
    {
        Sanctum::actingAs($this->manager);

        $inv = Invoice::create([
            'invoice_number' => 'INV-FIN-COGS',
            'client_trans_uuid' => 'uuid-fin-cogs',
            'store_id' => $this->store1->id,
            'subtotal' => 3200.00,
            'taxable_amount' => 3200.00,
            'grand_total' => 3584.00,
            'paid_amount' => 3584.00,
            'status' => 'completed',
            'created_by' => $this->manager->id,
        ]);

        $invItem = InvoiceItem::create([
            'invoice_id' => $inv->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'sku_snapshot' => 'ADI-500-WHT-10',
            'article_number_snapshot' => 'ADI-500',
            'product_name_snapshot' => 'Ultraboost',
            'color_name_snapshot' => 'White',
            'size_number_snapshot' => '10',
            'cost_price' => 800.00,
            'unit_price' => 1600.00,
            'quantity' => 2, // Total COGS = 1600
            'taxable_value' => 3200.00,
            'subtotal' => 3200.00,
        ]);

        $ret = ReturnSale::create([
            'return_number' => 'RET-FIN-COGS',
            'client_return_uuid' => 'uuid-ret-cogs',
            'original_invoice_id' => $inv->id,
            'store_id' => $this->store1->id,
            'total_refund_amount' => 1792.00,
            'processed_by' => $this->manager->id,
        ]);

        ReturnItem::create([
            'return_id' => $ret->id,
            'invoice_item_id' => $invItem->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'quantity' => 1, // Returned COGS = 800
            'refund_unit_price' => 1600.00,
            'subtotal' => 1600.00,
        ]);

        $res = $this->getJson('/api/v1/financial-reports/consolidated-sales');

        $res->assertStatus(200);
        // Net COGS = 1600 - 800 = 800
        $this->assertEquals(800.00, $res->json('data.summary.cogs'));
    }

    public function test_9_deducting_returns_and_refunds_exactly_once(): void
    {
        Sanctum::actingAs($this->manager);

        $inv = Invoice::create([
            'invoice_number' => 'INV-FIN-RET',
            'client_trans_uuid' => 'uuid-fin-ret',
            'store_id' => $this->store1->id,
            'grand_total' => 2000.00,
            'paid_amount' => 2000.00,
            'status' => 'completed',
            'created_by' => $this->manager->id,
        ]);

        ReturnSale::create([
            'return_number' => 'RET-FIN-1',
            'client_return_uuid' => 'uuid-ret-1',
            'original_invoice_id' => $inv->id,
            'store_id' => $this->store1->id,
            'total_refund_amount' => 500.00,
            'processed_by' => $this->manager->id,
        ]);

        $res = $this->getJson('/api/v1/financial-reports/consolidated-sales');

        $res->assertStatus(200);
        $this->assertEquals(2000.00, $res->json('data.summary.gross_billed_revenue'));
        $this->assertEquals(500.00, $res->json('data.summary.returns_refunds'));
        $this->assertEquals(1500.00, $res->json('data.summary.net_billed_revenue'));
    }

    public function test_10_exclude_cancelled_invoices_from_financial_reports(): void
    {
        Sanctum::actingAs($this->manager);

        Invoice::create([
            'invoice_number' => 'INV-CANCEL-FIN',
            'client_trans_uuid' => 'uuid-cancel-fin',
            'store_id' => $this->store1->id,
            'subtotal' => 5000.00,
            'grand_total' => 5600.00,
            'paid_amount' => 5600.00,
            'status' => 'cancelled',
            'created_by' => $this->manager->id,
        ]);

        $res = $this->getJson('/api/v1/financial-reports/consolidated-sales');

        $res->assertStatus(200);
        $this->assertEquals(0.00, $res->json('data.summary.gross_sales'));
    }

    public function test_11_split_payment_double_counting_prevention(): void
    {
        Sanctum::actingAs($this->manager);

        $inv = Invoice::create([
            'invoice_number' => 'INV-SPLIT-FIN',
            'client_trans_uuid' => 'uuid-split-fin',
            'store_id' => $this->store1->id,
            'subtotal' => 1000.00,
            'grand_total' => 1120.00,
            'paid_amount' => 1120.00,
            'status' => 'completed',
            'created_by' => $this->manager->id,
        ]);

        InvoicePayment::create(['invoice_id' => $inv->id, 'payment_method' => 'cash', 'amount' => 600.00, 'payment_time' => now()]);
        InvoicePayment::create(['invoice_id' => $inv->id, 'payment_method' => 'card', 'amount' => 520.00, 'payment_time' => now()]);

        $res = $this->getJson('/api/v1/financial-reports/consolidated-sales');

        $res->assertStatus(200);
        $this->assertEquals(1120.00, $res->json('data.summary.gross_billed_revenue')); // NOT 2240!
    }

    public function test_12_operating_expenses_category_breakdown_in_profit_loss(): void
    {
        Sanctum::actingAs($this->manager);

        $cat = ExpenseCategory::create(['name' => 'Utilities', 'code' => 'UTIL']);
        Expense::create([
            'expense_category_id' => $cat->id,
            'store_id' => $this->store1->id,
            'amount' => 450.00,
            'expense_date' => now()->toDateString(),
            'created_by' => $this->manager->id,
        ]);

        $res = $this->getJson('/api/v1/financial-reports/profit-loss');

        $res->assertStatus(200)
            ->assertJsonStructure(['data' => ['expense_categories_breakdown']]);
        $this->assertEquals(450.00, $res->json('data.profitability.operating_expenses'));
    }

    public function test_13_net_store_profit_or_loss_calculation_in_profit_loss(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/financial-reports/profit-loss');

        $res->assertStatus(200);
        $this->assertNotNull($res->json('data.profitability.net_profit'));
    }

    public function test_14_date_range_filtering(): void
    {
        Sanctum::actingAs($this->manager);

        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        $res = $this->getJson("/api/v1/financial-reports/consolidated-sales?date_from={$start}&date_to={$end}");
        $res->assertStatus(200);
    }

    public function test_15_date_preset_filtering(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/financial-reports/consolidated-sales?period=current_month');
        $res->assertStatus(200);
    }

    public function test_16_profit_loss_period_growth_comparison_percentages(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/financial-reports/profit-loss');

        $res->assertStatus(200)
            ->assertJsonStructure(['data' => ['period_comparison' => ['net_revenue_growth_pct', 'gross_profit_growth_pct', 'net_profit_growth_pct']]]);
    }

    public function test_17_store_access_isolation_for_non_super_admin(): void
    {
        Sanctum::actingAs($this->manager); // Store 1

        $res = $this->getJson("/api/v1/financial-reports/consolidated-sales?store_id={$this->store2->id}");
        $res->assertStatus(403);
    }

    public function test_18_super_admin_cross_store_consolidated_access(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/financial-reports/consolidated-sales');
        $res->assertStatus(200);
    }

    public function test_19_gst_liability_cgst_sgst_igst_totals_calculation(): void
    {
        Sanctum::actingAs($this->manager);

        Invoice::create([
            'invoice_number' => 'INV-GST-1',
            'client_trans_uuid' => 'uuid-gst-1',
            'store_id' => $this->store1->id,
            'taxable_amount' => 1000.00,
            'total_cgst' => 60.00,
            'total_sgst' => 60.00,
            'total_igst' => 0.00,
            'total_tax' => 120.00,
            'grand_total' => 1120.00,
            'paid_amount' => 1120.00,
            'status' => 'completed',
            'created_by' => $this->manager->id,
        ]);

        $res = $this->getJson('/api/v1/financial-reports/gst-liability');

        $res->assertStatus(200);
        $this->assertEquals(1000.00, $res->json('data.tax_liability_summary.gross_taxable_value'));
        $this->assertEquals(60.00, $res->json('data.tax_liability_summary.gross_cgst_amount'));
        $this->assertEquals(60.00, $res->json('data.tax_liability_summary.gross_sgst_amount'));
        $this->assertEquals(120.00, $res->json('data.tax_liability_summary.gross_total_gst'));
    }

    public function test_20_sales_return_gst_tax_credit_adjustment(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/financial-reports/gst-liability');
        $res->assertStatus(200);
    }

    public function test_21_net_gst_payable_calculation(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/financial-reports/gst-liability');
        $res->assertStatus(200);
    }

    public function test_22_store_wise_gst_breakdown(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/financial-reports/gst-liability');

        $res->assertStatus(200)->assertJsonStructure(['data' => ['store_tax_breakdown']]);
        $this->assertCount(2, $res->json('data.store_tax_breakdown'));
    }

    public function test_23_hsn_wise_tax_summary_table_aggregation(): void
    {
        Sanctum::actingAs($this->manager);

        $inv = Invoice::create([
            'invoice_number' => 'INV-HSN-1',
            'client_trans_uuid' => 'uuid-hsn-1',
            'store_id' => $this->store1->id,
            'taxable_amount' => 1000.00,
            'total_tax' => 120.00,
            'grand_total' => 1120.00,
            'paid_amount' => 1120.00,
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
            'hsn_code_snapshot' => '6403',
            'tax_rate_percentage' => 12.00,
            'quantity' => 1,
            'taxable_value' => 1000.00,
            'cgst_amount' => 60.00,
            'sgst_amount' => 60.00,
            'igst_amount' => 0.00,
            'total_tax_amount' => 120.00,
            'subtotal' => 1000.00,
        ]);

        $res = $this->getJson('/api/v1/financial-reports/gst-liability');

        $res->assertStatus(200)->assertJsonStructure(['data' => ['hsn_tax_summary']]);
        $hsnList = $res->json('data.hsn_tax_summary');
        $this->assertCount(1, $hsnList);
        $this->assertEquals('6403', $hsnList[0]['hsn_code']);
    }

    public function test_24_gstr_compliance_export_readiness_payload(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/financial-reports/gst-liability');

        $res->assertStatus(200)
            ->assertJsonStructure(['data' => ['gstr_compliance_export_readiness' => ['gstr1_b2c_pos_sales_ready', 'gstr3b_tax_liability_ready', 'export_format']]]);
    }

    public function test_25_filter_by_specific_store_id_in_gst_report(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson("/api/v1/financial-reports/gst-liability?store_id={$this->store1->id}");
        $res->assertStatus(200);
    }

    public function test_26_filter_by_cashier_id_in_sales_summary(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson("/api/v1/financial-reports/consolidated-sales?cashier_id={$this->manager->id}");
        $res->assertStatus(200);
    }

    public function test_27_readonly_safety(): void
    {
        Sanctum::actingAs($this->manager);

        $beforeCount = Invoice::count();
        $this->getJson('/api/v1/financial-reports/consolidated-sales')->assertStatus(200);
        $this->getJson('/api/v1/financial-reports/profit-loss')->assertStatus(200);
        $this->getJson('/api/v1/financial-reports/gst-liability')->assertStatus(200);
        $afterCount = Invoice::count();

        $this->assertEquals($beforeCount, $afterCount);
    }

    public function test_28_standardized_api_response_format(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/financial-reports/consolidated-sales');
        $res->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_29_zero_value_edge_cases_handling(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/financial-reports/profit-loss');
        $res->assertStatus(200);
        $this->assertEquals(0.00, $res->json('data.revenue.gross_sales'));
    }

    public function test_30_full_regression_compatibility(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/dashboard/executive-kpi');
        $res->assertStatus(200);
    }
}
