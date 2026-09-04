<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\HsnCode;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Size;
use App\Models\SizeChart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SizeChartConfigurationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $regularUser;
    protected Category $category;
    protected Brand $brand;
    protected HsnCode $hsn;

    protected function setUp(): void
    {
        parent::setUp();

        // Permissions
        $pView = Permission::create(['name' => 'size_charts.view', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Size Charts']);
        $pCreate = Permission::create(['name' => 'size_charts.create', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Create Size Charts']);
        $pEdit = Permission::create(['name' => 'size_charts.edit', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Edit Size Charts']);
        $pDelete = Permission::create(['name' => 'size_charts.delete', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Delete Size Charts']);
        $pConfig = Permission::create(['name' => 'size_charts.configure', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Configure Size Charts']);
        $pProdView = Permission::create(['name' => 'products.view', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Products']);
        $pProdCreate = Permission::create(['name' => 'products.create', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Create Products']);
        $pProdEdit = Permission::create(['name' => 'products.edit', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Edit Products']);

        $adminRole = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $adminRole->permissions()->attach([
            $pView->id, $pCreate->id, $pEdit->id, $pDelete->id, $pConfig->id, $pProdView->id, $pProdCreate->id, $pProdEdit->id
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $this->admin->roles()->attach($adminRole->id, ['model_type' => User::class]);

        $this->regularUser = User::create([
            'name' => 'Regular User',
            'username' => 'regular',
            'email' => 'regular@example.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->hsn = HsnCode::create(['code' => '6403', 'description' => 'Footwear', 'default_gst_rate' => 12.00]);
        $this->brand = Brand::create(['name' => 'Bata', 'slug' => 'bata']);
        $this->category = Category::create(['name' => "Men's Footwear", 'slug' => 'mens-footwear']);

        Size::create(['size_number' => '6', 'size_system' => 'IND', 'sort_order' => 1, 'is_active' => true]);
        Size::create(['size_number' => '7', 'size_system' => 'IND', 'sort_order' => 2, 'is_active' => true]);
        Size::create(['size_number' => '8', 'size_system' => 'IND', 'sort_order' => 3, 'is_active' => true]);
    }

    public function test_1_unauthenticated_request_is_rejected(): void
    {
        $res = $this->getJson('/api/v1/size-charts');
        $res->assertStatus(401);
    }

    public function test_2_user_without_permission_is_denied(): void
    {
        Sanctum::actingAs($this->regularUser);

        $res = $this->getJson('/api/v1/size-charts');
        $res->assertStatus(403);
    }

    public function test_3_create_size_chart_with_columns_and_rows_and_auto_sync_sizes(): void
    {
        Sanctum::actingAs($this->admin);

        $payload = [
            'name' => "Men's Leather Shoe Size Chart",
            'description' => 'Standard Indian footwear conversion matrix',
            'category_id' => $this->category->id,
            'gender' => 'men',
            'unit' => 'CM',
            'is_default' => true,
            'is_active' => true,
            'columns' => [
                ['name' => 'Size', 'data_type' => 'text'],
                ['name' => 'UK', 'data_type' => 'text'],
                ['name' => 'US', 'data_type' => 'text'],
                ['name' => 'EU', 'data_type' => 'text'],
                ['name' => 'Foot Length CM', 'data_type' => 'number'],
            ],
            'rows' => [
                ['size_value' => '6', 'values' => ['6', '6', '7', '40', '25.0']],
                ['size_value' => '7', 'values' => ['7', '7', '8', '41', '26.0']],
                ['size_value' => '11', 'values' => ['11', '11', '12', '45', '29.0']],
            ],
        ];

        $res = $this->postJson('/api/v1/size-charts', $payload);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', "Men's Leather Shoe Size Chart")
            ->assertJsonPath('data.column_count', 5)
            ->assertJsonPath('data.row_count', 3);

        $this->assertDatabaseHas('size_charts', [
            'name' => "Men's Leather Shoe Size Chart",
            'category_id' => $this->category->id,
            'is_default' => true,
        ]);

        // Verify new size 11 auto-created in master Size table
        $this->assertDatabaseHas('sizes', [
            'size_number' => '11',
        ]);
    }

    public function test_4_size_master_crud_and_status_toggle(): void
    {
        Sanctum::actingAs($this->admin);

        // Add size master
        $createRes = $this->postJson('/api/v1/sizes', [
            'size_number' => '12',
            'size_system' => 'UK/IND',
            'sort_order' => 12,
            'is_active' => true,
        ]);
        $createRes->assertStatus(201)->assertJsonPath('data.size_number', '12');

        $sizeId = $createRes->json('data.id');

        // Toggle status
        $toggleRes = $this->patchJson("/api/v1/sizes/{$sizeId}/status");
        $toggleRes->assertStatus(200)->assertJsonPath('data.is_active', false);

        // Update size master
        $updateRes = $this->putJson("/api/v1/sizes/{$sizeId}", [
            'size_number' => '12.5',
            'size_system' => 'UK/IND',
            'sort_order' => 13,
            'is_active' => true,
        ]);
        $updateRes->assertStatus(200)->assertJsonPath('data.size_number', '12.5');
    }

    public function test_5_list_size_charts_with_search_and_filters(): void
    {
        Sanctum::actingAs($this->admin);

        SizeChart::create([
            'name' => 'Sports Shoe Size Chart',
            'category_id' => $this->category->id,
            'gender' => 'men',
            'is_active' => true,
        ]);

        SizeChart::create([
            'name' => 'Kids Sandal Chart',
            'gender' => 'kids',
            'is_active' => true,
        ]);

        $res = $this->getJson('/api/v1/size-charts?search=Sports');
        $res->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Sports Shoe Size Chart');

        $resCat = $this->getJson("/api/v1/size-charts?category_id={$this->category->id}");
        $resCat->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_6_view_size_chart_details(): void
    {
        Sanctum::actingAs($this->admin);

        $chart = SizeChart::create([
            'name' => 'Formal Shoe Chart',
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        $res = $this->getJson("/api/v1/size-charts/{$chart->id}");
        $res->assertStatus(200)
            ->assertJsonPath('data.id', $chart->id)
            ->assertJsonPath('data.name', 'Formal Shoe Chart');
    }

    public function test_7_update_size_chart_columns_and_rows_atomically(): void
    {
        Sanctum::actingAs($this->admin);

        $chart = SizeChart::create([
            'name' => 'Initial Chart Name',
            'category_id' => $this->category->id,
            'is_active' => true,
        ]);

        $updatePayload = [
            'name' => 'Updated Chart Name',
            'category_id' => $this->category->id,
            'gender' => 'men',
            'unit' => 'CM',
            'columns' => [
                ['name' => 'Size', 'data_type' => 'text'],
                ['name' => 'UK', 'data_type' => 'text'],
            ],
            'rows' => [
                ['size_value' => '6', 'values' => ['6', '6']],
            ],
        ];

        $res = $this->putJson("/api/v1/size-charts/{$chart->id}", $updatePayload);
        $res->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Chart Name')
            ->assertJsonPath('data.column_count', 2)
            ->assertJsonPath('data.row_count', 1);

        $this->assertDatabaseHas('size_charts', [
            'id' => $chart->id,
            'name' => 'Updated Chart Name',
        ]);
    }

    public function test_8_delete_unused_size_chart(): void
    {
        Sanctum::actingAs($this->admin);

        $chart = SizeChart::create([
            'name' => 'Temp Chart',
            'is_active' => true,
        ]);

        $res = $this->deleteJson("/api/v1/size-charts/{$chart->id}");
        $res->assertStatus(200)->assertJsonPath('success', true);

        $this->assertSoftDeleted('size_charts', ['id' => $chart->id]);
    }

    public function test_9_block_unsafe_deletion_when_attached_to_products(): void
    {
        Sanctum::actingAs($this->admin);

        $chart = SizeChart::create([
            'name' => 'In-Use Chart',
            'is_active' => true,
        ]);

        Product::create([
            'article_number' => 'ART-INUSE-01',
            'name' => 'Attached Shoe',
            'slug' => 'attached-shoe',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'hsn_code_id' => $this->hsn->id,
            'size_chart_id' => $chart->id,
        ]);

        $res = $this->deleteJson("/api/v1/size-charts/{$chart->id}");
        $res->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_10_duplicate_size_chart(): void
    {
        Sanctum::actingAs($this->admin);

        $chart = SizeChart::create([
            'name' => 'Master Chart',
            'is_active' => true,
        ]);

        $res = $this->postJson("/api/v1/size-charts/{$chart->id}/duplicate");
        $res->assertStatus(201)
            ->assertJsonPath('data.name', 'Master Chart (Copy)');
    }

    public function test_11_toggle_active_status_and_set_default(): void
    {
        Sanctum::actingAs($this->admin);

        $chart = SizeChart::create([
            'name' => 'Chart Status Test',
            'is_active' => true,
            'is_default' => false,
        ]);

        // Toggle Status
        $resToggle = $this->postJson("/api/v1/size-charts/{$chart->id}/toggle-status");
        $resToggle->assertStatus(200)->assertJsonPath('data.is_active', false);

        // Set Default
        $resDefault = $this->postJson("/api/v1/size-charts/{$chart->id}/set-default");
        $resDefault->assertStatus(200)->assertJsonPath('data.is_default', true);
    }

    public function test_12_category_default_size_chart_suggestion_and_assignment(): void
    {
        Sanctum::actingAs($this->admin);

        $chart = SizeChart::create([
            'name' => 'Category Chart',
            'category_id' => $this->category->id,
            'is_active' => true,
            'is_default' => true,
        ]);

        // Assign to category
        $this->postJson("/api/v1/categories/{$this->category->id}/size-chart", [
            'size_chart_id' => $chart->id,
        ])->assertStatus(200);

        // Get suggested chart
        $resSug = $this->getJson("/api/v1/categories/{$this->category->id}/size-chart");
        $resSug->assertStatus(200)->assertJsonPath('data.id', $chart->id);
    }

    public function test_13_product_creation_with_size_chart(): void
    {
        Sanctum::actingAs($this->admin);

        $chart = SizeChart::create([
            'name' => 'Product Size Chart',
            'is_active' => true,
        ]);

        $prod = Product::create([
            'article_number' => 'ART-SC-001',
            'name' => 'Chart Product',
            'slug' => 'chart-product',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'hsn_code_id' => $this->hsn->id,
            'size_chart_id' => $chart->id,
        ]);

        $this->assertEquals($chart->id, $prod->fresh()->size_chart_id);
    }
}
