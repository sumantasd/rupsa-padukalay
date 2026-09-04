<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Size;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductBulkTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Category $category;
    protected Brand $brand;
    protected Color $color;
    protected Size $size;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $permView = Permission::create(['name' => 'products.view', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Products']);
        $permCreate = Permission::create(['name' => 'products.create', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Create Products']);
        $permEdit = Permission::create(['name' => 'products.edit', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Edit Products']);

        $superAdminRole = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdminRole->permissions()->attach([$permView->id, $permCreate->id, $permEdit->id]);

        $this->adminUser = User::create([
            'name' => 'Super Admin User',
            'username' => 'admin_test',
            'email' => 'admin_test@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($superAdminRole->id, ['model_type' => User::class]);

        $this->store = Store::create([
            'id' => 1,
            'code' => 'STR-001',
            'name' => 'RUPSA PADUKALAYA - Main Outlet',
            'address' => 'Dhantala Bazar, Dhantala',
            'city' => 'Nadia',
            'pincode' => '741202',
            'is_active' => true,
        ]);

        $this->category = Category::create(['name' => 'Men Footwear', 'slug' => 'men-footwear', 'is_active' => true]);
        $this->brand = Brand::create(['name' => 'Bata', 'slug' => 'bata', 'is_active' => true]);
        $this->color = Color::create(['name' => 'Black', 'code' => 'BLK', 'hex_code' => '#000000', 'is_active' => true]);
        $this->size = Size::create(['size_number' => '8', 'size_system' => 'UK', 'is_active' => true]);
    }

    public function test_atomic_product_bulk_create_succeeds_in_single_request()
    {
        Storage::fake('public');

        $colorBlocks = [
            [
                'color_id' => $this->color->id,
                'size_rows' => [
                    [
                        'size_id' => $this->size->id,
                        'mrp' => 1299,
                        'selling_price' => 999,
                        'cost_price' => 650,
                        'opening_stock' => 15,
                    ],
                ],
            ],
        ];

        $payload = [
            'article_number' => 'RP-BULK-001',
            'name' => 'Atomic Bulk Test Shoe',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'gender' => 'men',
            'upper_material' => 'Genuine Leather',
            'sole_material' => 'TPR Rubber',
            'description' => 'Atomic single transaction test',
            'mrp' => 1299,
            'selling_price' => 999,
            'cost_price' => 650,
            'image' => UploadedFile::fake()->create('shoe.jpg', 100, 'image/jpeg'),
            'color_blocks' => json_encode($colorBlocks),
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/products/bulk-create', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.article_number', 'RP-BULK-001');

        $this->assertDatabaseHas('products', [
            'article_number' => 'RP-BULK-001',
            'name' => 'Atomic Bulk Test Shoe',
        ]);

        $this->assertDatabaseHas('stock_adjustments', [
            'store_id' => 1,
            'reason' => 'opening_stock',
        ]);

        $this->assertDatabaseHas('inventory_stocks', [
            'store_id' => 1,
            'stock_quantity' => 15,
        ]);
    }

    public function test_atomic_product_bulk_update_succeeds()
    {
        $product = Product::create([
            'article_number' => 'RP-EDIT-001',
            'name' => 'Pre-existing Product',
            'slug' => 'rp-edit-001-pre-existing-product',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'gender' => 'unisex',
            'is_active' => true,
        ]);

        $colorBlocks = [
            [
                'color_id' => $this->color->id,
                'size_rows' => [
                    [
                        'size_id' => $this->size->id,
                        'mrp' => 1499,
                        'selling_price' => 1199,
                        'cost_price' => 700,
                        'opening_stock' => 20,
                    ],
                ],
            ],
        ];

        $payload = [
            'article_number' => 'RP-EDIT-001-UPDATED',
            'name' => 'Updated Product Name',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'gender' => 'men',
            'upper_material' => 'Suede Leather',
            'sole_material' => 'EVA Rubber',
            'description' => 'Updated via bulk-update',
            'color_blocks' => json_encode($colorBlocks),
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/bulk-update", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.article_number', 'RP-EDIT-001-UPDATED');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'article_number' => 'RP-EDIT-001-UPDATED',
            'name' => 'Updated Product Name',
        ]);
    }
}
