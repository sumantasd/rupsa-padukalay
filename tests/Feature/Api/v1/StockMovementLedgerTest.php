<?php

namespace Tests\Feature\Api\v1;

use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\Role;
use App\Models\Size;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockMovementLedgerTest extends TestCase
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
    protected StockMovement $movement1;

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

        $this->movement1 = StockMovement::create([
            'product_variant_size_id' => $this->variantSize1->id,
            'store_id' => $this->store1->id,
            'stock_location_id' => $this->location1->id,
            'movement_type' => StockMovementType::PURCHASE_RECEIVE->value,
            'quantity_change' => 20,
            'stock_before' => 0,
            'stock_after' => 20,
            'notes' => 'Initial Purchase Batch #1',
            'created_by' => $this->superAdmin->id,
        ]);
    }

    public function test_1_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/inventory/movements')->assertStatus(401);
    }

    public function test_2_user_without_permission_is_denied(): void
    {
        Sanctum::actingAs($this->noPermUser);
        $this->getJson('/api/v1/inventory/movements')->assertStatus(403);
    }

    public function test_3_authorized_user_can_list_stock_movements(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->getJson('/api/v1/inventory/movements');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'items' => [
                        '*' => [
                            'id',
                            'sku',
                            'article_number',
                            'product_name',
                            'movement_type',
                            'quantity_change',
                            'stock_before',
                            'stock_after',
                        ],
                    ],
                    'pagination',
                ],
            ]);
    }

    public function test_4_sku_and_article_number_filtering_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->getJson('/api/v1/inventory/movements?sku=ART805-BLK-08')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJson(['data' => ['items' => [['sku' => 'ART805-BLK-08']]]]);

        $this->getJson('/api/v1/inventory/movements?article_number=ART805')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJson(['data' => ['items' => [['article_number' => 'ART805']]]]);
    }

    public function test_5_movement_type_filtering_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->getJson('/api/v1/inventory/movements?movement_type=purchase_receive')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');

        $this->getJson('/api/v1/inventory/movements?movement_type=sale_pos')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.items');
    }

    public function test_6_date_filtering_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $today = now()->format('Y-m-d');

        $this->getJson("/api/v1/inventory/movements?date_from={$today}&date_to={$today}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_7_store_and_warehouse_filtering_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->getJson("/api/v1/inventory/movements?store_id={$this->store1->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_8_non_super_admin_cannot_view_unauthorized_store_movements(): void
    {
        // Add movement for store 2
        StockMovement::create([
            'product_variant_size_id' => $this->variantSize1->id,
            'store_id' => $this->store2->id,
            'movement_type' => StockMovementType::SALE_POS->value,
            'quantity_change' => -2,
            'stock_before' => 10,
            'stock_after' => 8,
            'created_by' => $this->superAdmin->id,
        ]);

        Sanctum::actingAs($this->storeManager);

        // Accessing store 2 movements directly is rejected with 403
        $this->getJson("/api/v1/inventory/movements?store_id={$this->store2->id}")
            ->assertStatus(403);
    }

    public function test_9_super_admin_can_view_movements_across_stores(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->getJson("/api/v1/inventory/movements?store_id={$this->store1->id}")
            ->assertStatus(200);
    }

    public function test_10_nonexistent_movement_id_returns_404(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->getJson('/api/v1/inventory/movements/99999')
            ->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Stock movement record not found.']);
    }

    public function test_11_read_only_behavior_enforced(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // POST / PUT / DELETE endpoints on movements do not exist (405 Method Not Allowed)
        $this->postJson('/api/v1/inventory/movements', ['quantity_change' => 5])->assertStatus(405);
        $this->putJson("/api/v1/inventory/movements/{$this->movement1->id}", ['notes' => 'hacked'])->assertStatus(405);
        $this->deleteJson("/api/v1/inventory/movements/{$this->movement1->id}")->assertStatus(405);
    }

    public function test_12_standardized_api_responses_are_returned(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->getJson("/api/v1/inventory/movements/{$this->movement1->id}")
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'sku',
                    'movement_type',
                    'quantity_change',
                    'stock_before',
                    'stock_after',
                ],
            ]);
    }
}
