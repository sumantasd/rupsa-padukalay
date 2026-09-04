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
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $storeManager;
    protected User $noPermUser;
    protected Store $store1;
    protected Store $store2;
    protected Warehouse $warehouse1;
    protected StockLocation $loc1;
    protected StockLocation $loc2;
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

        $this->loc1 = StockLocation::create(['store_id' => $this->store1->id, 'code' => 'RACK-01', 'name' => 'Rack 1']);
        $this->loc2 = StockLocation::create(['store_id' => $this->store1->id, 'code' => 'RACK-02', 'name' => 'Rack 2']);

        $this->storeManager->stores()->attach([$this->store1->id, $this->store2->id]);

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

        // Source stock = 50
        InventoryStock::create([
            'product_variant_size_id' => $this->variantSize1->id,
            'store_id' => $this->store1->id,
            'stock_location_id' => $this->loc1->id,
            'stock_quantity' => 50,
        ]);
    }

    public function test_1_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/v1/inventory/transfers', [])->assertStatus(401);
    }

    public function test_2_user_without_permission_is_denied(): void
    {
        Sanctum::actingAs($this->noPermUser);
        $this->postJson('/api/v1/inventory/transfers', [])->assertStatus(403);
    }

    public function test_3_successful_same_store_transfer_and_ledger_movements(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->postJson('/api/v1/inventory/transfers', [
            'from_store_id' => $this->store1->id,
            'to_store_id' => $this->store1->id,
            'notes' => 'Rack 1 to Rack 2 Transfer',
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'from_stock_location_id' => $this->loc1->id,
                    'to_stock_location_id' => $this->loc2->id,
                    'quantity' => 15,
                ],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'completed',
                    'items' => [
                        [
                            'sku' => 'ART805-BLK-08',
                            'quantity_sent' => 15,
                        ],
                    ],
                ],
            ]);

        // Source Stock deducted (50 - 15 = 35)
        $srcStock = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)
            ->where('store_id', $this->store1->id)
            ->where('stock_location_id', $this->loc1->id)
            ->first();
        $this->assertEquals(35, $srcStock->stock_quantity);

        // Destination Stock added (0 + 15 = 15)
        $dstStock = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)
            ->where('store_id', $this->store1->id)
            ->where('stock_location_id', $this->loc2->id)
            ->first();
        $this->assertEquals(15, $dstStock->stock_quantity);

        // Check movements: 1 transfer_out & 1 transfer_in
        $this->assertEquals(2, StockMovement::count());
    }

    public function test_4_successful_cross_store_transfer(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->postJson('/api/v1/inventory/transfers', [
            'from_store_id' => $this->store1->id,
            'to_store_id' => $this->store2->id,
            'notes' => 'Store 1 to Store 2 Stock Transfer',
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'from_stock_location_id' => $this->loc1->id,
                    'quantity' => 20,
                ],
            ],
        ]);

        $res->assertStatus(201);

        // Destination Store 2 Stock = 20
        $dstStock = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)
            ->where('store_id', $this->store2->id)
            ->first();
        $this->assertEquals(20, $dstStock->stock_quantity);
    }

    public function test_5_insufficient_stock_rejection_and_atomic_rollback(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Attempting to transfer 100 units from 50
        $res = $this->postJson('/api/v1/inventory/transfers', [
            'from_store_id' => $this->store1->id,
            'to_store_id' => $this->store2->id,
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'quantity' => 100,
                ],
            ],
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);

        // Rollback check: source stock remains 50, no transfer records
        $srcStock = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)
            ->where('store_id', $this->store1->id)
            ->where('stock_location_id', $this->loc1->id)
            ->first();
        $this->assertEquals(50, $srcStock->stock_quantity);
        $this->assertEquals(0, StockTransfer::count());
        $this->assertEquals(0, StockMovement::count());
    }

    public function test_6_invalid_hierarchy_or_identical_locations_rejected(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Same store and same location
        $this->postJson('/api/v1/inventory/transfers', [
            'from_store_id' => $this->store1->id,
            'to_store_id' => $this->store1->id,
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'from_stock_location_id' => $this->loc1->id,
                    'to_stock_location_id' => $this->loc1->id,
                    'quantity' => 5,
                ],
            ],
        ])->assertStatus(422);
    }

    public function test_7_inactive_dimension_rejection(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $inactiveStore = Store::create(['code' => 'ST-OFF', 'name' => 'Inactive Store', 'is_active' => false]);

        $this->postJson('/api/v1/inventory/transfers', [
            'from_store_id' => $this->store1->id,
            'to_store_id' => $inactiveStore->id,
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'quantity' => 5,
                ],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['to_store_id']);
    }

    public function test_8_unauthorized_store_access_rejection(): void
    {
        $restrictedUser = User::create([
            'name' => 'Restricted Manager',
            'username' => 'restr_mgr',
            'email' => 'restr@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $role = Role::firstOrCreate(['name' => 'Store Manager', 'guard_name' => 'web']);
        $restrictedUser->roles()->attach($role->id, ['model_type' => User::class]);

        // User is only attached to Store 1, NOT Store 2
        $restrictedUser->stores()->attach($this->store1->id);

        Sanctum::actingAs($restrictedUser);

        // Transfering to unauthorized Store 2 is rejected with 403
        $this->postJson('/api/v1/inventory/transfers', [
            'from_store_id' => $this->store1->id,
            'to_store_id' => $this->store2->id,
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'quantity' => 5,
                ],
            ],
        ])->assertStatus(403);
    }

    public function test_9_super_admin_can_perform_cross_store_transfers(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->postJson('/api/v1/inventory/transfers', [
            'from_store_id' => $this->store1->id,
            'to_store_id' => $this->store2->id,
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'from_stock_location_id' => $this->loc1->id,
                    'quantity' => 5,
                ],
            ],
        ])->assertStatus(201);
    }

    public function test_10_listing_filtering_and_detail_view_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $trf = StockTransfer::create([
            'transfer_number' => 'TRF-TEST-01',
            'from_store_id' => $this->store1->id,
            'to_store_id' => $this->store2->id,
            'status' => 'completed',
            'transfer_date' => now(),
            'transferred_by' => $this->superAdmin->id,
        ]);

        $this->getJson('/api/v1/inventory/transfers')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');

        $this->getJson("/api/v1/inventory/transfers/{$trf->id}")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['transfer_number' => 'TRF-TEST-01']]);
    }

    public function test_11_nonexistent_transfer_id_returns_404(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->getJson('/api/v1/inventory/transfers/99999')
            ->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Stock transfer record not found.']);
    }
}
