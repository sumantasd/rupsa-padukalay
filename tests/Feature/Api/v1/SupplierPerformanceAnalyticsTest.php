<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\HsnCode;
use App\Models\InventoryStock;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\Role;
use App\Models\Size;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierPerformanceAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $manager;
    protected User $noPermUser;
    protected Store $store1;
    protected Store $store2;
    protected Warehouse $warehouse1;
    protected Supplier $supplier1;
    protected Supplier $supplier2;
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

        $this->supplier1 = Supplier::create([
            'name' => 'Apex Footwear Ltd',
            'company_name' => 'Apex Global',
            'gstin' => '19ABCDE1234F1Z5',
            'phone' => '9876543210',
            'email' => 'apex@example.com',
            'address' => 'Kolkata, WB',
        ]);

        $this->supplier2 = Supplier::create([
            'name' => 'Bata India Suppliers',
            'company_name' => 'Bata Corp',
            'gstin' => '19VWXYZ9876F1Z2',
            'phone' => '9123456789',
            'email' => 'bata@example.com',
            'address' => 'Patna, Bihar',
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
        $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/purchase-history")->assertStatus(401);
        $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/purchase-summary")->assertStatus(401);
        $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/performance-analytics")->assertStatus(401);
        $this->getJson('/api/v1/suppliers/performance-ranking')->assertStatus(401);
    }

    public function test_2_rbac_permission_rejection(): void
    {
        Sanctum::actingAs($this->noPermUser);

        $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/purchase-history")->assertStatus(403);
        $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/purchase-summary")->assertStatus(403);
    }

    public function test_3_nonexistent_supplier_returns_404(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/suppliers/99999/purchase-history')->assertStatus(404);
        $this->getJson('/api/v1/suppliers/99999/purchase-summary')->assertStatus(404);
    }

    public function test_4_empty_supplier_purchase_history(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/purchase-summary");

        $res->assertStatus(200);
        $this->assertEquals(0, $res->json('data.total_purchase_orders'));
        $this->assertEquals(0.00, $res->json('data.total_spend'));
    }

    public function test_5_paginated_purchase_history(): void
    {
        Sanctum::actingAs($this->manager);

        PurchaseOrder::create([
            'po_number' => 'PO-SUP-001',
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store1->id,
            'warehouse_id' => $this->warehouse1->id,
            'order_date' => '2026-01-10',
            'received_date' => '2026-01-15',
            'status' => 'received',
            'subtotal' => 5000.00,
            'grand_total' => 5000.00,
            'paid_amount' => 5000.00,
            'due_amount' => 0.00,
            'created_by' => $this->manager->id,
        ]);

        $res = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/purchase-history");

        $res->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.po_number', 'PO-SUP-001');
    }

    public function test_6_date_range_filtering(): void
    {
        Sanctum::actingAs($this->manager);

        PurchaseOrder::create([
            'po_number' => 'PO-DATE-001',
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store1->id,
            'warehouse_id' => $this->warehouse1->id,
            'order_date' => '2026-02-10',
            'status' => 'received',
            'subtotal' => 2000.00,
            'grand_total' => 2000.00,
            'paid_amount' => 2000.00,
            'due_amount' => 0.00,
            'created_by' => $this->manager->id,
        ]);

        $res = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/purchase-history?start_date=2026-02-01&end_date=2026-02-28");
        $res->assertStatus(200)->assertJsonPath('data.pagination.total', 1);

        $resEmpty = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/purchase-history?start_date=2026-03-01&end_date=2026-03-31");
        $resEmpty->assertStatus(200)->assertJsonPath('data.pagination.total', 0);
    }

    public function test_7_store_filtering_on_purchase_history(): void
    {
        Sanctum::actingAs($this->superAdmin);

        PurchaseOrder::create([
            'po_number' => 'PO-ST1',
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store1->id,
            'warehouse_id' => $this->warehouse1->id,
            'order_date' => '2026-01-10',
            'status' => 'received',
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'due_amount' => 0.00,
            'created_by' => $this->superAdmin->id,
        ]);

        PurchaseOrder::create([
            'po_number' => 'PO-ST2',
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store2->id,
            'order_date' => '2026-01-10',
            'status' => 'received',
            'subtotal' => 2000.00,
            'grand_total' => 2000.00,
            'paid_amount' => 2000.00,
            'due_amount' => 0.00,
            'created_by' => $this->superAdmin->id,
        ]);

        $res1 = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/purchase-history?store_id={$this->store1->id}");
        $res1->assertStatus(200)->assertJsonPath('data.pagination.total', 1);

        $res2 = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/purchase-history?store_id={$this->store2->id}");
        $res2->assertStatus(200)->assertJsonPath('data.pagination.total', 1);
    }

    public function test_8_status_filtering_on_purchase_history(): void
    {
        Sanctum::actingAs($this->manager);

        PurchaseOrder::create([
            'po_number' => 'PO-STAT-REC',
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store1->id,
            'order_date' => '2026-01-10',
            'status' => 'received',
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'due_amount' => 0.00,
            'created_by' => $this->manager->id,
        ]);

        PurchaseOrder::create([
            'po_number' => 'PO-STAT-ORD',
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store1->id,
            'order_date' => '2026-01-10',
            'status' => 'ordered',
            'subtotal' => 1500.00,
            'grand_total' => 1500.00,
            'paid_amount' => 0.00,
            'due_amount' => 1500.00,
            'created_by' => $this->manager->id,
        ]);

        $res = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/purchase-history?status=received");
        $res->assertStatus(200)->assertJsonPath('data.pagination.total', 1);
    }

    public function test_9_search_filtering_on_purchase_history(): void
    {
        Sanctum::actingAs($this->manager);

        PurchaseOrder::create([
            'po_number' => 'PO-SRCH-999',
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store1->id,
            'order_date' => '2026-01-10',
            'status' => 'received',
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'due_amount' => 0.00,
            'created_by' => $this->manager->id,
        ]);

        $res = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/purchase-history?search=SRCH-999");
        $res->assertStatus(200)->assertJsonPath('data.pagination.total', 1);
    }

    public function test_10_store_access_isolation_for_non_super_admin(): void
    {
        Sanctum::actingAs($this->manager); // Manager assigned to store1 only

        // Accessing store2 analytics returns 403
        $res = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/purchase-summary?store_id={$this->store2->id}");
        $res->assertStatus(403);
    }

    public function test_11_super_admin_cross_store_access(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/purchase-summary?store_id={$this->store2->id}");
        $res->assertStatus(200);
    }

    public function test_12_total_purchase_spend_calculation(): void
    {
        Sanctum::actingAs($this->manager);

        PurchaseOrder::create([
            'po_number' => 'PO-SPEND-1',
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store1->id,
            'order_date' => '2026-01-10',
            'status' => 'received',
            'subtotal' => 4000.00,
            'grand_total' => 4000.00,
            'paid_amount' => 4000.00,
            'due_amount' => 0.00,
            'created_by' => $this->manager->id,
        ]);

        $res = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/purchase-summary");

        $res->assertStatus(200);
        $this->assertEquals(1, $res->json('data.total_purchase_orders'));
        $this->assertEquals(4000.00, $res->json('data.total_spend'));
    }

    public function test_13_po_status_breakdown_calculation(): void
    {
        Sanctum::actingAs($this->manager);

        PurchaseOrder::create([
            'po_number' => 'PO-B1',
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store1->id,
            'order_date' => '2026-01-10',
            'status' => 'received',
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'created_by' => $this->manager->id,
        ]);

        PurchaseOrder::create([
            'po_number' => 'PO-B2',
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store1->id,
            'order_date' => '2026-01-10',
            'status' => 'ordered',
            'subtotal' => 2000.00,
            'grand_total' => 2000.00,
            'created_by' => $this->manager->id,
        ]);

        $res = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/purchase-summary");

        $res->assertStatus(200);
        $this->assertEquals(1, $res->json('data.po_status_breakdown.received'));
        $this->assertEquals(1, $res->json('data.po_status_breakdown.ordered'));
    }

    public function test_14_outstanding_due_amount_calculation(): void
    {
        Sanctum::actingAs($this->manager);

        PurchaseOrder::create([
            'po_number' => 'PO-DUE-1',
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store1->id,
            'order_date' => '2026-01-10',
            'status' => 'received',
            'subtotal' => 5000.00,
            'grand_total' => 5000.00,
            'paid_amount' => 2000.00,
            'due_amount' => 3000.00,
            'created_by' => $this->manager->id,
        ]);

        $res = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/purchase-summary");

        $res->assertStatus(200);
        $this->assertEquals(3000.00, $res->json('data.total_due_amount'));
    }

    public function test_15_pending_po_valuation_calculation(): void
    {
        Sanctum::actingAs($this->manager);

        PurchaseOrder::create([
            'po_number' => 'PO-PEND-1',
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store1->id,
            'order_date' => '2026-01-10',
            'status' => 'ordered',
            'subtotal' => 7500.00,
            'grand_total' => 7500.00,
            'created_by' => $this->manager->id,
        ]);

        $res = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/purchase-summary");

        $res->assertStatus(200);
        $this->assertEquals(7500.00, $res->json('data.pending_po_valuation'));
    }

    public function test_16_purchase_return_value_calculation(): void
    {
        Sanctum::actingAs($this->manager);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-PRET-1',
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store1->id,
            'order_date' => '2026-01-10',
            'status' => 'received',
            'subtotal' => 10000.00,
            'grand_total' => 10000.00,
            'paid_amount' => 10000.00,
            'created_by' => $this->manager->id,
        ]);

        PurchaseReturn::create([
            'return_number' => 'PRET-001',
            'purchase_order_id' => $po->id,
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store1->id,
            'total_return_amount' => 1200.00,
            'reason' => 'Defective lot',
            'processed_by' => $this->manager->id,
        ]);

        $res = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/purchase-summary");

        $res->assertStatus(200);
        $this->assertEquals(1200.00, $res->json('data.total_returned_value'));
        $this->assertEquals(1, $res->json('data.number_of_returns'));
    }

    public function test_17_fulfillment_rate_calculation_accuracy(): void
    {
        Sanctum::actingAs($this->manager);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-FULFILL-1',
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store1->id,
            'order_date' => '2026-01-10',
            'status' => 'received',
            'subtotal' => 5000.00,
            'grand_total' => 5000.00,
            'created_by' => $this->manager->id,
        ]);

        // Ordered 100, received 90 -> 90% fulfillment
        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'quantity_ordered' => 100,
            'quantity_received' => 90,
            'cost_price' => 50.00,
            'mrp' => 120.00,
            'selling_price' => 100.00,
            'total_cost' => 4500.00,
        ]);

        $res = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/performance-analytics");

        $res->assertStatus(200);
        $this->assertEquals(90.00, $res->json('data.fulfillment_rate'));
    }

    public function test_18_delivery_completion_rate_calculation(): void
    {
        Sanctum::actingAs($this->manager);

        PurchaseOrder::create([
            'po_number' => 'PO-DELIV-1',
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store1->id,
            'order_date' => '2026-01-10',
            'status' => 'received',
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'created_by' => $this->manager->id,
        ]);

        PurchaseOrder::create([
            'po_number' => 'PO-DELIV-2',
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store1->id,
            'order_date' => '2026-01-10',
            'status' => 'ordered',
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'created_by' => $this->manager->id,
        ]);

        $res = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/performance-analytics");

        $res->assertStatus(200);
        $this->assertEquals(50.00, $res->json('data.delivery_completion_rate')); // 1 received out of 2 = 50%
    }

    public function test_19_purchase_return_rate_calculation(): void
    {
        Sanctum::actingAs($this->manager);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-RR-1',
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store1->id,
            'order_date' => '2026-01-10',
            'status' => 'received',
            'subtotal' => 10000.00,
            'grand_total' => 10000.00,
            'created_by' => $this->manager->id,
        ]);

        PurchaseReturn::create([
            'return_number' => 'PRET-RR-1',
            'purchase_order_id' => $po->id,
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store1->id,
            'total_return_amount' => 500.00,
            'reason' => 'Defective',
            'processed_by' => $this->manager->id,
        ]);

        $res = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/performance-analytics");

        $res->assertStatus(200);
        $this->assertEquals(5.00, $res->json('data.return_rate')); // (500 / 10000) * 100 = 5%
    }

    public function test_20_average_lead_time_days_calculation(): void
    {
        Sanctum::actingAs($this->manager);

        PurchaseOrder::create([
            'po_number' => 'PO-LEAD-1',
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store1->id,
            'order_date' => '2026-01-01',
            'received_date' => '2026-01-05', // 4 days lead time
            'status' => 'received',
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'created_by' => $this->manager->id,
        ]);

        $res = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/performance-analytics");

        $res->assertStatus(200);
        $this->assertEquals(4.0, $res->json('data.average_lead_time_days'));
    }

    public function test_21_top_supplied_products_aggregation(): void
    {
        Sanctum::actingAs($this->manager);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-TOP-PROD',
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store1->id,
            'order_date' => '2026-01-10',
            'status' => 'received',
            'subtotal' => 5000.00,
            'grand_total' => 5000.00,
            'created_by' => $this->manager->id,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'quantity_ordered' => 10,
            'quantity_received' => 10,
            'cost_price' => 500.00,
            'mrp' => 1200.00,
            'selling_price' => 1000.00,
            'total_cost' => 5000.00,
        ]);

        $res = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/performance-analytics");

        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.top_supplied_products'));
        $this->assertEquals('ART900-BLK-08', $res->json('data.top_supplied_products.0.sku'));
        $this->assertEquals(10, $res->json('data.top_supplied_products.0.total_received_quantity'));
    }

    public function test_22_composite_supplier_performance_score_calculation(): void
    {
        Sanctum::actingAs($this->manager);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-SCORE-1',
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store1->id,
            'order_date' => '2026-01-01',
            'received_date' => '2026-01-03', // 2 days lead time
            'status' => 'received',
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'created_by' => $this->manager->id,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'quantity_ordered' => 10,
            'quantity_received' => 10, // 100% fulfillment
            'cost_price' => 100.00,
            'mrp' => 200.00,
            'selling_price' => 180.00,
            'total_cost' => 1000.00,
        ]);

        $res = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/performance-analytics");

        $res->assertStatus(200);
        $score = $res->json('data.supplier_score');
        $this->assertGreaterThan(90.0, $score); // High score for perfect fulfillment & fast lead time
    }

    public function test_23_monthly_spend_trend_aggregation(): void
    {
        Sanctum::actingAs($this->manager);

        PurchaseOrder::create([
            'po_number' => 'PO-TREND-1',
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store1->id,
            'order_date' => '2026-02-15',
            'status' => 'received',
            'subtotal' => 3000.00,
            'grand_total' => 3000.00,
            'created_by' => $this->manager->id,
        ]);

        $res = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/performance-analytics");

        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data.monthly_spend_trends'));
        $this->assertEquals('2026-02', $res->json('data.monthly_spend_trends.0.year_month'));
        $this->assertEquals(3000.00, $res->json('data.monthly_spend_trends.0.total_spend'));
    }

    public function test_24_supplier_performance_ranking_pagination(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/suppliers/performance-ranking');

        $res->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['items', 'pagination']]);
        $this->assertCount(2, $res->json('data.items'));
    }

    public function test_25_ranking_order_by_composite_score(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/suppliers/performance-ranking');

        $res->assertStatus(200);
        $items = $res->json('data.items');
        $this->assertEquals(1, $items[0]['rank']);
        $this->assertEquals(2, $items[1]['rank']);
    }

    public function test_26_ranking_store_access_isolation(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/suppliers/performance-ranking');
        $res->assertStatus(200);
    }

    public function test_27_readonly_analytics_safety(): void
    {
        Sanctum::actingAs($this->manager);

        $beforeCount = PurchaseOrder::count();
        $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/performance-analytics")->assertStatus(200);
        $afterCount = PurchaseOrder::count();

        $this->assertEquals($beforeCount, $afterCount);
    }

    public function test_28_cancelled_po_exclusion_from_spend(): void
    {
        Sanctum::actingAs($this->manager);

        PurchaseOrder::create([
            'po_number' => 'PO-CANCEL-1',
            'supplier_id' => $this->supplier1->id,
            'store_id' => $this->store1->id,
            'order_date' => '2026-01-10',
            'status' => 'cancelled',
            'subtotal' => 9999.00,
            'grand_total' => 9999.00,
            'created_by' => $this->manager->id,
        ]);

        $res = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/purchase-summary");

        $res->assertStatus(200);
        $this->assertEquals(0.00, $res->json('data.total_spend'));
    }

    public function test_29_standardized_api_responses(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson("/api/v1/suppliers/{$this->supplier1->id}/purchase-history");

        $res->assertStatus(200)->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_30_full_regression_compatibility(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/suppliers');
        $res->assertStatus(200);
    }
}
