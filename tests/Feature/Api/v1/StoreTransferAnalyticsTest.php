<?php

namespace Tests\Feature\Api\v1;

use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\HsnCode;
use App\Models\InventoryStock;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\Role;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StoreTransferAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $manager;
    protected User $noPermUser;
    protected Store $store1;
    protected Store $store2;
    protected Warehouse $warehouse1;
    protected Warehouse $warehouse2;
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
        $this->warehouse2 = Warehouse::create(['code' => 'WH-002', 'name' => 'Branch Warehouse', 'store_id' => $this->store2->id, 'is_active' => true]);

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

        // Setup Footwear Masters
        $hsn = HsnCode::create(['code' => '6403', 'description' => 'Footwear', 'default_gst_rate' => 12.00]);
        $brand = Brand::create(['name' => 'Puma', 'slug' => 'puma']);
        $cat = Category::create(['name' => 'Sports', 'slug' => 'sports', 'hsn_code_id' => $hsn->id]);
        $product = Product::create(['article_number' => 'PUMA-700', 'name' => 'Running Shoe', 'slug' => 'running-shoe', 'brand_id' => $brand->id, 'category_id' => $cat->id, 'hsn_code_id' => $hsn->id]);
        $color = Color::create(['name' => 'Red', 'code' => 'RED']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color_id' => $color->id]);
        $size9 = Size::create(['size_number' => '09']);

        $this->variantSize1 = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $size9->id,
            'sku' => 'PUMA-700-RED-09',
            'cost_price' => 600.00,
            'mrp' => 1500.00,
            'selling_price' => 1200.00,
        ]);

        InventoryStock::create(['product_variant_size_id' => $this->variantSize1->id, 'store_id' => $this->store1->id, 'stock_quantity' => 100, 'reorder_level' => 20]);
        InventoryStock::create(['product_variant_size_id' => $this->variantSize1->id, 'store_id' => $this->store2->id, 'stock_quantity' => 10, 'reorder_level' => 15]);
    }

    public function test_1_unauthenticated_access_rejected(): void
    {
        $this->getJson('/api/v1/transfers/analytics/summary')->assertStatus(401);
        $this->getJson('/api/v1/transfers/analytics/matrix')->assertStatus(401);
        $this->getJson('/api/v1/inventory/turnover-analytics')->assertStatus(401);
        $this->getJson('/api/v1/inventory/cross-store-balance')->assertStatus(401);
    }

    public function test_2_rbac_permission_rejection(): void
    {
        Sanctum::actingAs($this->noPermUser);

        $this->getJson('/api/v1/transfers/analytics/summary')->assertStatus(403);
        $this->getJson('/api/v1/inventory/turnover-analytics')->assertStatus(403);
    }

    public function test_3_nonexistent_store_query_handling(): void
    {
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/transfers/analytics/summary?store_id=99999')->assertStatus(403);
    }

    public function test_4_empty_transfer_analytics(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/transfers/analytics/summary');

        $res->assertStatus(200);
        $this->assertEquals(0, $res->json('data.total_transfers'));
        $this->assertEquals(0.00, $res->json('data.total_transfer_valuation'));
    }

    public function test_5_transfer_summary_calculations(): void
    {
        Sanctum::actingAs($this->manager);

        $tr = StockTransfer::create([
            'transfer_number' => 'TR-001',
            'from_store_id' => $this->store1->id,
            'from_warehouse_id' => $this->warehouse1->id,
            'to_store_id' => $this->store2->id,
            'to_warehouse_id' => $this->warehouse2->id,
            'status' => 'completed',
            'transfer_date' => '2026-01-10 10:00:00',
            'received_date' => '2026-01-10 14:00:00', // 4 hours transit
            'transferred_by' => $this->manager->id,
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $tr->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'quantity_sent' => 20,
            'quantity_received' => 20,
        ]);

        $res = $this->getJson('/api/v1/transfers/analytics/summary');

        $res->assertStatus(200);
        $this->assertEquals(1, $res->json('data.total_transfers'));
        $this->assertEquals(20, $res->json('data.total_volume_sent'));
        $this->assertEquals(20, $res->json('data.total_volume_received'));
        $this->assertEquals(12000.00, $res->json('data.total_transfer_valuation')); // 20 * 600
    }

    public function test_6_date_range_filtering(): void
    {
        Sanctum::actingAs($this->manager);

        StockTransfer::create([
            'transfer_number' => 'TR-DATE-1',
            'from_store_id' => $this->store1->id,
            'to_store_id' => $this->store2->id,
            'status' => 'completed',
            'transfer_date' => '2026-02-10 10:00:00',
            'transferred_by' => $this->manager->id,
        ]);

        $res1 = $this->getJson('/api/v1/transfers/analytics/summary?start_date=2026-02-01&end_date=2026-02-28');
        $res1->assertStatus(200)->assertJsonPath('data.total_transfers', 1);

        $res2 = $this->getJson('/api/v1/transfers/analytics/summary?start_date=2026-03-01&end_date=2026-03-31');
        $res2->assertStatus(200)->assertJsonPath('data.total_transfers', 0);
    }

    public function test_7_source_store_filtering(): void
    {
        Sanctum::actingAs($this->superAdmin);

        StockTransfer::create([
            'transfer_number' => 'TR-SRC-1',
            'from_store_id' => $this->store1->id,
            'to_store_id' => $this->store2->id,
            'status' => 'completed',
            'transfer_date' => '2026-01-10 10:00:00',
            'transferred_by' => $this->superAdmin->id,
        ]);

        $res = $this->getJson("/api/v1/transfers/analytics/summary?from_store_id={$this->store1->id}");
        $res->assertStatus(200)->assertJsonPath('data.total_transfers', 1);
    }

    public function test_8_destination_store_filtering(): void
    {
        Sanctum::actingAs($this->superAdmin);

        StockTransfer::create([
            'transfer_number' => 'TR-DST-1',
            'from_store_id' => $this->store1->id,
            'to_store_id' => $this->store2->id,
            'status' => 'completed',
            'transfer_date' => '2026-01-10 10:00:00',
            'transferred_by' => $this->superAdmin->id,
        ]);

        $res = $this->getJson("/api/v1/transfers/analytics/summary?to_store_id={$this->store2->id}");
        $res->assertStatus(200)->assertJsonPath('data.total_transfers', 1);
    }

    public function test_9_transfer_status_filtering(): void
    {
        Sanctum::actingAs($this->manager);

        StockTransfer::create([
            'transfer_number' => 'TR-STAT-1',
            'from_store_id' => $this->store1->id,
            'to_store_id' => $this->store2->id,
            'status' => 'in_transit',
            'transfer_date' => '2026-01-10 10:00:00',
            'transferred_by' => $this->manager->id,
        ]);

        $res = $this->getJson('/api/v1/transfers/analytics/summary?status=in_transit');
        $res->assertStatus(200)->assertJsonPath('data.total_transfers', 1);
    }

    public function test_10_store_access_isolation_for_non_super_admin(): void
    {
        Sanctum::actingAs($this->manager); // Assigned to store1

        $res = $this->getJson("/api/v1/transfers/analytics/summary?from_store_id={$this->store2->id}");
        $res->assertStatus(403);
    }

    public function test_11_super_admin_cross_store_access(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson("/api/v1/transfers/analytics/summary?from_store_id={$this->store2->id}");
        $res->assertStatus(200);
    }

    public function test_12_transfer_velocity_calculation(): void
    {
        Sanctum::actingAs($this->manager);

        $tr = StockTransfer::create([
            'transfer_number' => 'TR-VEL-1',
            'from_store_id' => $this->store1->id,
            'to_store_id' => $this->store2->id,
            'status' => 'completed',
            'transfer_date' => '2026-01-10 10:00:00',
            'transferred_by' => $this->manager->id,
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $tr->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'quantity_sent' => 50,
            'quantity_received' => 50,
        ]);

        $res = $this->getJson('/api/v1/transfers/analytics/summary');

        $res->assertStatus(200);
        $this->assertEquals(50, $res->json('data.total_volume_sent'));
    }

    public function test_13_transfer_completion_rate_calculation(): void
    {
        Sanctum::actingAs($this->manager);

        StockTransfer::create([
            'transfer_number' => 'TR-COMP-1',
            'from_store_id' => $this->store1->id,
            'to_store_id' => $this->store2->id,
            'status' => 'completed',
            'transfer_date' => '2026-01-10 10:00:00',
            'transferred_by' => $this->manager->id,
        ]);

        StockTransfer::create([
            'transfer_number' => 'TR-COMP-2',
            'from_store_id' => $this->store1->id,
            'to_store_id' => $this->store2->id,
            'status' => 'in_transit',
            'transfer_date' => '2026-01-10 10:00:00',
            'transferred_by' => $this->manager->id,
        ]);

        $res = $this->getJson('/api/v1/transfers/analytics/summary');

        $res->assertStatus(200);
        $this->assertEquals(50.00, $res->json('data.completion_rate')); // 1 completed out of 2 = 50%
    }

    public function test_14_transfer_cancellation_rate_calculation(): void
    {
        Sanctum::actingAs($this->manager);

        StockTransfer::create([
            'transfer_number' => 'TR-CANC-1',
            'from_store_id' => $this->store1->id,
            'to_store_id' => $this->store2->id,
            'status' => 'cancelled',
            'transfer_date' => '2026-01-10 10:00:00',
            'transferred_by' => $this->manager->id,
        ]);

        $res = $this->getJson('/api/v1/transfers/analytics/summary');

        $res->assertStatus(200);
        $this->assertEquals(100.00, $res->json('data.cancellation_rate'));
    }

    public function test_15_average_transit_lead_time_calculation(): void
    {
        Sanctum::actingAs($this->manager);

        StockTransfer::create([
            'transfer_number' => 'TR-LEAD-1',
            'from_store_id' => $this->store1->id,
            'to_store_id' => $this->store2->id,
            'status' => 'completed',
            'transfer_date' => '2026-01-10 08:00:00',
            'received_date' => '2026-01-10 14:00:00', // 6 hours
            'transferred_by' => $this->manager->id,
        ]);

        $res = $this->getJson('/api/v1/transfers/analytics/summary');

        $res->assertStatus(200);
        $this->assertEquals(6.0, $res->json('data.avg_transit_lead_time_hours'));
    }

    public function test_16_store_to_store_transfer_matrix_aggregation(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $tr = StockTransfer::create([
            'transfer_number' => 'TR-MTX-1',
            'from_store_id' => $this->store1->id,
            'to_store_id' => $this->store2->id,
            'status' => 'completed',
            'transfer_date' => '2026-01-10 10:00:00',
            'transferred_by' => $this->superAdmin->id,
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $tr->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'quantity_sent' => 15,
            'quantity_received' => 15,
        ]);

        $res = $this->getJson('/api/v1/transfers/analytics/matrix');

        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertEquals($this->store1->id, $res->json('data.0.from_store_id'));
        $this->assertEquals($this->store2->id, $res->json('data.0.to_store_id'));
        $this->assertEquals(15, $res->json('data.0.total_sent_quantity'));
    }

    public function test_17_source_vs_destination_flow_analysis(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/transfers/analytics/matrix');
        $res->assertStatus(200)->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_18_store_inventory_balance_valuation_calculation(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson("/api/v1/inventory/turnover-analytics?store_id={$this->store1->id}");

        $res->assertStatus(200);
        $this->assertEquals(100, $res->json('data.total_inventory_units'));
        $this->assertEquals(60000.00, $res->json('data.total_inventory_valuation')); // 100 * 600
    }

    public function test_19_inventory_turnover_ratio_calculation(): void
    {
        Sanctum::actingAs($this->manager);

        // Record an outbound sale movement
        StockMovement::create([
            'product_variant_size_id' => $this->variantSize1->id,
            'store_id' => $this->store1->id,
            'movement_type' => StockMovementType::SALE_POS,
            'quantity_change' => -10,
            'stock_before' => 100,
            'stock_after' => 90,
            'created_by' => $this->manager->id,
        ]);

        $res = $this->getJson("/api/v1/inventory/turnover-analytics?store_id={$this->store1->id}");

        $res->assertStatus(200);
        $this->assertGreaterThan(0.0, $res->json('data.inventory_turnover_ratio'));
    }

    public function test_20_stockout_frequency_calculation(): void
    {
        Sanctum::actingAs($this->manager);

        // Update existing stock to 0 for stockout frequency test
        InventoryStock::where('product_variant_size_id', $this->variantSize1->id)
            ->where('store_id', $this->store1->id)
            ->update(['stock_quantity' => 0]);

        $res = $this->getJson("/api/v1/inventory/turnover-analytics?store_id={$this->store1->id}");

        $res->assertStatus(200);
        $this->assertEquals(1, $res->json('data.stockout_frequency'));
    }

    public function test_21_low_stock_indicator_calculation(): void
    {
        Sanctum::actingAs($this->manager);

        // store2 has stock_quantity 10 and reorder_level 15 -> low stock
        $res = $this->getJson("/api/v1/inventory/turnover-analytics?store_id={$this->store2->id}");
        $res->assertStatus(403); // Manager is not assigned to store2

        Sanctum::actingAs($this->superAdmin);
        $resAdmin = $this->getJson("/api/v1/inventory/turnover-analytics?store_id={$this->store2->id}");
        $resAdmin->assertStatus(200);
        $this->assertEquals(1, $resAdmin->json('data.low_stock_count'));
    }

    public function test_22_overstock_indicator_calculation(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // store1 has stock_quantity 100 and reorder_level 20 -> 100 >= 3*20 (60) -> overstock
        $res = $this->getJson("/api/v1/inventory/turnover-analytics?store_id={$this->store1->id}");

        $res->assertStatus(200);
        $this->assertEquals(1, $res->json('data.overstock_count'));
    }

    public function test_23_cross_store_inventory_balance_sku_breakdown(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/inventory/cross-store-balance');

        $res->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['items', 'pagination']]);
        $this->assertEquals('PUMA-700-RED-09', $res->json('data.items.0.sku'));
        $this->assertCount(2, $res->json('data.items.0.store_breakdown'));
    }

    public function test_24_paginated_transfer_history_listing(): void
    {
        Sanctum::actingAs($this->manager);

        StockTransfer::create([
            'transfer_number' => 'TR-HIST-1',
            'from_store_id' => $this->store1->id,
            'to_store_id' => $this->store2->id,
            'status' => 'completed',
            'transfer_date' => '2026-01-10 10:00:00',
            'transferred_by' => $this->manager->id,
        ]);

        $res = $this->getJson('/api/v1/transfers/analytics/history');

        $res->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.transfer_number', 'TR-HIST-1');
    }

    public function test_25_transfer_search_filtering(): void
    {
        Sanctum::actingAs($this->manager);

        StockTransfer::create([
            'transfer_number' => 'TR-SRCH-777',
            'from_store_id' => $this->store1->id,
            'to_store_id' => $this->store2->id,
            'status' => 'completed',
            'transfer_date' => '2026-01-10 10:00:00',
            'transferred_by' => $this->manager->id,
        ]);

        $res = $this->getJson('/api/v1/transfers/analytics/history?search=SRCH-777');
        $res->assertStatus(200)->assertJsonPath('data.pagination.total', 1);
    }

    public function test_26_readonly_analytics_safety(): void
    {
        Sanctum::actingAs($this->manager);

        $beforeCount = StockTransfer::count();
        $this->getJson('/api/v1/transfers/analytics/summary')->assertStatus(200);
        $afterCount = StockTransfer::count();

        $this->assertEquals($beforeCount, $afterCount);
    }

    public function test_27_cancelled_transfers_excluded_from_volume(): void
    {
        Sanctum::actingAs($this->manager);

        $tr = StockTransfer::create([
            'transfer_number' => 'TR-CANC-VAL',
            'from_store_id' => $this->store1->id,
            'to_store_id' => $this->store2->id,
            'status' => 'cancelled',
            'transfer_date' => '2026-01-10 10:00:00',
            'transferred_by' => $this->manager->id,
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $tr->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'quantity_sent' => 100,
            'quantity_received' => 0,
        ]);

        $res = $this->getJson('/api/v1/transfers/analytics/summary');

        $res->assertStatus(200);
        $this->assertEquals(0, $res->json('data.total_volume_sent'));
        $this->assertEquals(0.00, $res->json('data.total_transfer_valuation'));
    }

    public function test_28_status_breakdown_accuracy(): void
    {
        Sanctum::actingAs($this->manager);

        StockTransfer::create([
            'transfer_number' => 'TR-SB-1',
            'from_store_id' => $this->store1->id,
            'to_store_id' => $this->store2->id,
            'status' => 'in_transit',
            'transfer_date' => '2026-01-10 10:00:00',
            'transferred_by' => $this->manager->id,
        ]);

        $res = $this->getJson('/api/v1/transfers/analytics/summary');

        $res->assertStatus(200);
        $this->assertEquals(1, $res->json('data.status_breakdown.in_transit'));
    }

    public function test_29_standardized_api_responses(): void
    {
        Sanctum::actingAs($this->manager);

        $res = $this->getJson('/api/v1/transfers/analytics/summary');

        $res->assertStatus(200)->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_30_full_regression_compatibility(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/inventory/transfers');
        $res->assertStatus(200);
    }
}
