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
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GoodsReceivingTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $noPermUser;
    protected Supplier $supplier;
    protected Store $store1;
    protected Store $store2;
    protected ProductVariantSize $variantSize1;
    protected PurchaseOrder $po1;

    protected function setUp(): void
    {
        parent::setUp();

        $permView = Permission::create(['name' => 'products.view', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Products']);
        $permEdit = Permission::create(['name' => 'products.edit', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Edit Products']);

        $superAdminRole = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdminRole->permissions()->attach([$permView->id, $permEdit->id]);

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

        $this->store1 = Store::create(['code' => 'ST-001', 'name' => 'Main Store', 'is_active' => true]);
        $this->store2 = Store::create(['code' => 'ST-002', 'name' => 'Branch Store', 'is_active' => true]);

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

        $this->po1 = PurchaseOrder::create([
            'po_number' => 'PO-TEST-REC-01',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store1->id,
            'order_date' => '2026-09-02',
            'status' => 'draft',
            'subtotal' => 25000.00,
            'grand_total' => 25000.00,
            'created_by' => $this->superAdmin->id,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $this->po1->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'quantity_ordered' => 50,
            'quantity_received' => 0,
            'cost_price' => 500.00,
            'mrp' => 1299.00,
            'selling_price' => 999.00,
            'total_cost' => 25000.00,
        ]);
    }

    public function test_1_unauthenticated_request_is_rejected(): void
    {
        $this->postJson("/api/v1/purchases/orders/{$this->po1->id}/receive", [])->assertStatus(401);
    }

    public function test_2_user_without_permission_is_denied(): void
    {
        Sanctum::actingAs($this->noPermUser);
        $this->postJson("/api/v1/purchases/orders/{$this->po1->id}/receive", [])->assertStatus(403);
    }

    public function test_3_partial_goods_receiving_increases_stock_and_updates_status(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->postJson("/api/v1/purchases/orders/{$this->po1->id}/receive", [
            'store_id' => $this->store1->id,
            'notes' => 'Received partial shipment 20 units',
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'quantity_received' => 20,
                ],
            ],
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'partial',
                    'items' => [
                        [
                            'sku' => 'ART805-BLK-08',
                            'quantity_ordered' => 50,
                            'quantity_received' => 20,
                        ],
                    ],
                ],
            ]);

        // VERIFY PHYSICAL STOCK INCREASE
        $stock = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)
            ->where('store_id', $this->store1->id)
            ->first();
        $this->assertNotNull($stock);
        $this->assertEquals(20, $stock->stock_quantity);

        // VERIFY STOCK MOVEMENT LEDGER
        $movement = StockMovement::where('product_variant_size_id', $this->variantSize1->id)->first();
        $this->assertNotNull($movement);
        $this->assertEquals('purchase_receive', is_object($movement->movement_type) ? $movement->movement_type->value : $movement->movement_type);
        $this->assertEquals(20, $movement->quantity_change);
        $this->assertEquals(0, $movement->stock_before);
        $this->assertEquals(20, $movement->stock_after);
    }

    public function test_4_full_goods_receiving_updates_status_to_received(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->postJson("/api/v1/purchases/orders/{$this->po1->id}/receive", [
            'store_id' => $this->store1->id,
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'quantity_received' => 50,
                ],
            ],
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'received',
                    'items' => [
                        [
                            'quantity_received' => 50,
                        ],
                    ],
                ],
            ]);

        $stock = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first();
        $this->assertEquals(50, $stock->stock_quantity);
    }

    public function test_5_over_receiving_rejected(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Attempting to receive 60 units on a 50 unit PO
        $res = $this->postJson("/api/v1/purchases/orders/{$this->po1->id}/receive", [
            'store_id' => $this->store1->id,
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'quantity_received' => 60,
                ],
            ],
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);

        // Verify stock was not modified
        $this->assertEquals(0, InventoryStock::count());
    }

    public function test_6_receiving_already_completed_po_rejected(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Mark PO as received
        $this->po1->status = 'received';
        $this->po1->save();

        $this->postJson("/api/v1/purchases/orders/{$this->po1->id}/receive", [
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'quantity_received' => 10,
                ],
            ],
        ])->assertStatus(422);
    }

    public function test_7_unauthorized_store_goods_receiving_rejected(): void
    {
        $restrictedUser = User::create([
            'name' => 'Restricted User',
            'username' => 'restr_usr',
            'email' => 'restr@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $role = Role::firstOrCreate(['name' => 'Store Manager', 'guard_name' => 'web']);
        $role->permissions()->attach(Permission::where('name', 'products.edit')->first()->id);
        $restrictedUser->roles()->attach($role->id, ['model_type' => User::class]);

        // User is attached ONLY to store2, NOT store1
        $restrictedUser->stores()->attach($this->store2->id);

        Sanctum::actingAs($restrictedUser);

        $this->postJson("/api/v1/purchases/orders/{$this->po1->id}/receive", [
            'store_id' => $this->store1->id,
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'quantity_received' => 10,
                ],
            ],
        ])->assertStatus(403);
    }

    public function test_8_nonexistent_po_returns_404(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->postJson('/api/v1/purchases/orders/99999/receive', [
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'quantity_received' => 10,
                ],
            ],
        ])->assertStatus(404);
    }
}
