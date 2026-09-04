<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\HsnCode;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use App\Models\User;
use App\Services\TaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductImageHsnTaxTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $noPermUser;
    protected Product $product1;
    protected Product $product2;
    protected ProductVariant $variant1;

    protected function setUp(): void
    {
        parent::setUp();

        $permView = Permission::create(['name' => 'products.view', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Products']);
        $permCreate = Permission::create(['name' => 'products.create', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Create Products']);
        $permEdit = Permission::create(['name' => 'products.edit', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Edit Products']);

        $adminRole = Role::create(['name' => 'Master Admin', 'guard_name' => 'web']);
        $adminRole->permissions()->attach([$permView->id, $permCreate->id, $permEdit->id]);

        $this->adminUser = User::create([
            'name' => 'Master Admin',
            'username' => 'master_admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($adminRole->id, ['model_type' => User::class]);

        $this->noPermUser = User::create([
            'name' => 'No Perm User',
            'username' => 'noperm_user',
            'email' => 'noperm@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $brand = Brand::create(['name' => 'Bata', 'slug' => 'bata', 'is_active' => true]);
        $category = Category::create(['name' => 'Formal', 'slug' => 'formal', 'is_active' => true]);
        $color = Color::create(['name' => 'Black', 'code' => 'BLK']);

        $this->product1 = Product::create([
            'article_number' => 'ART101',
            'name' => 'Formal Shoe 1',
            'slug' => 'art101-formal-shoe-1',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
        ]);

        $this->product2 = Product::create([
            'article_number' => 'ART102',
            'name' => 'Formal Shoe 2',
            'slug' => 'art102-formal-shoe-2',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
        ]);

        $this->variant1 = ProductVariant::create([
            'product_id' => $this->product1->id,
            'color_id' => $color->id,
        ]);
    }

    public function test_product_image_authentication_and_rbac_protection(): void
    {
        $this->getJson("/api/v1/products/{$this->product1->id}/images")->assertStatus(401);

        Sanctum::actingAs($this->noPermUser);
        $this->getJson("/api/v1/products/{$this->product1->id}/images")->assertStatus(403);
    }

    public function test_product_image_attach_listing_and_metadata_update(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Attach Image
        $res = $this->postJson("/api/v1/products/{$this->product1->id}/images", [
            'image_path' => 'products/shoe1.png',
            'alt_text' => 'Side View',
            'is_primary' => true,
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'image_path' => 'products/shoe1.png',
                    'is_primary' => true,
                ],
            ]);

        $imageId = $res->json('data.id');

        // List Images
        $this->getJson("/api/v1/products/{$this->product1->id}/images")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Update Metadata
        $this->putJson("/api/v1/products/images/{$imageId}", [
            'alt_text' => 'Updated Side View',
        ])->assertStatus(200)
        ->assertJson(['success' => true, 'data' => ['alt_text' => 'Updated Side View']]);
    }

    public function test_primary_image_flagging_resets_other_primary_flags(): void
    {
        Sanctum::actingAs($this->adminUser);

        $img1 = ProductImage::create([
            'product_id' => $this->product1->id,
            'image_path' => 'p1.png',
            'is_primary' => true,
        ]);

        $img2 = ProductImage::create([
            'product_id' => $this->product1->id,
            'image_path' => 'p2.png',
            'is_primary' => false,
        ]);

        // Set img2 as primary
        $this->patchJson("/api/v1/products/images/{$img2->id}/primary")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['is_primary' => true]]);

        $this->assertFalse($img1->fresh()->is_primary);
        $this->assertTrue($img2->fresh()->is_primary);
    }

    public function test_invalid_variant_product_association_rejected(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Attempt to associate product2 with variant1 (which belongs to product1)
        $this->postJson("/api/v1/products/{$this->product2->id}/images", [
            'image_path' => 'invalid.png',
            'product_variant_id' => $this->variant1->id,
        ])->assertStatus(422)->assertJsonValidationErrors(['product_variant_id']);
    }

    public function test_hsn_code_crud_and_duplicate_prevention(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create HSN
        $res = $this->postJson('/api/v1/hsn-codes', [
            'code' => '6403',
            'description' => 'Footwear with outer soles of rubber/plastics',
            'default_gst_rate' => 12.00,
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'code' => '6403',
                    'default_gst_rate' => 12.00,
                ],
            ]);

        // Duplicate HSN code
        $this->postJson('/api/v1/hsn-codes', [
            'code' => '6403',
        ])->assertStatus(422)->assertJsonValidationErrors(['code']);
    }

    public function test_tax_rate_crud_operation(): void
    {
        Sanctum::actingAs($this->adminUser);

        $res = $this->postJson('/api/v1/tax-rates', [
            'name' => 'GST 12%',
            'rate_percentage' => 12.00,
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'GST 12%',
                    'rate_percentage' => 12.00,
                    'cgst_percentage' => 6.00,
                    'sgst_percentage' => 6.00,
                ],
            ]);

        $this->getJson('/api/v1/tax-rates')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_gst_off_by_default_behavior(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Check settings: GST is OFF by default
        $this->getJson('/api/v1/tax-settings')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['gst_enabled' => false],
            ]);

        $taxService = app(TaxService::class);
        $this->assertFalse($taxService->isGstEnabled());

        $calc = $taxService->calculateItemTax(1000.00, 1);
        $this->assertEquals(0.00, $calc['total_tax_amount']);
        $this->assertEquals(1000.00, $calc['line_total']);
    }

    public function test_gst_on_configuration_behavior(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Enable GST
        $this->putJson('/api/v1/tax-settings', [
            'gst_enabled' => 1,
        ])->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => ['gst_enabled' => true],
        ]);

        $taxService = app(TaxService::class);
        $this->assertTrue($taxService->isGstEnabled());

        $hsn = HsnCode::create(['code' => '6403', 'default_gst_rate' => 12.00]);

        $calc = $taxService->calculateItemTax(1120.00, 1, 0.0, $hsn);
        $this->assertEquals(12.00, $calc['tax_rate_percentage']);
        $this->assertTrue($calc['total_tax_amount'] > 0);
    }
}
