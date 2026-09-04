<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\HsnCode;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Permission;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminReportsFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $cashier;
    protected User $noPermUser;
    protected Store $store1;
    protected Store $store2;
    protected ProductVariantSize $variantSize1;

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

        $cashierRole = Role::create(['name' => 'Cashier', 'guard_name' => 'web']);
        $cashierRole->permissions()->attach([$permView->id, $permCreate->id]);

        $this->store1 = Store::create(['code' => 'ST-001', 'name' => 'Main Store', 'is_active' => true]);
        $this->store2 = Store::create(['code' => 'ST-002', 'name' => 'Branch Store', 'is_active' => true]);

        $this->cashier = User::create([
            'name' => 'John Cashier',
            'username' => 'john_cashier',
            'email' => 'cashier@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->cashier->roles()->attach($cashierRole->id, ['model_type' => User::class]);
        $this->cashier->stores()->attach($this->store1->id);

        $this->noPermUser = User::create([
            'name' => 'No Perm User',
            'username' => 'noperm_user',
            'email' => 'noperm@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $hsn = HsnCode::create(['code' => '6403', 'description' => 'Footwear', 'default_gst_rate' => 12.00]);
        $brand = Brand::create(['name' => 'Nike', 'slug' => 'nike']);
        $cat = Category::create(['name' => 'Casual', 'slug' => 'casual', 'hsn_code_id' => $hsn->id]);
        $product = Product::create(['article_number' => 'ART900', 'name' => 'Men Casual Sneaker', 'slug' => 'men-casual-sneaker', 'brand_id' => $brand->id, 'category_id' => $cat->id, 'hsn_code_id' => $hsn->id]);
        $color = Color::create(['name' => 'Black', 'code' => 'BLK']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color_id' => $color->id]);
        $size8 = Size::create(['size_number' => '08']);

        $this->variantSize1 = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $size8->id,
            'sku' => 'ART900-BLK-08',
            'cost_price' => 500.00,
            'mrp' => 1200.00,
            'selling_price' => 1000.00,
        ]);

        InventoryStock::create([
            'product_variant_size_id' => $this->variantSize1->id,
            'store_id' => $this->store1->id,
            'warehouse_id' => 0,
            'stock_location_id' => 0,
            'stock_quantity' => 20,
        ]);

        // Seed Sales Invoices
        $inv1 = Invoice::create([
            'invoice_number' => 'INV-RPT-001',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'subtotal' => 2000.00,
            'discount_amount' => 100.00,
            'is_gst_enabled' => true,
            'taxable_amount' => 1900.00,
            'total_cgst' => 114.00,
            'total_sgst' => 114.00,
            'total_igst' => 0.00,
            'total_tax' => 228.00,
            'grand_total' => 2128.00,
            'paid_amount' => 2128.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
            'created_at' => now(),
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv1->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'sku_snapshot' => 'ART900-BLK-08',
            'article_number_snapshot' => 'ART900',
            'product_name_snapshot' => 'Men Casual Sneaker',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '08',
            'hsn_code_snapshot' => '6403',
            'cost_price' => 500.00,
            'mrp' => 1200.00,
            'unit_price' => 1000.00,
            'quantity' => 2,
            'discount_amount' => 100.00,
            'tax_rate_percentage' => 12.00,
            'taxable_value' => 1900.00,
            'cgst_amount' => 114.00,
            'sgst_amount' => 114.00,
            'total_tax_amount' => 228.00,
            'subtotal' => 1900.00,
        ]);

        InvoicePayment::create([
            'invoice_id' => $inv1->id,
            'payment_method' => 'cash',
            'amount' => 1000.00,
            'payment_time' => now(),
        ]);

        InvoicePayment::create([
            'invoice_id' => $inv1->id,
            'payment_method' => 'upi',
            'amount' => 1128.00,
            'payment_time' => now(),
        ]);

        // Invoice for Store 2
        $inv2 = Invoice::create([
            'invoice_number' => 'INV-RPT-002',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store2->id,
            'subtotal' => 1000.00,
            'discount_amount' => 0.00,
            'is_gst_enabled' => true,
            'taxable_amount' => 1000.00,
            'total_cgst' => 60.00,
            'total_sgst' => 60.00,
            'total_igst' => 0.00,
            'total_tax' => 120.00,
            'grand_total' => 1120.00,
            'paid_amount' => 1120.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
            'created_at' => now(),
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv2->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'sku_snapshot' => 'ART900-BLK-08',
            'article_number_snapshot' => 'ART900',
            'product_name_snapshot' => 'Men Casual Sneaker',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '08',
            'hsn_code_snapshot' => '6403',
            'cost_price' => 500.00,
            'mrp' => 1200.00,
            'unit_price' => 1000.00,
            'quantity' => 1,
            'discount_amount' => 0.00,
            'tax_rate_percentage' => 12.00,
            'taxable_value' => 1000.00,
            'cgst_amount' => 60.00,
            'sgst_amount' => 60.00,
            'total_tax_amount' => 120.00,
            'subtotal' => 1000.00,
        ]);

        InvoicePayment::create([
            'invoice_id' => $inv2->id,
            'payment_method' => 'card',
            'amount' => 1120.00,
            'payment_time' => now(),
        ]);
    }

    public function test_1_unauthenticated_access_rejected(): void
    {
        $this->getJson('/api/v1/reports/sales-summary')->assertStatus(401);
        $this->getJson('/api/v1/reports/store-performance')->assertStatus(401);
        $this->getJson('/api/v1/reports/stock-valuation')->assertStatus(401);
        $this->getJson('/api/v1/reports/tax-summary')->assertStatus(401);
    }

    public function test_2_permission_rbac_rejection(): void
    {
        Sanctum::actingAs($this->noPermUser);

        $this->getJson('/api/v1/reports/sales-summary')->assertStatus(403);
        $this->getJson('/api/v1/reports/store-performance')->assertStatus(403);
        $this->getJson('/api/v1/reports/stock-valuation')->assertStatus(403);
        $this->getJson('/api/v1/reports/tax-summary')->assertStatus(403);
    }

    public function test_3_sales_summary_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson('/api/v1/reports/sales-summary');

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_sales_count' => 1,
                    'total_subtotal' => 2000.00,
                    'total_discount' => 100.00,
                    'total_tax' => 228.00,
                    'total_grand_total' => 2128.00,
                    'total_paid_amount' => 2128.00,
                ],
            ]);
    }

    public function test_4_sales_date_filtering_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $today = now()->format('Y-m-d');
        $res = $this->getJson("/api/v1/reports/sales-summary?date_from={$today}&date_to={$today}");

        $res->assertStatus(200)->assertJsonPath('data.total_sales_count', 1);

        $future = now()->addDays(10)->format('Y-m-d');
        $resEmpty = $this->getJson("/api/v1/reports/sales-summary?date_from={$future}");

        $resEmpty->assertStatus(200)->assertJsonPath('data.total_sales_count', 0);
    }

    public function test_5_sales_store_filtering_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $resS1 = $this->getJson("/api/v1/reports/sales-summary?store_id={$this->store1->id}");
        $resS1->assertStatus(200)->assertJsonPath('data.total_grand_total', 2128);

        $resS2 = $this->getJson("/api/v1/reports/sales-summary?store_id={$this->store2->id}");
        $resS2->assertStatus(200)->assertJsonPath('data.total_grand_total', 1120);
    }

    public function test_6_sales_totals_are_accurate(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson('/api/v1/reports/sales-summary');

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'total_taxable_amount' => 1900.00,
                    'total_cgst' => 114.00,
                    'total_sgst' => 114.00,
                    'total_tax' => 228.00,
                    'total_grand_total' => 2128.00,
                ],
            ]);
    }

    public function test_7_split_payment_sales_are_not_double_counted(): void
    {
        Sanctum::actingAs($this->cashier);

        // inv1 has 2 payment entries (1000 cash + 1128 upi = 2128 total)
        // Grand total must be 2128, NOT multiplied by 2
        $res = $this->getJson('/api/v1/reports/sales-summary');

        $res->assertStatus(200)
            ->assertJsonPath('data.total_grand_total', 2128)
            ->assertJsonPath('data.total_paid_amount', 2128);
    }

    public function test_8_store_performance_report_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/reports/store-performance');

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_stores' => 2,
                ],
            ]);
    }

    public function test_9_store_access_isolation_works(): void
    {
        Sanctum::actingAs($this->cashier);

        // Cashier assigned to store1 only
        $res = $this->getJson('/api/v1/reports/store-performance');

        $res->assertStatus(200)
            ->assertJsonCount(1, 'data.stores')
            ->assertJsonPath('data.stores.0.store_id', $this->store1->id);
    }

    public function test_10_unauthorized_store_access_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        // Cashier attempts to filter store2 (unauthorized)
        $res = $this->getJson("/api/v1/reports/sales-summary?store_id={$this->store2->id}");

        // For non-Super Admin, sales-summary filters query to assigned stores, returning 0 sales for store2
        $res->assertStatus(200)->assertJsonPath('data.total_sales_count', 0);
    }

    public function test_11_super_admin_cross_store_reporting_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/reports/sales-summary');

        // Super Admin gets inv1 (2128) + inv2 (1120) = 3248 total
        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'total_sales_count' => 2,
                    'total_grand_total' => 3248.00,
                ],
            ]);
    }

    public function test_12_stock_valuation_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson('/api/v1/reports/stock-valuation');

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'summary' => [
                        'total_items_count' => 1,
                        'total_stock_quantity' => 20,
                        'total_inventory_cost_value' => 10000.00, // 20 * 500
                        'total_inventory_mrp_value' => 24000.00,  // 20 * 1200
                        'total_inventory_selling_value' => 20000.00, // 20 * 1000
                    ],
                ],
            ]);
    }

    public function test_13_stock_valuation_does_not_modify_inventory(): void
    {
        Sanctum::actingAs($this->cashier);

        $qtyBefore = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first()->stock_quantity;

        $this->getJson('/api/v1/reports/stock-valuation')->assertStatus(200);

        $qtyAfter = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first()->stock_quantity;
        $this->assertEquals($qtyBefore, $qtyAfter);
    }

    public function test_14_gst_summary_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/reports/tax-summary');

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'total_invoices_count' => 2,
                    'taxable_value' => 2900.00,
                    'cgst_amount' => 174.00,
                    'sgst_amount' => 174.00,
                    'total_tax_amount' => 348.00,
                ],
            ]);
    }

    public function test_15_gst_date_filtering_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $today = now()->format('Y-m-d');
        $res = $this->getJson("/api/v1/reports/tax-summary?date_from={$today}&date_to={$today}");

        $res->assertStatus(200)->assertJsonPath('data.total_invoices_count', 2);
    }

    public function test_16_gst_store_filtering_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson("/api/v1/reports/tax-summary?store_id={$this->store1->id}");

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'total_invoices_count' => 1,
                    'taxable_value' => 1900.00,
                    'total_tax_amount' => 228.00,
                ],
            ]);
    }

    public function test_17_empty_report_result_handled_correctly(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $future = now()->addDays(30)->format('Y-m-d');
        $res = $this->getJson("/api/v1/reports/sales-summary?date_from={$future}");

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'total_sales_count' => 0,
                    'total_grand_total' => 0.00,
                    'total_paid_amount' => 0.00,
                ],
            ]);
    }

    public function test_18_invalid_filter_validation(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson('/api/v1/reports/sales-summary?date_from=invalid-date-format');

        $res->assertStatus(422)->assertJsonStructure(['message', 'errors' => ['date_from']]);
    }

    public function test_19_nonexistent_store_handling(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/reports/sales-summary?store_id=999999');

        $res->assertStatus(422)->assertJsonStructure(['message', 'errors' => ['store_id']]);
    }

    public function test_20_standardized_api_responses(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson('/api/v1/reports/sales-summary');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);
    }

    public function test_21_report_endpoints_remain_read_only(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $invCountBefore = Invoice::count();
        $stockQtyBefore = InventoryStock::first()->stock_quantity;

        $this->getJson('/api/v1/reports/sales-summary');
        $this->getJson('/api/v1/reports/store-performance');
        $this->getJson('/api/v1/reports/stock-valuation');
        $this->getJson('/api/v1/reports/tax-summary');

        $this->assertEquals($invCountBefore, Invoice::count());
        $this->assertEquals($stockQtyBefore, InventoryStock::first()->stock_quantity);
    }

    public function test_22_regression_compatibility(): void
    {
        Sanctum::actingAs($this->cashier);

        // Standard POS sale view from Task #37 works
        $this->getJson('/api/v1/pos/sales')->assertStatus(200);
    }

    public function test_23_purchase_summary_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson('/api/v1/reports/purchases-summary');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_purchases',
                    'purchase_bills_count',
                    'pending_pos_count',
                    'purchase_returns_total',
                    'outstanding_supplier_amount',
                ],
            ]);
    }

    public function test_24_customer_summary_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson('/api/v1/reports/customers-summary');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_customers',
                    'total_purchase_amount',
                    'total_paid_amount',
                    'total_outstanding_amount',
                    'total_loyalty_points',
                ],
            ]);
    }

    public function test_25_payment_summary_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson('/api/v1/reports/payments-summary');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'cash',
                    'card',
                    'upi',
                    'bank',
                    'other',
                    'total_collections',
                    'total_refunds',
                ],
            ]);
    }

    public function test_26_item_wise_sales_report_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/reports/item-wise-sales');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_items_types',
                        'total_qty_sold',
                        'total_gross_sales',
                        'total_discount',
                        'total_net_sales',
                        'total_cogs',
                        'total_gross_profit',
                        'total_margin_pct',
                    ],
                    'items' => [
                        '*' => [
                            'sku',
                            'article_number',
                            'product_name',
                            'color',
                            'size',
                            'qty_sold',
                            'gross_sales',
                            'discount',
                            'net_sales',
                            'cogs',
                            'gross_profit',
                            'margin_pct',
                            'is_profit',
                        ],
                    ],
                ],
            ]);
    }

    public function test_27_date_wise_sales_report_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/reports/date-wise-sales?group_by=day');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_periods',
                        'total_invoices',
                        'total_gross_sales',
                        'total_discount',
                        'total_net_sales',
                    ],
                    'items' => [
                        '*' => [
                            'period_label',
                            'invoice_count',
                            'gross_sales',
                            'discount',
                            'returns',
                            'net_sales',
                        ],
                    ],
                ],
            ]);
    }

    public function test_28_date_wise_payments_report_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/reports/date-wise-payments?group_by=day');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_cash',
                        'total_card',
                        'total_upi',
                        'total_bank',
                        'total_other',
                        'total_collection',
                    ],
                    'items' => [
                        '*' => [
                            'period_label',
                            'cash',
                            'card',
                            'upi',
                            'bank',
                            'other',
                            'total_collection',
                            'refund',
                            'net_collection',
                        ],
                    ],
                ],
            ]);
    }

    public function test_29_date_wise_profit_loss_report_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/reports/date-wise-profit-loss?group_by=day');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary' => [
                        'total_net_sales',
                        'total_cogs',
                        'total_gross_profit_loss',
                        'is_profit',
                        'total_expenses',
                        'total_net_profit_loss',
                        'total_net_margin_pct',
                    ],
                    'items' => [
                        '*' => [
                            'period_label',
                            'gross_sales',
                            'discount',
                            'returns',
                            'net_sales',
                            'cogs',
                            'gross_profit_loss',
                            'is_profit',
                            'expenses',
                            'net_profit_loss',
                            'net_margin_pct',
                        ],
                    ],
                ],
            ]);
    }

    public function test_30_empty_store_id_param_does_not_filter_out_sales(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Sending empty string ?store_id= should NOT evaluate as store_id = 0
        $res = $this->getJson('/api/v1/reports/sales-summary?store_id=');

        $res->assertStatus(200)
            ->assertJsonPath('data.total_sales_count', 2);
    }

    public function test_31_yesterday_date_filtering_captures_all_day_transactions(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $yesterdayDate = now()->subDay()->format('Y-m-d');

        // Create an invoice created yesterday at 23:55:00
        $inv = Invoice::create([
            'invoice_number' => 'INV-YEST-999',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'subtotal' => 500.00,
            'discount_amount' => 0.00,
            'is_gst_enabled' => false,
            'taxable_amount' => 500.00,
            'total_tax' => 0.00,
            'grand_total' => 500.00,
            'paid_amount' => 500.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
        ]);

        \Illuminate\Support\Facades\DB::table('invoices')->where('id', $inv->id)->update([
            'created_at' => \Carbon\Carbon::parse($yesterdayDate . ' 23:55:00')->toDateTimeString(),
        ]);

        $res = $this->getJson("/api/v1/reports/sales-summary?date_from={$yesterdayDate}&date_to={$yesterdayDate}");

        $res->assertStatus(200)
            ->assertJsonPath('data.total_sales_count', 1)
            ->assertJsonPath('data.total_grand_total', 500);
    }

    public function test_32_cross_report_financial_reconciliation(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $today = now()->format('Y-m-d');

        $salesRes = $this->getJson("/api/v1/reports/sales-summary?date_from={$today}&date_to={$today}")->json('data');
        $plRes = $this->getJson("/api/v1/financial-reports/profit-loss?date_from={$today}&date_to={$today}")->json('data');
        $itemWiseRes = $this->getJson("/api/v1/reports/item-wise-sales?date_from={$today}&date_to={$today}")->json('data');
        $paymentsRes = $this->getJson("/api/v1/reports/payments-summary?date_from={$today}&date_to={$today}")->json('data');

        // Assert Gross Sales match across Sales Summary & P&L
        $salesGross = (float) $salesRes['total_grand_total'];
        $plGross = (float) $plRes['revenue']['gross_sales'];
        $this->assertEquals($salesGross, $plGross, "Gross Sales mismatch: Sales Summary ({$salesGross}) vs P&L ({$plGross})");

        // Assert Returns match across Sales Summary & P&L
        $salesReturns = (float) ($salesRes['total_returns'] ?? 0.0);
        $plReturns = (float) $plRes['revenue']['returns'];
        $this->assertEquals($salesReturns, $plReturns, "Returns mismatch: Sales Summary ({$salesReturns}) vs P&L ({$plReturns})");

        // Assert Net Sales match across Sales Summary & P&L
        $salesNet = (float) $salesRes['net_sales'];
        $plNet = (float) $plRes['revenue']['net_sales'];
        $this->assertEquals($salesNet, $plNet, "Net Sales mismatch: Sales Summary ({$salesNet}) vs P&L ({$plNet})");

        // Assert Total Items Sold match between Sales Summary & Item-Wise Sales
        $salesItems = (int) $salesRes['total_items_sold'];
        $itemWiseItems = (int) $itemWiseRes['summary']['total_qty_sold'];
        $this->assertEquals($salesItems, $itemWiseItems, "Total Items Qty mismatch: Sales Summary ({$salesItems}) vs Item-Wise ({$itemWiseItems})");

        // Assert Payment totals reconcile with total paid collections
        $totalPaidCollections = (float) $paymentsRes['total_collections'];
        $calculatedPayments = (float) $paymentsRes['cash'] + (float) $paymentsRes['card'] + (float) $paymentsRes['upi'] + (float) $paymentsRes['bank'] + (float) $paymentsRes['other'];
        $this->assertEquals($totalPaidCollections, $calculatedPayments, "Payment total collection mismatch: sum of payment modes ({$calculatedPayments}) vs total collections ({$totalPaidCollections})");
    }
}



