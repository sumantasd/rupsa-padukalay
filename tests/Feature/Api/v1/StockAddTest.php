<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\PurchaseBill;
use App\Models\PurchaseOrder;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockAddTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;
    protected Product $product;
    protected ProductVariantSize $size6;
    protected ProductVariantSize $size7;
    protected ProductVariantSize $size8;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\ProductionAdminUserSeeder::class);

        $this->store = Store::where('code', 'STR-001')->first() ?? Store::create([
            'name' => 'Main Store',
            'code' => 'STR-001',
            'address' => 'Dhantala',
            'is_active' => true,
        ]);

        $superAdminRole = \App\Models\Role::where('name', 'Super Admin')->firstOrFail();

        $this->user = User::create([
            'name' => 'Stock Admin',
            'username' => 'stockadmin',
            'email' => 'stockadmin@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->roles()->attach($superAdminRole->id, ['model_type' => User::class]);

        $brand = Brand::create(['name' => 'Bata', 'slug' => 'bata-stock-test', 'is_active' => true]);
        $category = Category::create(['name' => 'School Shoes', 'slug' => 'school-shoes-stock-test', 'is_active' => true]);
        $color = Color::create(['name' => 'Black', 'code' => '#000000', 'is_active' => true]);

        $s6 = Size::create(['size_number' => '6', 'size_system' => 'UK/IND', 'is_active' => true]);
        $s7 = Size::create(['size_number' => '7', 'size_system' => 'UK/IND', 'is_active' => true]);
        $s8 = Size::create(['size_number' => '8', 'size_system' => 'UK/IND', 'is_active' => true]);

        $this->product = Product::create([
            'name' => 'Bata School Shoes',
            'article_number' => 'BS-001',
            'slug' => 'bata-school-shoes-stock-add',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'color_id' => $color->id,
            'is_active' => true,
        ]);

        $this->size6 = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $s6->id,
            'sku' => 'SKU-BS-06',
            'barcode' => '8900000006',
            'selling_price' => 899,
            'is_active' => true,
        ]);

        $this->size7 = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $s7->id,
            'sku' => 'SKU-BS-07',
            'barcode' => '8900000007',
            'selling_price' => 899,
            'is_active' => true,
        ]);

        $this->size8 = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $s8->id,
            'sku' => 'SKU-BS-08',
            'barcode' => '8900000008',
            'selling_price' => 899,
            'is_active' => true,
        ]);

        // Initial inventory stock
        InventoryStock::create([
            'product_variant_size_id' => $this->size6->id,
            'store_id' => $this->store->id,
            'stock_quantity' => 12,
        ]);

        InventoryStock::create([
            'product_variant_size_id' => $this->size7->id,
            'store_id' => $this->store->id,
            'stock_quantity' => 5,
        ]);

        InventoryStock::create([
            'product_variant_size_id' => $this->size8->id,
            'store_id' => $this->store->id,
            'stock_quantity' => 0,
        ]);
    }

    public function test_stock_add_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/inventory/stock-add', []);
        $response->assertStatus(401);
    }

    public function test_multi_size_bulk_stock_add_updates_inventory_and_logs_movements_atomically(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'notes' => 'Received lot #104 batch update',
            'items' => [
                ['product_variant_size_id' => $this->size6->id, 'quantity' => 5],
                ['product_variant_size_id' => $this->size8->id, 'quantity' => 10],
            ],
        ];

        $response = $this->postJson('/api/v1/inventory/stock-add', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product_name', 'Bata School Shoes')
            ->assertJsonPath('data.total_items_updated', 2)
            ->assertJsonPath('data.total_quantity_added', 15);

        // Verify updated database stocks
        $this->assertDatabaseHas('inventory_stocks', [
            'product_variant_size_id' => $this->size6->id,
            'store_id' => $this->store->id,
            'stock_quantity' => 17, // 12 + 5
        ]);

        $this->assertDatabaseHas('inventory_stocks', [
            'product_variant_size_id' => $this->size7->id,
            'store_id' => $this->store->id,
            'stock_quantity' => 5, // Unchanged
        ]);

        $this->assertDatabaseHas('inventory_stocks', [
            'product_variant_size_id' => $this->size8->id,
            'store_id' => $this->store->id,
            'stock_quantity' => 10, // 0 + 10
        ]);

        // Verify StockMovement logs created
        $this->assertDatabaseHas('stock_movements', [
            'product_variant_size_id' => $this->size6->id,
            'store_id' => $this->store->id,
            'quantity_change' => 5,
            'stock_before' => 12,
            'stock_after' => 17,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_variant_size_id' => $this->size8->id,
            'store_id' => $this->store->id,
            'quantity_change' => 10,
            'stock_before' => 0,
            'stock_after' => 10,
        ]);

        // Confirm NO Purchase or Supplier payable records are created
        $this->assertEquals(0, PurchaseOrder::count());
        $this->assertEquals(0, PurchaseBill::count());
    }

    public function test_stock_add_rejects_zero_or_negative_quantities(): void
    {
        Sanctum::actingAs($this->user);

        $payload = [
            'store_id' => $this->store->id,
            'product_id' => $this->product->id,
            'items' => [
                ['product_variant_size_id' => $this->size6->id, 'quantity' => 0],
            ],
        ];

        $response = $this->postJson('/api/v1/inventory/stock-add', $payload);
        $response->assertStatus(422);
    }
}
