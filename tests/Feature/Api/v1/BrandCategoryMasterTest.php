<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BrandCategoryMasterTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $cashierUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        $permView = Permission::create(['name' => 'products.view', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Products']);
        $permCreate = Permission::create(['name' => 'products.create', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Create Products']);
        $permEdit = Permission::create(['name' => 'products.edit', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Edit Products']);

        // Admin User (has all permissions)
        $adminRole = Role::create(['name' => 'Catalog Admin', 'guard_name' => 'web']);
        $adminRole->permissions()->attach([$permView->id, $permCreate->id, $permEdit->id]);

        $this->adminUser = User::create([
            'name' => 'Catalog Admin',
            'username' => 'catalog_admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($adminRole->id, ['model_type' => User::class]);

        // Cashier User (no product permissions)
        $this->cashierUser = User::create([
            'name' => 'Basic Cashier',
            'username' => 'cashier_user',
            'email' => 'cashier@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/brands')->assertStatus(401);
        $this->getJson('/api/v1/categories')->assertStatus(401);
    }

    public function test_unauthorized_user_without_permission_is_denied(): void
    {
        Sanctum::actingAs($this->cashierUser);

        $this->getJson('/api/v1/brands')->assertStatus(403);
        $this->postJson('/api/v1/brands', ['name' => 'Sparx'])->assertStatus(403);
        $this->getJson('/api/v1/categories')->assertStatus(403);
    }

    public function test_successful_brand_creation_listing_and_viewing(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create Brand
        $response = $this->postJson('/api/v1/brands', [
            'name' => 'Sparx',
            'logo_url' => 'https://example.com/sparx.png',
            'is_featured_on_web' => true,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Brand created successfully.',
                'data' => [
                    'name' => 'Sparx',
                    'slug' => 'sparx',
                    'is_featured_on_web' => true,
                    'is_active' => true,
                ],
            ]);

        $brandId = $response->json('data.id');

        // View Brand
        $this->getJson("/api/v1/brands/{$brandId}")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['name' => 'Sparx'],
            ]);

        // List Brands
        $this->getJson('/api/v1/brands')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_duplicate_brand_name_is_rejected(): void
    {
        Sanctum::actingAs($this->adminUser);

        Brand::create(['name' => 'Bata', 'slug' => 'bata']);

        $response = $this->postJson('/api/v1/brands', ['name' => 'Bata']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_successful_brand_update_and_status_toggle(): void
    {
        Sanctum::actingAs($this->adminUser);

        $brand = Brand::create(['name' => 'Red Chief', 'slug' => 'red-chief', 'is_active' => true]);

        // Update Brand
        $this->putJson("/api/v1/brands/{$brand->id}", [
            'name' => 'Red Chief Premium',
        ])->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => ['name' => 'Red Chief Premium', 'slug' => 'red-chief-premium'],
        ]);

        // Toggle Status -> Deactivate
        $this->patchJson("/api/v1/brands/{$brand->id}/status")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['is_active' => false],
            ]);

        $this->assertFalse($brand->fresh()->is_active);
    }

    public function test_successful_category_creation_with_parent_tree(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create Root Category (Men)
        $resParent = $this->postJson('/api/v1/categories', [
            'name' => 'Men Footwear',
            'is_visible_on_web' => true,
        ])->assertStatus(201);

        $parentId = $resParent->json('data.id');

        // Create Subcategory (Formal Shoes under Men)
        $resChild = $this->postJson('/api/v1/categories', [
            'parent_id' => $parentId,
            'name' => 'Formal Shoes',
            'is_visible_on_web' => true,
        ])->assertStatus(201);

        $resChild->assertJson([
            'success' => true,
            'data' => [
                'name' => 'Formal Shoes',
                'parent_id' => $parentId,
                'parent_name' => 'Men Footwear',
            ],
        ]);

        // List Categories
        $this->getJson('/api/v1/categories')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_invalid_category_parent_id_is_rejected(): void
    {
        Sanctum::actingAs($this->adminUser);

        $response = $this->postJson('/api/v1/categories', [
            'parent_id' => 99999, // Non-existent parent ID
            'name' => 'Invalid Category',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }

    public function test_category_self_parenting_is_rejected(): void
    {
        Sanctum::actingAs($this->adminUser);

        $category = Category::create(['name' => 'Boots', 'slug' => 'boots']);

        $response = $this->putJson("/api/v1/categories/{$category->id}", [
            'parent_id' => $category->id,
            'name' => 'Boots',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);
    }
}
