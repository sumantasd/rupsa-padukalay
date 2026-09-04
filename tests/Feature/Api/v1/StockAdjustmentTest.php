<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\InventoryStock;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\Role;
use App\Models\Size;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $storeManager;
    protected User $noPermUser;
    protected Store $store1;
    protected Store $store2;
    protected Warehouse $warehouse1;
    protected ProductVariantSize $variantSize1;

    protected function setUp(): void
    {
        parent::setUp();

        $permView = Permission::create(['name' => 'products.view', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Products']);
        $permEdit = Permission::create(['name' => 'products.edit', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Edit Products']);

        $superAdminRole = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdminRole->permissions()->attach([$permView->id, $permEdit->id]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->superAdmin->roles()->attach($superAdminRole->id, ['model_type' => User::class]);

        $managerRole = Role::create(['name' => 'Store Manager', 'guard_name' => 'web']);
        $managerRole->permissions()->attach([$permView->id, $permEdit->id]);

        $this->storeManager = User::create([
            'name' => 'Store Manager',
            'username' => 'store_mgr',
            'email' => 'manager@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->storeManager->roles()->attach($managerRole->id, ['model_type' => User::class]);

        $this->noPermUser = User::create([
            'name' => 'No Perm User',
            'username' => 'noperm_user',
            'email' => 'noperm@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $this->store1 = Store::create(['code' => 'ST-001', 'name' => 'Store 1', 'is_active' => true]);
        $this->store2 = Store::create(['code' => 'ST-002', 'name' => 'Store 2', 'is_active' => true]);
        $this->warehouse1 = Warehouse::create(['code' => 'WH-001', 'name' => 'Warehouse 1', 'is_active' => true]);

        $this->storeManager->stores()->attach($this->store1->id);

        $brand = Brand::create(['name' => 'Bata', 'slug' => 'bata', 'is_active' => true]);
        $category = Category::create(['name' => 'Formal', 'slug' => 'formal', 'is_active' => true]);
        $color = Color::create(['name' => 'Black', 'code' => 'BLK']);
        $size = Size::create(['size_number' => '08', 'size_system' => 'UK', 'sort_order' => 8]);

        $product = Product::create([
            'article_number' => 'ART805',
            'name' => 'Men Formal Shoe',
            'slug' => 'art805-men-formal-shoe',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color_id' => $color->id,
        ]);

        $this->variantSize1 = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $size->id,
            'sku' => 'ART805-BLK-08',
            'barcode' => '8901234567890',
            'mrp' => 1299.00,
            'selling_price' => 999.00,
        ]);
    }

    public function test_1_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/v1/inventory/adjustments', [])->assertStatus(401);
    }

    public function test_2_user_without_permission_is_denied(): void
    {
        Sanctum::actingAs($this->noPermUser);
        $this->postJson('/api/v1/inventory/adjustments', [])->assertStatus(403);
    }

    public function test_3_successful_stock_addition_and_movement_creation(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->postJson('/api/v1/inventory/adjustments', [
            'store_id' => $this->store1->id,
            'reason' => 'physical_count',
            'notes' => 'Received stock batch 1',
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'type' => 'add',
                    'quantity' => 20,
                ],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'reason' => 'physical_count',
                    'items' => [
                        [
                            'sku' => 'ART805-BLK-08',
                            'old_quantity' => 0,
                            'new_quantity' => 20,
                            'quantity_adjusted' => 20,
                        ],
                    ],
                ],
            ]);

        // Check Inventory Stock
        $stock = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)
            ->where('store_id', $this->store1->id)
            ->first();
        $this->assertNotNull($stock);
        $this->assertEquals(20, $stock->stock_quantity);

        // Check Movement Ledger
        $movement = StockMovement::where('product_variant_size_id', $this->variantSize1->id)->first();
        $this->assertNotNull($movement);
        $this->assertEquals(20, $movement->quantity_change);
        $this->assertEquals(0, $movement->stock_before);
        $this->assertEquals(20, $movement->stock_after);
    }

    public function test_4_successful_stock_deduction(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Setup initial stock = 30
        InventoryStock::create([
            'product_variant_size_id' => $this->variantSize1->id,
            'store_id' => $this->store1->id,
            'stock_quantity' => 30,
        ]);

        $res = $this->postJson('/api/v1/inventory/adjustments', [
            'store_id' => $this->store1->id,
            'reason' => 'damage',
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'type' => 'deduct',
                    'quantity' => 5,
                ],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'items' => [
                        [
                            'old_quantity' => 30,
                            'new_quantity' => 25,
                            'quantity_adjusted' => -5,
                        ],
                    ],
                ],
            ]);

        $stock = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)
            ->where('store_id', $this->store1->id)
            ->first();
        $this->assertEquals(25, $stock->stock_quantity);
    }

    public function test_5_insufficient_stock_rejection_and_atomic_rollback(): void
    {
        Sanctum::actingAs($this->superAdmin);

        InventoryStock::create([
            'product_variant_size_id' => $this->variantSize1->id,
            'store_id' => $this->store1->id,
            'stock_quantity' => 10,
        ]);

        // Attempt to deduct 50 units (more than 10 available)
        $res = $this->postJson('/api/v1/inventory/adjustments', [
            'store_id' => $this->store1->id,
            'reason' => 'damage',
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'type' => 'deduct',
                    'quantity' => 50,
                ],
            ],
        ]);

        $res->assertStatus(422)
            ->assertJson(['success' => false]);

        // Assert DB rolled back completely (stock remains 10, no adjustment record created)
        $stock = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first();
        $this->assertEquals(10, $stock->stock_quantity);
        $this->assertEquals(0, StockAdjustment::count());
        $this->assertEquals(0, StockMovement::count());
    }

    public function test_6_invalid_sku_rejection(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->postJson('/api/v1/inventory/adjustments', [
            'store_id' => $this->store1->id,
            'reason' => 'physical_count',
            'items' => [
                [
                    'sku' => 'INVALID-SKU-999',
                    'type' => 'add',
                    'quantity' => 10,
                ],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['items.0.sku']);
    }

    public function test_7_unauthorized_store_access_rejection(): void
    {
        Sanctum::actingAs($this->storeManager);

        // Store manager attempting to adjust Store 2 (unauthorized) returns 403
        $this->postJson('/api/v1/inventory/adjustments', [
            'store_id' => $this->store2->id,
            'reason' => 'physical_count',
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'type' => 'add',
                    'quantity' => 10,
                ],
            ],
        ])->assertStatus(403);
    }

    public function test_8_super_admin_can_perform_adjustments_across_stores(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->postJson('/api/v1/inventory/adjustments', [
            'store_id' => $this->store2->id,
            'reason' => 'physical_count',
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'type' => 'add',
                    'quantity' => 15,
                ],
            ],
        ])->assertStatus(201);
    }

    public function test_9_listing_filtering_and_detail_view_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $adj = StockAdjustment::create([
            'adjustment_number' => 'ADJ-TEST-01',
            'store_id' => $this->store1->id,
            'reason' => 'physical_count',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->getJson('/api/v1/inventory/adjustments')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');

        $this->getJson("/api/v1/inventory/adjustments/{$adj->id}")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['adjustment_number' => 'ADJ-TEST-01']]);
    }

    public function test_10_nonexistent_adjustment_id_returns_404(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->getJson('/api/v1/inventory/adjustments/99999')
            ->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Stock adjustment record not found.']);
    }

    public function test_11_opening_stock_reason_is_valid(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->postJson('/api/v1/inventory/adjustments', [
            'store_id' => $this->store1->id,
            'reason' => 'opening_stock',
            'notes' => 'Initial opening stock entry for article TEST-001',
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'type' => 'add',
                    'quantity' => 25,
                ],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'reason' => 'opening_stock',
                ],
            ]);
    }

    public function test_12_invalid_reason_rejection(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->postJson('/api/v1/inventory/adjustments', [
            'store_id' => $this->store1->id,
            'reason' => 'invalid_random_reason',
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'type' => 'add',
                    'quantity' => 10,
                ],
            ],
        ]);

        $res->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }
}
