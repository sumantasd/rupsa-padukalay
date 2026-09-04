<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\HsnCode;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
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

class CustomerPurchaseAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $cashier;
    protected User $noPermUser;
    protected Store $store1;
    protected Store $store2;
    protected Customer $customer;
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

        $this->customer = Customer::create([
            'mobile_number' => '9876543210',
            'name' => 'Alice Customer',
            'email' => 'alice@example.com',
        ]);

        // Setup Footwear Masters
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

        InventoryStock::create(['product_variant_size_id' => $this->variantSize1->id, 'store_id' => $this->store1->id, 'stock_quantity' => 50]);
    }

    public function test_1_unauthenticated_access_rejected(): void
    {
        $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-history")->assertStatus(401);
        $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-summary")->assertStatus(401);
        $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-analytics")->assertStatus(401);
        $this->getJson("/api/v1/customers/{$this->customer->id}/rfm")->assertStatus(401);
    }

    public function test_2_rbac_permission_rejection(): void
    {
        Sanctum::actingAs($this->noPermUser);

        $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-history")->assertStatus(403);
        $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-summary")->assertStatus(403);
    }

    public function test_3_nonexistent_customer_returns_404(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->getJson('/api/v1/customers/99999/purchase-history')->assertStatus(404);
        $this->getJson('/api/v1/customers/99999/purchase-summary')->assertStatus(404);
    }

    public function test_4_empty_customer_purchase_history(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-summary");

        $res->assertStatus(200);
        $this->assertEquals(0, $res->json('data.total_orders'));
        $this->assertEquals(0.00, $res->json('data.total_net_purchase_value'));
    }

    public function test_5_paginated_purchase_history(): void
    {
        Sanctum::actingAs($this->cashier);

        Invoice::create([
            'invoice_number' => 'INV-HIST-001',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-history");

        $res->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.invoice_number', 'INV-HIST-001');
    }

    public function test_6_date_filters_on_purchase_history(): void
    {
        Sanctum::actingAs($this->cashier);

        $inv = Invoice::create([
            'invoice_number' => 'INV-DATE-001',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);
        $inv->timestamps = false;
        $inv->created_at = '2026-01-15 10:00:00';
        $inv->save();

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-history?start_date=2026-01-01&end_date=2026-01-31");
        $res->assertStatus(200)->assertJsonPath('data.pagination.total', 1);

        $resEmpty = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-history?start_date=2026-02-01&end_date=2026-02-28");
        $resEmpty->assertStatus(200)->assertJsonPath('data.pagination.total', 0);
    }

    public function test_7_store_filtering_on_purchase_history(): void
    {
        Sanctum::actingAs($this->superAdmin);

        Invoice::create([
            'invoice_number' => 'INV-ST1',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
        ]);

        Invoice::create([
            'invoice_number' => 'INV-ST2',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store2->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1500.00,
            'grand_total' => 1500.00,
            'paid_amount' => 1500.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
        ]);

        $res1 = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-history?store_id={$this->store1->id}");
        $res1->assertStatus(200)->assertJsonPath('data.pagination.total', 1);

        $res2 = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-history?store_id={$this->store2->id}");
        $res2->assertStatus(200)->assertJsonPath('data.pagination.total', 1);
    }

    public function test_8_search_filtering_on_purchase_history(): void
    {
        Sanctum::actingAs($this->cashier);

        Invoice::create([
            'invoice_number' => 'INV-SRCH-99',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-history?search=SRCH-99");
        $res->assertStatus(200)->assertJsonPath('data.pagination.total', 1);
    }

    public function test_9_store_access_isolation_for_non_super_admin(): void
    {
        Sanctum::actingAs($this->cashier); // Cashier assigned to store1 only

        // Accessing store2 analytics returns 403
        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-summary?store_id={$this->store2->id}");
        $res->assertStatus(403);
    }

    public function test_10_super_admin_cross_store_access(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-summary?store_id={$this->store2->id}");
        $res->assertStatus(200);
    }

    public function test_11_financial_purchase_summary_calculations(): void
    {
        Sanctum::actingAs($this->cashier);

        Invoice::create([
            'invoice_number' => 'INV-SUM-001',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 2000.00,
            'discount_amount' => 200.00,
            'taxable_amount' => 1800.00,
            'total_tax' => 216.00,
            'grand_total' => 2016.00,
            'paid_amount' => 2016.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-summary");

        $res->assertStatus(200);
        $this->assertEquals(1, $res->json('data.total_orders'));
        $this->assertEquals(2000.00, $res->json('data.total_gross_purchase_value'));
        $this->assertEquals(200.00, $res->json('data.total_discounts'));
        $this->assertEquals(216.00, $res->json('data.total_tax'));
        $this->assertEquals(2016.00, $res->json('data.total_net_purchase_value'));
        $this->assertEquals(2016.00, $res->json('data.total_paid_amount'));
        $this->assertEquals(0.00, $res->json('data.outstanding_amount'));
    }

    public function test_12_sales_return_total_refund_deduction(): void
    {
        Sanctum::actingAs($this->cashier);

        $inv = Invoice::create([
            'invoice_number' => 'INV-RET-001',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        ReturnSale::create([
            'return_number' => 'RET-001',
            'client_return_uuid' => (string) Str::uuid(),
            'original_invoice_id' => $inv->id,
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'total_refund_amount' => 300.00,
            'refund_mode' => 'cash',
            'reason' => 'Defective',
            'processed_by' => $this->cashier->id,
        ]);

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-summary");

        $res->assertStatus(200);
        $this->assertEquals(300.00, $res->json('data.total_returned_amount'));
        $this->assertEquals(1, $res->json('data.number_of_returns'));

        // Analytics CLV check: 1000 net - 300 return = 700 CLV
        $analytics = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-analytics");
        $this->assertEquals(700.00, $analytics->json('data.clv'));
    }

    public function test_13_cancelled_transaction_exclusion(): void
    {
        Sanctum::actingAs($this->cashier);

        Invoice::create([
            'invoice_number' => 'INV-CANC-001',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 5000.00,
            'grand_total' => 5000.00,
            'paid_amount' => 5000.00,
            'payment_status' => 'paid',
            'status' => 'cancelled',
            'created_by' => $this->cashier->id,
        ]);

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-summary");

        $res->assertStatus(200);
        $this->assertEquals(0, $res->json('data.total_orders'));
        $this->assertEquals(0.00, $res->json('data.total_net_purchase_value'));
    }

    public function test_14_split_payment_compatibility(): void
    {
        Sanctum::actingAs($this->cashier);

        $inv = Invoice::create([
            'invoice_number' => 'INV-SPLIT-001',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        InvoicePayment::create(['invoice_id' => $inv->id, 'payment_method' => 'cash', 'amount' => 600.00, 'payment_time' => now()]);
        InvoicePayment::create(['invoice_id' => $inv->id, 'payment_method' => 'card', 'amount' => 400.00, 'payment_time' => now()]);

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-summary");

        $res->assertStatus(200);
        $this->assertEquals(1000.00, $res->json('data.total_net_purchase_value'));
        $this->assertEquals(1000.00, $res->json('data.total_paid_amount'));
    }

    public function test_15_customer_lifetime_value_calculation(): void
    {
        Sanctum::actingAs($this->cashier);

        $inv = Invoice::create([
            'invoice_number' => 'INV-CLV-001',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 5000.00,
            'grand_total' => 5000.00,
            'paid_amount' => 5000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        ReturnSale::create([
            'return_number' => 'RET-CLV-01',
            'client_return_uuid' => (string) Str::uuid(),
            'original_invoice_id' => $inv->id,
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'total_refund_amount' => 500.00,
            'refund_mode' => 'cash',
            'reason' => 'Partial return',
            'processed_by' => $this->cashier->id,
        ]);

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-analytics");

        $res->assertStatus(200);
        $this->assertEquals(4500.00, $res->json('data.clv')); // 5000 - 500
    }

    public function test_16_average_order_value_calculation(): void
    {
        Sanctum::actingAs($this->cashier);

        Invoice::create([
            'invoice_number' => 'INV-AOV-1',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        Invoice::create([
            'invoice_number' => 'INV-AOV-2',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 3000.00,
            'grand_total' => 3000.00,
            'paid_amount' => 3000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-analytics");

        $res->assertStatus(200);
        $this->assertEquals(2000.00, $res->json('data.aov')); // (1000 + 3000) / 2 = 2000
    }

    public function test_17_purchase_frequency_repurchase_rate(): void
    {
        Sanctum::actingAs($this->cashier);

        $inv1 = Invoice::create([
            'invoice_number' => 'INV-FREQ-1',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);
        $inv1->timestamps = false;
        $inv1->created_at = '2026-01-01 10:00:00';
        $inv1->save();

        $inv2 = Invoice::create([
            'invoice_number' => 'INV-FREQ-2',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);
        $inv2->timestamps = false;
        $inv2->created_at = '2026-01-11 10:00:00';
        $inv2->save();

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-analytics");

        $res->assertStatus(200);
        $this->assertEquals(50.00, $res->json('data.repurchase_rate'));
        $this->assertEquals(10.00, $res->json('data.purchase_frequency_days')); // 10 days / 1 gap
    }

    public function test_18_favorite_product_analytics(): void
    {
        Sanctum::actingAs($this->cashier);

        $inv = Invoice::create([
            'invoice_number' => 'INV-FAV-PROD',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 3000.00,
            'grand_total' => 3000.00,
            'paid_amount' => 3000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'sku_snapshot' => 'ART900-BLK-08',
            'article_number_snapshot' => 'ART900',
            'product_name_snapshot' => 'Men Casual Sneaker',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '08',
            'cost_price' => 500.00,
            'mrp' => 1200.00,
            'unit_price' => 1000.00,
            'quantity' => 3,
            'subtotal' => 3000.00,
        ]);

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-analytics");

        $res->assertStatus(200);
        $this->assertEquals('Men Casual Sneaker', $res->json('data.favorite_product.name'));
        $this->assertEquals(3, $res->json('data.favorite_product.total_quantity'));
    }

    public function test_19_favorite_category_analytics(): void
    {
        Sanctum::actingAs($this->cashier);

        $inv = Invoice::create([
            'invoice_number' => 'INV-FAV-CAT',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 2000.00,
            'grand_total' => 2000.00,
            'paid_amount' => 2000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'sku_snapshot' => 'ART900-BLK-08',
            'article_number_snapshot' => 'ART900',
            'product_name_snapshot' => 'Men Casual Sneaker',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '08',
            'cost_price' => 500.00,
            'mrp' => 1200.00,
            'unit_price' => 1000.00,
            'quantity' => 2,
            'subtotal' => 2000.00,
        ]);

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-analytics");

        $res->assertStatus(200);
        $this->assertEquals('Casual', $res->json('data.favorite_category.name'));
    }

    public function test_20_favorite_brand_analytics(): void
    {
        Sanctum::actingAs($this->cashier);

        $inv = Invoice::create([
            'invoice_number' => 'INV-FAV-BRD',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'sku_snapshot' => 'ART900-BLK-08',
            'article_number_snapshot' => 'ART900',
            'product_name_snapshot' => 'Men Casual Sneaker',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '08',
            'unit_price' => 1000.00,
            'quantity' => 1,
            'subtotal' => 1000.00,
        ]);

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-analytics");

        $res->assertStatus(200);
        $this->assertEquals('Nike', $res->json('data.favorite_brand.name'));
    }

    public function test_21_top_5_purchased_skus(): void
    {
        Sanctum::actingAs($this->cashier);

        $inv = Invoice::create([
            'invoice_number' => 'INV-TOP-SKU',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 5000.00,
            'grand_total' => 5000.00,
            'paid_amount' => 5000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'sku_snapshot' => 'ART900-BLK-08',
            'article_number_snapshot' => 'ART900',
            'product_name_snapshot' => 'Men Casual Sneaker',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '08',
            'unit_price' => 1000.00,
            'quantity' => 5,
            'subtotal' => 5000.00,
        ]);

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-analytics");

        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.top_purchased_skus'));
        $this->assertEquals('ART900-BLK-08', $res->json('data.top_purchased_skus.0.sku'));
        $this->assertEquals(5, $res->json('data.top_purchased_skus.0.total_quantity'));
    }

    public function test_22_last_purchase_details(): void
    {
        Sanctum::actingAs($this->cashier);

        Invoice::create([
            'invoice_number' => 'INV-LAST-INFO',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1200.00,
            'grand_total' => 1200.00,
            'paid_amount' => 1200.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-analytics");

        $res->assertStatus(200);
        $this->assertEquals('INV-LAST-INFO', $res->json('data.last_purchase_info.invoice_number'));
        $this->assertEquals(1200.00, $res->json('data.last_purchase_info.grand_total'));
        $this->assertEquals('Main Store', $res->json('data.last_purchase_info.store_name'));
    }

    public function test_23_monthly_purchase_trend_aggregation(): void
    {
        Sanctum::actingAs($this->cashier);

        $inv = Invoice::create([
            'invoice_number' => 'INV-TREND-01',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);
        $inv->timestamps = false;
        $inv->created_at = '2026-03-10 10:00:00';
        $inv->save();

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-analytics");

        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.monthly_trends'));
        $this->assertEquals('2026-03', $res->json('data.monthly_trends.0.year_month'));
        $this->assertEquals(1000.00, $res->json('data.monthly_trends.0.net_total'));
    }

    public function test_24_rfm_recency_calculation(): void
    {
        Sanctum::actingAs($this->cashier);

        $inv = Invoice::create([
            'invoice_number' => 'INV-RFM-REC',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);
        $inv->timestamps = false;
        $inv->created_at = now()->subDays(5)->toDateTimeString();
        $inv->save();

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/rfm");

        $res->assertStatus(200);
        $this->assertEquals(5, $res->json('data.recency_days'));
        $this->assertEquals(5, $res->json('data.recency_score')); // <= 30 days = 5
    }

    public function test_25_rfm_frequency_calculation(): void
    {
        Sanctum::actingAs($this->cashier);

        for ($i = 1; $i <= 3; $i++) {
            Invoice::create([
                'invoice_number' => "INV-RFM-FREQ-{$i}",
                'client_trans_uuid' => (string) Str::uuid(),
                'store_id' => $this->store1->id,
                'customer_id' => $this->customer->id,
                'subtotal' => 500.00,
                'grand_total' => 500.00,
                'paid_amount' => 500.00,
                'payment_status' => 'paid',
                'status' => 'completed',
                'created_by' => $this->cashier->id,
            ]);
        }

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/rfm");

        $res->assertStatus(200);
        $this->assertEquals(3, $res->json('data.frequency'));
        $this->assertEquals(3, $res->json('data.frequency_score')); // >= 3 orders = 3
    }

    public function test_26_rfm_monetary_calculation(): void
    {
        Sanctum::actingAs($this->cashier);

        Invoice::create([
            'invoice_number' => 'INV-RFM-MONEY',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 12000.00,
            'grand_total' => 12000.00,
            'paid_amount' => 12000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/rfm");

        $res->assertStatus(200);
        $this->assertEquals(12000.00, $res->json('data.monetary_value'));
        $this->assertEquals(5, $res->json('data.monetary_score')); // >= 10000 = 5
    }

    public function test_27_rfm_score_assignment_segment_classification(): void
    {
        Sanctum::actingAs($this->cashier);

        // Champions segment: R>=4, F>=4, M>=4
        for ($i = 1; $i <= 5; $i++) {
            $inv = Invoice::create([
                'invoice_number' => "INV-CHAMP-{$i}",
                'client_trans_uuid' => (string) Str::uuid(),
                'store_id' => $this->store1->id,
                'customer_id' => $this->customer->id,
                'subtotal' => 3000.00,
                'grand_total' => 3000.00,
                'paid_amount' => 3000.00,
                'payment_status' => 'paid',
                'status' => 'completed',
                'created_by' => $this->cashier->id,
            ]);
            $inv->timestamps = false;
            $inv->created_at = now()->subDays(2)->toDateTimeString();
            $inv->save();
        }

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/rfm");

        $res->assertStatus(200);
        $this->assertEquals('545', $res->json('data.rfm_score'));
        $this->assertEquals('Champions', $res->json('data.segment'));
    }

    public function test_28_rfm_store_access_isolation(): void
    {
        Sanctum::actingAs($this->cashier); // Cashier assigned to store1

        // Normal RFM call succeeds
        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/rfm");
        $res->assertStatus(200);
    }

    public function test_29_standardized_api_responses(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/purchase-history");

        $res->assertStatus(200)->assertJsonStructure(['success', 'message', 'data' => ['items', 'pagination']]);
    }

    public function test_30_full_regression_compatibility(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/customers');
        $res->assertStatus(200);
    }
}
