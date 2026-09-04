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
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseOrderFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $noPermUser;
    protected Supplier $supplier;
    protected Store $store;
    protected ProductVariantSize $variantSize1;

    protected function setUp(): void
    {
        parent::setUp();

        $permView = Permission::create(['name' => 'products.view', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Products']);
        $permCreate = Permission::create(['name' => 'products.create', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Create Products']);
        $permEdit = Permission::create(['name' => 'products.edit', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Edit Products']);

        $superAdminRole = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdminRole->permissions()->attach([$permView->id, $permCreate->id, $permEdit->id]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->superAdmin->roles()->attach($superAdminRole->id, ['model_type' => User::class]);

        $this->noPermUser = User::create([
            'name' => 'No Perm User',
            'username' => 'noperm_user',
            'email' => 'noperm@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Bata Supplier India',
            'phone' => '9830098300',
        ]);

        $this->store = Store::create(['code' => 'ST-001', 'name' => 'Main Store', 'is_active' => true]);

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
    }

    public function test_1_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/purchases/orders')->assertStatus(401);
    }

    public function test_2_user_without_permission_is_denied(): void
    {
        Sanctum::actingAs($this->noPermUser);
        $this->getJson('/api/v1/purchases/orders')->assertStatus(403);
    }

    public function test_3_purchase_order_creation_subtotal_grand_total_and_no_stock_increase(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->postJson('/api/v1/purchases/orders', [
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'order_date' => '2026-09-02',
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'quantity_ordered' => 50,
                    'cost_price' => 500.00,
                    'mrp' => 1299.00,
                    'selling_price' => 999.00,
                ],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'supplier_id' => $this->supplier->id,
                    'status' => 'draft',
                    'subtotal' => 25000.00,
                    'grand_total' => 25000.00,
                    'items' => [
                        [
                            'sku' => 'ART805-BLK-08',
                            'quantity_ordered' => 50,
                            'quantity_received' => 0,
                            'cost_price' => 500.00,
                            'total_cost' => 25000.00,
                        ],
                    ],
                ],
            ]);

        // CRITICAL BUSINESS RULE VERIFICATION: Creating a PO must NOT increase physical stock
        $stockCount = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->count();
        $this->assertEquals(0, $stockCount);
    }

    public function test_4_draft_purchase_order_update(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-01',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'order_date' => '2026-09-02',
            'status' => 'draft',
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'created_by' => $this->superAdmin->id,
        ]);

        $res = $this->putJson("/api/v1/purchases/orders/{$po->id}", [
            'notes' => 'Updated PO draft notes',
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'quantity_ordered' => 10,
                    'cost_price' => 600.00,
                ],
            ],
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'notes' => 'Updated PO draft notes',
                    'subtotal' => 6000.00,
                    'grand_total' => 6000.00,
                ],
            ]);
    }

    public function test_5_non_draft_po_update_rejected(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-REC',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'order_date' => '2026-09-02',
            'status' => 'received',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->putJson("/api/v1/purchases/orders/{$po->id}", [
            'notes' => 'Attempt illegal update',
        ])->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_6_listing_filtering_and_detail_view(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-SEARCH',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'order_date' => '2026-09-02',
            'status' => 'draft',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->getJson('/api/v1/purchases/orders?search=SEARCH')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');

        $this->getJson("/api/v1/purchases/orders/{$po->id}")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['po_number' => 'PO-TEST-SEARCH']]);
    }

    public function test_7_nonexistent_po_returns_404(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->getJson('/api/v1/purchases/orders/99999')
            ->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Purchase order not found.']);
    }
}
