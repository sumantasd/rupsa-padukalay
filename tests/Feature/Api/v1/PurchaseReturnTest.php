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
use App\Models\PurchaseReturn;
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

class PurchaseReturnTest extends TestCase
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

        // Create PO in 'received' status
        $this->po1 = PurchaseOrder::create([
            'po_number' => 'PO-TEST-RET-01',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store1->id,
            'order_date' => '2026-09-02',
            'status' => 'received',
            'subtotal' => 25000.00,
            'grand_total' => 25000.00,
            'created_by' => $this->superAdmin->id,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $this->po1->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'quantity_ordered' => 50,
            'quantity_received' => 50,
            'cost_price' => 500.00,
            'mrp' => 1299.00,
            'selling_price' => 999.00,
            'total_cost' => 25000.00,
        ]);

        // Physical inventory stock = 50
        InventoryStock::create([
            'product_variant_size_id' => $this->variantSize1->id,
            'store_id' => $this->store1->id,
            'stock_quantity' => 50,
        ]);
    }

    public function test_1_unauthenticated_request_is_rejected(): void
    {
        $this->postJson("/api/v1/purchases/orders/{$this->po1->id}/return", [])->assertStatus(401);
    }

    public function test_2_user_without_permission_is_denied(): void
    {
        Sanctum::actingAs($this->noPermUser);
        $this->postJson("/api/v1/purchases/orders/{$this->po1->id}/return", [])->assertStatus(403);
    }

    public function test_3_successful_purchase_return_decreases_stock_and_creates_movement_ledger(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->postJson("/api/v1/purchases/orders/{$this->po1->id}/return", [
            'store_id' => $this->store1->id,
            'reason' => 'Defective batch return',
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'quantity' => 10,
                ],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_return_amount' => 5000.00,
                    'reason' => 'Defective batch return',
                    'items' => [
                        [
                            'sku' => 'ART805-BLK-08',
                            'quantity' => 10,
                            'cost_price' => 500.00,
                            'subtotal' => 5000.00,
                        ],
                    ],
                ],
            ]);

        // VERIFY PHYSICAL INVENTORY DEDUCTION (50 - 10 = 40)
        $stock = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)
            ->where('store_id', $this->store1->id)
            ->first();
        $this->assertEquals(40, $stock->stock_quantity);

        // VERIFY STOCK MOVEMENT LEDGER
        $movement = StockMovement::where('product_variant_size_id', $this->variantSize1->id)->first();
        $this->assertNotNull($movement);
        $this->assertEquals('purchase_return', is_object($movement->movement_type) ? $movement->movement_type->value : $movement->movement_type);
        $this->assertEquals(-10, $movement->quantity_change);
        $this->assertEquals(50, $movement->stock_before);
        $this->assertEquals(40, $movement->stock_after);
    }

    public function test_4_return_quantity_cannot_exceed_received_quantity(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Attempting to return 60 units when only 50 were received
        $res = $this->postJson("/api/v1/purchases/orders/{$this->po1->id}/return", [
            'store_id' => $this->store1->id,
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'quantity' => 60,
                ],
            ],
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_5_return_quantity_cannot_exceed_available_physical_stock(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Set physical stock = 5
        $stock = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first();
        $stock->stock_quantity = 5;
        $stock->save();

        // Attempting to return 10 units when physical stock is only 5
        $res = $this->postJson("/api/v1/purchases/orders/{$this->po1->id}/return", [
            'store_id' => $this->store1->id,
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'quantity' => 10,
                ],
            ],
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_6_already_returned_quantity_is_respected(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // First return: 30 units
        $this->postJson("/api/v1/purchases/orders/{$this->po1->id}/return", [
            'store_id' => $this->store1->id,
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'quantity' => 30,
                ],
            ],
        ])->assertStatus(201);

        // Second return attempt of 30 units (30 + 30 = 60 > 50 received) -> rejected
        $this->postJson("/api/v1/purchases/orders/{$this->po1->id}/return", [
            'store_id' => $this->store1->id,
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'quantity' => 30,
                ],
            ],
        ])->assertStatus(422);

        // Valid second return of remaining 20 units -> succeeds
        $this->postJson("/api/v1/purchases/orders/{$this->po1->id}/return", [
            'store_id' => $this->store1->id,
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'quantity' => 20,
                ],
            ],
        ])->assertStatus(201);
    }

    public function test_7_invalid_po_or_item_relationship_rejected(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $otherSize = ProductVariantSize::create([
            'product_variant_id' => $this->variantSize1->product_variant_id,
            'size_id' => Size::create(['size_number' => '09', 'sort_order' => 9])->id,
            'sku' => 'ART805-BLK-09',
            'mrp' => 1299.00,
            'selling_price' => 999.00,
        ]);

        // Attempt to return an SKU that does NOT belong to this PO
        $this->postJson("/api/v1/purchases/orders/{$this->po1->id}/return", [
            'store_id' => $this->store1->id,
            'items' => [
                [
                    'sku' => 'ART805-BLK-09',
                    'quantity' => 5,
                ],
            ],
        ])->assertStatus(422);
    }

    public function test_8_cancelled_or_ineligible_po_rejected(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->po1->status = 'draft';
        $this->po1->save();

        $this->postJson("/api/v1/purchases/orders/{$this->po1->id}/return", [
            'store_id' => $this->store1->id,
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'quantity' => 5,
                ],
            ],
        ])->assertStatus(422);
    }

    public function test_9_atomic_rollback_on_failed_multi_item_return(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Attempt return with 1 valid item (10 units) and 1 invalid item (60 units > 50)
        $res = $this->postJson("/api/v1/purchases/orders/{$this->po1->id}/return", [
            'store_id' => $this->store1->id,
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'quantity' => 60,
                ],
            ],
        ]);

        $res->assertStatus(422);

        // Rollback check: physical stock remains 50, no purchase return records created
        $stock = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first();
        $this->assertEquals(50, $stock->stock_quantity);
        $this->assertEquals(0, PurchaseReturn::count());
        $this->assertEquals(0, StockMovement::count());
    }

    public function test_10_unauthorized_store_access_rejected(): void
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

        // User attached only to store2, NOT store1
        $restrictedUser->stores()->attach($this->store2->id);

        Sanctum::actingAs($restrictedUser);

        $this->postJson("/api/v1/purchases/orders/{$this->po1->id}/return", [
            'store_id' => $this->store1->id,
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'quantity' => 5,
                ],
            ],
        ])->assertStatus(403);
    }

    public function test_11_super_admin_can_process_return_across_stores(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->postJson("/api/v1/purchases/orders/{$this->po1->id}/return", [
            'store_id' => $this->store1->id,
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'quantity' => 5,
                ],
            ],
        ])->assertStatus(201);
    }

    public function test_12_listing_filtering_and_detail_endpoints_work(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $pr = PurchaseReturn::create([
            'return_number' => 'PR-TEST-01',
            'purchase_order_id' => $this->po1->id,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store1->id,
            'total_return_amount' => 2500.00,
            'processed_by' => $this->superAdmin->id,
        ]);

        $this->getJson('/api/v1/purchases/returns?search=PR-TEST')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');

        $this->getJson("/api/v1/purchases/returns/{$pr->id}")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['return_number' => 'PR-TEST-01']]);
    }

    public function test_13_nonexistent_po_or_return_returns_404(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->getJson('/api/v1/purchases/returns/99999')->assertStatus(404);
        $this->postJson('/api/v1/purchases/orders/99999/return', ['items' => [['sku' => 'ART805-BLK-08', 'quantity' => 1]]])->assertStatus(404);
    }
}
