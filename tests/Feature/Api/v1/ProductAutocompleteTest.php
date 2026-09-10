<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\Size;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductAutocompleteTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\ProductionAdminUserSeeder::class);

        $this->store = Store::where('code', 'STR-001')->first() ?? Store::create([
            'name' => 'Main Outlet',
            'code' => 'STORE-01',
            'address' => 'Dhantala',
            'is_active' => true,
        ]);

        $superAdminRole = \App\Models\Role::where('name', 'Super Admin')->firstOrFail();

        $this->user = User::create([
            'name' => 'Test Admin',
            'username' => 'testadmin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->user->roles()->attach($superAdminRole->id, ['model_type' => User::class]);
    }

    public function test_autocomplete_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/products/autocomplete?q=Bata');
        $response->assertStatus(401);
    }

    public function test_autocomplete_rejects_query_shorter_than_two_characters(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/products/autocomplete?q=B');
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', []);
    }

    public function test_autocomplete_returns_matching_products_by_sku_barcode_article_and_name(): void
    {
        Sanctum::actingAs($this->user);

        $brand = Brand::create(['name' => 'Bata', 'slug' => 'bata', 'is_active' => true]);
        $category = Category::create(['name' => 'Formal Shoes', 'slug' => 'formal-shoes', 'is_active' => true]);
        $color = Color::create(['name' => 'Black', 'code' => '#000000', 'is_active' => true]);
        $size = Size::create(['size_number' => '7', 'size_system' => 'UK/IND', 'is_active' => true]);

        $product = Product::create([
            'name' => 'Bata Formal Shoes',
            'article_number' => 'BF-07',
            'slug' => 'bata-formal-shoes',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color_id' => $color->id,
            'is_active' => true,
        ]);

        $variantSize = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $size->id,
            'sku' => 'SKU-BF07',
            'barcode' => '8901234567890',
            'cost_price' => 500,
            'mrp' => 1199,
            'selling_price' => 1099,
            'is_active' => true,
        ]);

        InventoryStock::create([
            'product_variant_size_id' => $variantSize->id,
            'store_id' => $this->store->id,
            'stock_quantity' => 12,
        ]);

        // Search by Product Name
        $response = $this->getJson('/api/v1/products/autocomplete?q=Formal&store_id=' . $this->store->id);
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.product_name', 'Bata Formal Shoes')
            ->assertJsonPath('data.0.article_code', 'BF-07')
            ->assertJsonPath('data.0.sku', 'SKU-BF07')
            ->assertJsonPath('data.0.stock', 12)
            ->assertJsonPath('data.0.selling_price', 1099);

        // Search by SKU
        $responseSku = $this->getJson('/api/v1/products/autocomplete?q=SKU-BF07&store_id=' . $this->store->id);
        $responseSku->assertStatus(200)
            ->assertJsonPath('data.0.product_variant_size_id', $variantSize->id);

        // Search by Barcode
        $responseBarcode = $this->getJson('/api/v1/products/autocomplete?q=8901234567890&store_id=' . $this->store->id);
        $responseBarcode->assertStatus(200)
            ->assertJsonPath('data.0.product_variant_size_id', $variantSize->id);
    }

    public function test_autocomplete_deduplicates_by_product_for_text_searches(): void
    {
        Sanctum::actingAs($this->user);

        $brand = Brand::create(['name' => 'Bata', 'slug' => 'bata-school', 'is_active' => true]);
        $category = Category::create(['name' => 'School Shoes', 'slug' => 'school-shoes', 'is_active' => true]);
        $color = Color::create(['name' => 'Black', 'code' => '#000000', 'is_active' => true]);

        $size6 = Size::create(['size_number' => '6', 'size_system' => 'UK/IND', 'is_active' => true]);
        $size7 = Size::create(['size_number' => '7', 'size_system' => 'UK/IND', 'is_active' => true]);
        $size8 = Size::create(['size_number' => '8', 'size_system' => 'UK/IND', 'is_active' => true]);

        $product = Product::create([
            'name' => 'School Shoes',
            'article_number' => 'SS-100',
            'slug' => 'school-shoes-unique',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color_id' => $color->id,
            'is_active' => true,
        ]);

        foreach ([$size6, $size7, $size8] as $s) {
            $pvs = ProductVariantSize::create([
                'product_variant_id' => $variant->id,
                'size_id' => $s->id,
                'sku' => 'SKU-SS-' . $s->size_number,
                'barcode' => '8900000000' . $s->size_number,
                'selling_price' => 899,
                'is_active' => true,
            ]);
            InventoryStock::create([
                'product_variant_size_id' => $pvs->id,
                'store_id' => $this->store->id,
                'stock_quantity' => 10,
            ]);
        }

        // Searching product name "School" must return ONLY 1 product result (not 3 size rows)
        $response = $this->getJson('/api/v1/products/autocomplete?q=School&store_id=' . $this->store->id);
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_name', 'School Shoes')
            ->assertJsonPath('data.0.total_stock', 30);
    }
}

