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
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Size;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LiveSupplierAndPurchaseWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;
    protected Supplier $supplier;
    protected ProductVariantSize $pvs1;
    protected ProductVariantSize $pvs2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::create([
            'code' => 'STR-001',
            'name' => 'RUPSA PADUKALAYA - Main Outlet',
            'phone' => '9876543210',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'username' => 'rupsa_admin_test',
            'is_active' => true,
        ]);
        $this->user->stores()->attach($this->store->id);

        $role = Role::create([
            'name' => 'Super Admin',
            'guard_name' => 'web',
            'display_name' => 'Super Admin',
            'module_group' => 'system',
        ]);

        $permissions = [
            'products.view', 'products.create', 'products.edit',
            'procurement.view', 'procurement.receive', 'suppliers.view',
        ];

        foreach ($permissions as $p) {
            $perm = Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web'], [
                'display_name' => ucfirst($p),
                'module_group' => 'purchases',
            ]);
            $role->permissions()->syncWithoutDetaching([$perm->id]);
        }

        DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_id' => $this->user->id,
            'model_type' => User::class,
        ]);

        $this->supplier = Supplier::create([
            'code' => 'SUP-0001',
            'name' => 'Fine Leathers',
            'company_name' => 'Fine Leathers Ltd',
            'phone' => '9876543210',
            'is_active' => true,
        ]);

        $brand = Brand::create(['name' => 'RUPSA Classic', 'slug' => 'rupsa-classic', 'code' => 'RC', 'is_active' => true]);
        $category = Category::create(['name' => 'Formal Shoes', 'slug' => 'formal-shoes', 'code' => 'FORMAL', 'is_active' => true]);
        $color = Color::create(['name' => 'Black', 'code' => 'BLK']);
        $size6 = Size::create(['size_number' => '6', 'size_system' => 'IND', 'is_active' => true]);
        $size7 = Size::create(['size_number' => '7', 'size_system' => 'IND', 'is_active' => true]);

        $product = Product::create([
            'article_number' => 'RP-805',
            'name' => 'Executive Oxford Leather Shoe',
            'slug' => 'executive-oxford-leather-shoe',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color_id' => $color->id,
            'is_active' => true,
        ]);

        $this->pvs1 = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $size6->id,
            'sku' => 'RP-805-BLACK-UK6',
            'cost_price' => 1075.20,
            'selling_price' => 1792.00,
            'mrp' => 2240.00,
            'is_active' => true,
        ]);

        $this->pvs2 = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $size7->id,
            'sku' => 'RP-805-BLACK-UK7',
            'cost_price' => 1075.20,
            'selling_price' => 1792.00,
            'mrp' => 2240.00,
            'is_active' => true,
        ]);

        InventoryStock::create([
            'product_variant_size_id' => $this->pvs1->id,
            'store_id' => $this->store->id,
            'stock_quantity' => 5,
        ]);

        InventoryStock::create([
            'product_variant_size_id' => $this->pvs2->id,
            'store_id' => $this->store->id,
            'stock_quantity' => 5,
        ]);
    }

    /** @test */
    public function supplier_directory_api_returns_200_and_real_supplier_payload(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/suppliers?per_page=100');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.name', 'Fine Leathers');
    }

    /** @test */
    public function footwear_size_matrix_search_returns_sizes_for_article(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/products/size-matrix-search?q=RP-805');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.article_number', 'RP-805')
            ->assertJsonPath('data.0.variants.0.sizes.0.sku', 'RP-805-BLACK-UK6')
            ->assertJsonPath('data.0.variants.0.sizes.1.sku', 'RP-805-BLACK-UK7');
    }

    /** @test */
    public function footwear_size_matrix_search_works_by_product_name_brand_sku_and_category(): void
    {
        // 1. By Product Name
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/products/size-matrix-search?q=Executive')
            ->assertStatus(200)
            ->assertJsonPath('data.0.article_number', 'RP-805');

        // 2. By Brand Name
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/products/size-matrix-search?q=RUPSA')
            ->assertStatus(200)
            ->assertJsonPath('data.0.article_number', 'RP-805');

        // 3. By SKU
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/products/size-matrix-search?q=RP-805-BLACK-UK6')
            ->assertStatus(200)
            ->assertJsonPath('data.0.article_number', 'RP-805');

        // 4. By Category Name
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/products/size-matrix-search?q=Formal')
            ->assertStatus(200)
            ->assertJsonPath('data.0.article_number', 'RP-805');
    }

    /** @test */
    public function size_wise_purchase_order_creation_and_grn_reconciliation(): void
    {
        // 1. Create Size-Wise PO
        $poRes = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchases/orders', [
                'supplier_id' => $this->supplier->id,
                'store_id' => $this->store->id,
                'items' => [
                    [
                        'product_variant_size_id' => $this->pvs1->id,
                        'quantity_ordered' => 10,
                        'cost_price' => 1000.00,
                    ],
                    [
                        'product_variant_size_id' => $this->pvs2->id,
                        'quantity_ordered' => 5,
                        'cost_price' => 1050.00,
                    ],
                ],
            ]);

        $poRes->assertStatus(201);
        $poId = $poRes->json('data.id');

        // Stock should remain unchanged (5 pcs each)
        $this->assertEquals(5, InventoryStock::where('product_variant_size_id', $this->pvs1->id)->value('stock_quantity'));

        // 2. Receive Goods (GRN)
        $po = PurchaseOrder::with('items')->find($poId);
        $grnRes = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchases/grn', [
                'purchase_order_id' => $poId,
                'supplier_id' => $this->supplier->id,
                'store_id' => $this->store->id,
                'items' => [
                    [
                        'purchase_order_item_id' => $po->items[0]->id,
                        'product_variant_size_id' => $this->pvs1->id,
                        'quantity_received_now' => 10,
                        'cost_price' => 1000.00,
                    ],
                    [
                        'purchase_order_item_id' => $po->items[1]->id,
                        'product_variant_size_id' => $this->pvs2->id,
                        'quantity_received_now' => 5,
                        'cost_price' => 1050.00,
                    ],
                ],
            ]);

        $grnRes->assertStatus(201);

        // Stock must now increase: pvs1 = 5 + 10 = 15, pvs2 = 5 + 5 = 10
        $this->assertEquals(15, InventoryStock::where('product_variant_size_id', $this->pvs1->id)->value('stock_quantity'));
        $this->assertEquals(10, InventoryStock::where('product_variant_size_id', $this->pvs2->id)->value('stock_quantity'));
    }

    /** @test */
    public function purchase_order_details_endpoint_returns_full_supplier_and_size_matrix_items(): void
    {
        $poRes = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchases/orders', [
                'supplier_id' => $this->supplier->id,
                'store_id' => $this->store->id,
                'items' => [
                    [
                        'product_variant_size_id' => $this->pvs1->id,
                        'quantity_ordered' => 6,
                        'cost_price' => 1000.00,
                    ],
                ],
            ]);

        $poId = $poRes->json('data.id');

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/purchases/orders/{$poId}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $poId)
            ->assertJsonPath('data.supplier_name', 'Fine Leathers')
            ->assertJsonPath('data.supplier_company', 'Fine Leathers Ltd')
            ->assertJsonPath('data.items.0.sku', 'RP-805-BLACK-UK6')
            ->assertJsonPath('data.items.0.quantity_ordered', 6)
            ->assertJsonPath('data.items.0.quantity_remaining', 6)
            ->assertJsonPath('data.items.0.cost_price', 1000);
    }

    /** @test */
    public function non_existent_purchase_order_details_returns_404(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/purchases/orders/999999');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Purchase order not found.');
    }
}
