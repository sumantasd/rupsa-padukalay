<?php

namespace Tests\Feature\Api\v1;

use App\Models\Color;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Size;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SizeColorMasterTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $cashierUser;

    protected function setUp(): void
    {
        parent::setUp();

        $permView = Permission::create(['name' => 'products.view', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Products']);
        $permCreate = Permission::create(['name' => 'products.create', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Create Products']);
        $permEdit = Permission::create(['name' => 'products.edit', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Edit Products']);

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
        $this->getJson('/api/v1/sizes')->assertStatus(401);
        $this->getJson('/api/v1/colors')->assertStatus(401);
    }

    public function test_unauthorized_user_without_permission_is_denied(): void
    {
        Sanctum::actingAs($this->cashierUser);

        $this->getJson('/api/v1/sizes')->assertStatus(403);
        $this->postJson('/api/v1/sizes', ['size_number' => '8'])->assertStatus(403);
        $this->getJson('/api/v1/colors')->assertStatus(403);
    }

    public function test_successful_size_creation_listing_and_filtering(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create Size 08
        $response = $this->postJson('/api/v1/sizes', [
            'size_number' => '08',
            'size_system' => 'UK/IND',
            'sort_order' => 8,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Size created successfully.',
                'data' => [
                    'size_number' => '08',
                    'size_system' => 'UK/IND',
                    'sort_order' => 8,
                ],
            ]);

        $sizeId = $response->json('data.id');

        // View Size
        $this->getJson("/api/v1/sizes/{$sizeId}")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['size_number' => '08']]);

        // Filter Sizes
        $this->getJson('/api/v1/sizes?system=UK/IND')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_duplicate_size_in_same_system_is_rejected(): void
    {
        Sanctum::actingAs($this->adminUser);

        Size::create(['size_number' => '09', 'size_system' => 'UK/IND', 'sort_order' => 9]);

        $response = $this->postJson('/api/v1/sizes', [
            'size_number' => '09',
            'size_system' => 'UK/IND',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['size_number']);
    }

    public function test_successful_color_creation_listing_and_updating(): void
    {
        Sanctum::actingAs($this->adminUser);

        // Create Color
        $response = $this->postJson('/api/v1/colors', [
            'name' => 'Black',
            'code' => 'BLK',
            'hex_code' => '#000000',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Black',
                    'code' => 'BLK',
                    'hex_code' => '#000000',
                ],
            ]);

        $colorId = $response->json('data.id');

        // Update Color
        $this->putJson("/api/v1/colors/{$colorId}", [
            'name' => 'Jet Black',
            'code' => 'BLK',
            'hex_code' => '#050505',
        ])->assertStatus(200)
        ->assertJson([
            'success' => true,
            'data' => ['name' => 'Jet Black'],
        ]);

        // Search Colors
        $this->getJson('/api/v1/colors?search=Jet')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_duplicate_color_name_or_code_is_rejected(): void
    {
        Sanctum::actingAs($this->adminUser);

        Color::create(['name' => 'Brown', 'code' => 'BRN', 'hex_code' => '#8B4513']);

        // Duplicate name
        $this->postJson('/api/v1/colors', [
            'name' => 'Brown',
            'code' => 'BRN2',
        ])->assertStatus(422)->assertJsonValidationErrors(['name']);

        // Duplicate code
        $this->postJson('/api/v1/colors', [
            'name' => 'Dark Brown',
            'code' => 'BRN',
        ])->assertStatus(422)->assertJsonValidationErrors(['code']);
    }
}
