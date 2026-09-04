<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductVariantSkuTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $viewOnlyUser;
    protected User $noPermUser;
    protected Brand $activeBrand;
    protected Brand $inactiveBrand;
    protected Category $activeCategory;
    protected Color $activeColor;
    protected Size $activeSize;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Permissions
        $permView = Permission::create(['name' => 'products.view', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Products']);
        $permCreate = Permission::create(['name' => 'products.create', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Create Products']);
        $permEdit = Permission::create(['name' => 'products.edit', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Edit Products']);

        // Admin User
        $adminRole = Role::create(['name' => 'Product Admin', 'guard_name' => 'web']);
        $adminRole->permissions()->attach([$permView->id, $permCreate->id, $permEdit->id]);

        $this->adminUser = User::create([
            'name' => 'Product Admin',
            'username' => 'prod_admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($adminRole->id, ['model_type' => User::class]);

        // View Only User
        $viewRole = Role::create(['name' => 'Product Viewer', 'guard_name' => 'web']);
        $viewRole->permissions()->attach($permView->id);

        $this->viewOnlyUser = User::create([
            'name' => 'Product Viewer',
            'username' => 'prod_viewer',
            'email' => 'viewer@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->viewOnlyUser->roles()->attach($viewRole->id, ['model_type' => User::class]);

        // No Perm User
        $this->noPermUser = User::create([
            'name' => 'No Perm User',
            'username' => 'noperm_user',
            'email' => 'noperm@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        // Masters
        $this->activeBrand = Brand::create(['name' => 'Apex', 'slug' => 'apex', 'is_active' => true]);
        $this->inactiveBrand = Brand::create(['name' => 'Old Brand', 'slug' => 'old-brand', 'is_active' => false]);
        $this->activeCategory = Category::create(['name' => 'Casual Shoes', 'slug' => 'casual-shoes', 'is_active' => true]);
        $this->activeColor = Color::create(['name' => 'Black', 'code' => 'BLK', 'hex_code' => '#000000']);
        $this->activeSize = Size::create(['size_number' => '08', 'size_system' => 'UK/IND', 'sort_order' => 8]);
    }

    public function test_1_unauthenticated_product_request_is_rejected(): void
    {
        $this->getJson('/api/v1/products')->assertStatus(401);
    }

    public function test_2_user_without_products_view_cannot_view_products(): void
    {
        Sanctum::actingAs($this->noPermUser);
        $this->getJson('/api/v1/products')->assertStatus(403);
    }

    public function test_3_user_without_products_create_cannot_create_products(): void
    {
        Sanctum::actingAs($this->viewOnlyUser);

        $this->postJson('/api/v1/products', [
            'article_number' => 'ART805',
            'name' => 'Men Casual Shoe',
            'brand_id' => $this->activeBrand->id,
            'category_id' => $this->activeCategory->id,
        ])->assertStatus(403);
    }

    public function test_4_product_creation_works_with_valid_brand_and_category(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/v1/products', [
            'article_number' => 'ART805',
            'name' => 'Men Casual Shoe',
            'brand_id' => $this->activeBrand->id,
            'category_id' => $this->activeCategory->id,
            'gender' => 'men',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Product created successfully.',
                'data' => [
                    'article_number' => 'ART805',
                    'name' => 'Men Casual Shoe',
                ],
            ]);
    }

    public function test_5_invalid_or_inactive_brand_category_is_rejected(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Invalid brand ID
        $this->postJson('/api/v1/products', [
            'article_number' => 'ART806',
            'name' => 'Shoe 2',
            'brand_id' => 9999,
            'category_id' => $this->activeCategory->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['brand_id']);

        // Inactive brand ID
        $this->postJson('/api/v1/products', [
            'article_number' => 'ART807',
            'name' => 'Shoe 3',
            'brand_id' => $this->inactiveBrand->id,
            'category_id' => $this->activeCategory->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['brand_id']);
    }

    public function test_6_duplicate_article_number_is_rejected(): void
    {
        Sanctum::actingAs($this->adminUser);

        Product::create([
            'article_number' => 'ART805',
            'name' => 'Shoe 1',
            'slug' => 'art805-shoe-1',
            'brand_id' => $this->activeBrand->id,
            'category_id' => $this->activeCategory->id,
        ]);

        $this->postJson('/api/v1/products', [
            'article_number' => 'ART805',
            'name' => 'Shoe Duplicate',
            'brand_id' => $this->activeBrand->id,
            'category_id' => $this->activeCategory->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['article_number']);
    }

    public function test_7_product_listing_and_search_works(): void
    {
        Sanctum::actingAs($this->adminUser);

        Product::create([
            'article_number' => 'ART901',
            'name' => 'Formal Derby',
            'slug' => 'art901-formal-derby',
            'brand_id' => $this->activeBrand->id,
            'category_id' => $this->activeCategory->id,
        ]);

        $this->getJson('/api/v1/products?search=Derby')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_8_product_update_works(): void
    {
        Sanctum::actingAs($this->adminUser);

        $product = Product::create([
            'article_number' => 'ART902',
            'name' => 'Old Name',
            'slug' => 'art902-old-name',
            'brand_id' => $this->activeBrand->id,
            'category_id' => $this->activeCategory->id,
        ]);

        $this->putJson("/api/v1/products/{$product->id}", [
            'article_number' => 'ART902',
            'name' => 'Updated Name',
            'brand_id' => $this->activeBrand->id,
            'category_id' => $this->activeCategory->id,
        ])->assertStatus(200)
        ->assertJson(['success' => true, 'data' => ['name' => 'Updated Name']]);
    }

    public function test_9_product_status_toggle_works(): void
    {
        Sanctum::actingAs($this->adminUser);

        $product = Product::create([
            'article_number' => 'ART903',
            'name' => 'Toggle Shoe',
            'slug' => 'art903-toggle-shoe',
            'brand_id' => $this->activeBrand->id,
            'category_id' => $this->activeCategory->id,
            'is_active' => true,
        ]);

        $this->patchJson("/api/v1/products/{$product->id}/status")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['is_active' => false]]);
    }

    public function test_10_product_variant_creation_works(): void
    {
        Sanctum::actingAs($this->adminUser);

        $product = Product::create([
            'article_number' => 'ART805',
            'name' => 'Casual Shoe',
            'slug' => 'art805-casual-shoe',
            'brand_id' => $this->activeBrand->id,
            'category_id' => $this->activeCategory->id,
        ]);

        $response = $this->postJson("/api/v1/products/{$product->id}/variants", [
            'color_id' => $this->activeColor->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'color_name' => 'Black',
                    'color_code' => 'BLK',
                ],
            ]);
    }

    public function test_11_invalid_colour_is_rejected(): void
    {
        Sanctum::actingAs($this->adminUser);

        $product = Product::create([
            'article_number' => 'ART805',
            'name' => 'Casual Shoe',
            'slug' => 'art805-casual-shoe',
            'brand_id' => $this->activeBrand->id,
            'category_id' => $this->activeCategory->id,
        ]);

        $this->postJson("/api/v1/products/{$product->id}/variants", [
            'color_id' => 9999,
        ])->assertStatus(422)->assertJsonValidationErrors(['color_id']);
    }

    public function test_12_size_sku_creation_works(): void
    {
        Sanctum::actingAs($this->adminUser);

        $product = Product::create([
            'article_number' => 'ART805',
            'name' => 'Casual Shoe',
            'slug' => 'art805-casual-shoe',
            'brand_id' => $this->activeBrand->id,
            'category_id' => $this->activeCategory->id,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color_id' => $this->activeColor->id,
        ]);

        $response = $this->postJson("/api/v1/products/{$product->id}/variants/{$variant->id}/sizes", [
            'size_id' => $this->activeSize->id,
            'mrp' => 1499.00,
            'selling_price' => 1299.00,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'sku' => 'ART805-BLK-08',
                    'mrp' => 1499.00,
                    'selling_price' => 1299.00,
                ],
            ]);
    }

    public function test_13_same_variant_same_size_cannot_be_duplicated(): void
    {
        Sanctum::actingAs($this->adminUser);

        $product = Product::create([
            'article_number' => 'ART805',
            'name' => 'Casual Shoe',
            'slug' => 'art805-casual-shoe',
            'brand_id' => $this->activeBrand->id,
            'category_id' => $this->activeCategory->id,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color_id' => $this->activeColor->id,
        ]);

        $this->postJson("/api/v1/products/{$product->id}/variants/{$variant->id}/sizes", [
            'size_id' => $this->activeSize->id,
        ])->assertStatus(201);

        // Duplicate same variant + same size
        $this->postJson("/api/v1/products/{$product->id}/variants/{$variant->id}/sizes", [
            'size_id' => $this->activeSize->id,
        ])->assertStatus(422);
    }

    public function test_14_sku_is_generated_and_validated_according_to_approved_format(): void
    {
        Sanctum::actingAs($this->adminUser);

        $product = Product::create([
            'article_number' => 'ART805',
            'name' => 'Casual Shoe',
            'slug' => 'art805-casual-shoe',
            'brand_id' => $this->activeBrand->id,
            'category_id' => $this->activeCategory->id,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color_id' => $this->activeColor->id,
        ]);

        $response = $this->postJson("/api/v1/products/{$product->id}/variants/{$variant->id}/sizes", [
            'size_id' => $this->activeSize->id,
        ]);

        $response->assertStatus(201);
        $this->assertEquals('ART805-BLK-08', $response->json('data.sku'));
    }

    public function test_15_duplicate_sku_is_rejected(): void
    {
        Sanctum::actingAs($this->adminUser);

        $product1 = Product::create([
            'article_number' => 'ART805',
            'name' => 'Shoe 1',
            'slug' => 'art805-shoe-1',
            'brand_id' => $this->activeBrand->id,
            'category_id' => $this->activeCategory->id,
        ]);

        $variant1 = ProductVariant::create(['product_id' => $product1->id, 'color_id' => $this->activeColor->id]);

        $this->postJson("/api/v1/products/{$product1->id}/variants/{$variant1->id}/sizes", [
            'size_id' => $this->activeSize->id,
            'sku' => 'CUSTOM-SKU-01',
        ])->assertStatus(201);

        $product2 = Product::create([
            'article_number' => 'ART806',
            'name' => 'Shoe 2',
            'slug' => 'art806-shoe-2',
            'brand_id' => $this->activeBrand->id,
            'category_id' => $this->activeCategory->id,
        ]);

        $variant2 = ProductVariant::create(['product_id' => $product2->id, 'color_id' => $this->activeColor->id]);

        // Attempting duplicate custom SKU
        $this->postJson("/api/v1/products/{$product2->id}/variants/{$variant2->id}/sizes", [
            'size_id' => $this->activeSize->id,
            'sku' => 'CUSTOM-SKU-01',
        ])->assertStatus(422)->assertJsonValidationErrors(['sku']);
    }

    public function test_16_optional_barcode_uniqueness_works(): void
    {
        Sanctum::actingAs($this->adminUser);

        $product = Product::create([
            'article_number' => 'ART805',
            'name' => 'Casual Shoe',
            'slug' => 'art805-casual-shoe',
            'brand_id' => $this->activeBrand->id,
            'category_id' => $this->activeCategory->id,
        ]);

        $variant = ProductVariant::create(['product_id' => $product->id, 'color_id' => $this->activeColor->id]);

        $this->postJson("/api/v1/products/{$product->id}/variants/{$variant->id}/sizes", [
            'size_id' => $this->activeSize->id,
            'barcode' => '8901234567890',
        ])->assertStatus(201);

        $size2 = Size::create(['size_number' => '09', 'size_system' => 'UK/IND', 'sort_order' => 9]);

        // Duplicate barcode
        $this->postJson("/api/v1/products/{$product->id}/variants/{$variant->id}/sizes", [
            'size_id' => $size2->id,
            'barcode' => '8901234567890',
        ])->assertStatus(422)->assertJsonValidationErrors(['barcode']);
    }

    public function test_17_standardized_api_responses_are_returned(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);
    }
}
