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
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryStockTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $storeManager;
    protected User $noPermUser;
    protected Store $store1;
    protected Store $store2;
    protected Warehouse $warehouse1;
    protected StockLocation $location1;
    protected ProductVariantSize $variantSize1;

    protected function setUp(): void
    {
        parent::setUp();

        $permView = Permission::create(['name' => 'products.view', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Products']);

        $superAdminRole = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdminRole->permissions()->attach($permView->id);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->superAdmin->roles()->attach($superAdminRole->id, ['model_type' => User::class]);

        $managerRole = Role::create(['name' => 'Store Manager', 'guard_name' => 'web']);
        $managerRole->permissions()->attach($permView->id);

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
        $this->location1 = StockLocation::create(['store_id' => $this->store1->id, 'code' => 'RACK-01', 'name' => 'Rack 1']);

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

        InventoryStock::create([
            'product_variant_size_id' => $this->variantSize1->id,
            'store_id' => $this->store1->id,
            'stock_location_id' => $this->location1->id,
            'stock_quantity' => 25,
            'reorder_level' => 5,
        ]);
    }

    public function test_1_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/inventory/stocks')->assertStatus(401);
    }

    public function test_2_user_without_permission_is_denied(): void
    {
        Sanctum::actingAs($this->noPermUser);
        $this->getJson('/api/v1/inventory/stocks')->assertStatus(403);
    }

    public function test_3_authorized_user_can_list_inventory(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/inventory/stocks');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'items',
                    'pagination',
                ],
            ]);
    }

    public function test_4_sku_lookup_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/inventory/stocks?sku=ART805-BLK-08');

        $res->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJson(['data' => ['items' => [['sku' => 'ART805-BLK-08']]]]);
    }

    public function test_5_article_number_search_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/inventory/stocks?article_number=ART805');

        $res->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJson(['data' => ['items' => [['article_number' => 'ART805']]]]);
    }

    public function test_6_stock_status_filtering_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Filter in_stock
        $this->getJson('/api/v1/inventory/stocks?status=in_stock')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');

        // Filter out_of_stock
        $this->getJson('/api/v1/inventory/stocks?status=out_of_stock')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.items');
    }

    public function test_7_non_super_admin_cannot_view_unauthorized_store_stock(): void
    {
        // Add stock record for store 2
        InventoryStock::create([
            'product_variant_size_id' => $this->variantSize1->id,
            'store_id' => $this->store2->id,
            'stock_quantity' => 10,
            'reorder_level' => 2,
        ]);

        Sanctum::actingAs($this->storeManager);

        // Store manager requesting store 2 stock is rejected with 403
        $this->getJson("/api/v1/inventory/stocks?store_id={$this->store2->id}")
            ->assertStatus(403);
    }

    public function test_8_super_admin_can_view_stock_across_stores(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->getJson("/api/v1/inventory/stocks?store_id={$this->store1->id}")
            ->assertStatus(200);
    }

    public function test_9_nonexistent_stock_id_returns_404(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->getJson('/api/v1/inventory/stocks/99999')
            ->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Inventory stock record not found.']);
    }
}
