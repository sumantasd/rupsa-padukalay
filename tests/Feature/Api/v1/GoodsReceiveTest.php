<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\GoodsReceive;
use App\Models\InventoryStock;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\PurchaseBill;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GoodsReceiveTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;
    protected Supplier $supplier;
    protected ProductVariantSize $pvs1;
    protected ProductVariantSize $pvs2;
    protected ProductVariantSize $pvs3;
    protected ProductVariantSize $pvs4;

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
            'username' => 'rupsa_grn_tester',
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
        $maroon = Color::create(['name' => 'Maroon', 'code' => 'MRN']);
        $black = Color::create(['name' => 'Black', 'code' => 'BLK']);

        $size6 = Size::create(['size_number' => '6', 'size_system' => 'IND', 'is_active' => true]);
        $size7 = Size::create(['size_number' => '7', 'size_system' => 'IND', 'is_active' => true]);
        $size8 = Size::create(['size_number' => '8', 'size_system' => 'IND', 'is_active' => true]);

        // Product 1: RP-401
        $p1 = Product::create([
            'article_number' => 'RP-401',
            'name' => 'Traditional Ethnic Embroidered Sandal',
            'slug' => 'traditional-ethnic-embroidered-sandal',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $v1 = ProductVariant::create(['product_id' => $p1->id, 'color_id' => $maroon->id, 'is_active' => true]);

        $this->pvs1 = ProductVariantSize::create([
            'product_variant_id' => $v1->id,
            'size_id' => $size6->id,
            'sku' => 'RP-401-MAROON-UK6',
            'cost_price' => 750.00,
            'selling_price' => 1250.00,
            'mrp' => 1500.00,
            'is_active' => true,
        ]);

        $this->pvs2 = ProductVariantSize::create([
            'product_variant_id' => $v1->id,
            'size_id' => $size7->id,
            'sku' => 'RP-401-MAROON-UK7',
            'cost_price' => 750.00,
            'selling_price' => 1250.00,
            'mrp' => 1500.00,
            'is_active' => true,
        ]);

        $this->pvs3 = ProductVariantSize::create([
            'product_variant_id' => $v1->id,
            'size_id' => $size8->id,
            'sku' => 'RP-401-MAROON-UK8',
            'cost_price' => 750.00,
            'selling_price' => 1250.00,
            'mrp' => 1500.00,
            'is_active' => true,
        ]);

        // Product 2: AR001
        $p2 = Product::create([
            'article_number' => 'AR001',
            'name' => 'Hooh Baby Chappal',
            'slug' => 'hooh-baby-chappal',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $v2 = ProductVariant::create(['product_id' => $p2->id, 'color_id' => $black->id, 'is_active' => true]);

        $this->pvs4 = ProductVariantSize::create([
            'product_variant_id' => $v2->id,
            'size_id' => $size6->id,
            'sku' => 'AR001-BLACK-UK6',
            'cost_price' => 359.00,
            'selling_price' => 399.00,
            'mrp' => 499.00,
            'is_active' => true,
        ]);

        // Initial zero stock
        foreach ([$this->pvs1, $this->pvs2, $this->pvs3, $this->pvs4] as $pvs) {
            InventoryStock::create([
                'product_variant_size_id' => $pvs->id,
                'store_id' => $this->store->id,
                'stock_quantity' => 0,
            ]);
        }
    }

    /** @test */
    public function full_goods_receive_workflow_increases_exact_sku_stock_and_creates_stock_movement(): void
    {
        // 1. Create Purchase Order with 4 size-wise items
        $poRes = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchases/orders', [
                'supplier_id' => $this->supplier->id,
                'store_id' => $this->store->id,
                'items' => [
                    ['product_variant_size_id' => $this->pvs1->id, 'quantity_ordered' => 6, 'cost_price' => 750.00],
                    ['product_variant_size_id' => $this->pvs2->id, 'quantity_ordered' => 6, 'cost_price' => 750.00],
                    ['product_variant_size_id' => $this->pvs3->id, 'quantity_ordered' => 6, 'cost_price' => 750.00],
                    ['product_variant_size_id' => $this->pvs4->id, 'quantity_ordered' => 6, 'cost_price' => 359.00],
                ],
            ]);

        $poRes->assertStatus(201);
        $poId = $poRes->json('data.id');

        // Verify stock is still 0 before GRN
        $this->assertEquals(0, InventoryStock::where('product_variant_size_id', $this->pvs1->id)->value('stock_quantity'));
        $this->assertEquals(0, InventoryStock::where('product_variant_size_id', $this->pvs4->id)->value('stock_quantity'));

        // 2. Confirm GRN
        $po = PurchaseOrder::with('items')->find($poId);

        $grnRes = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchases/grn', [
                'purchase_order_id' => $poId,
                'supplier_invoice_number' => 'INV-2026-99',
                'notes' => 'Receiving 6 pairs of each footwear size',
                'items' => [
                    ['purchase_order_item_id' => $po->items[0]->id, 'quantity_received_now' => 6],
                    ['purchase_order_item_id' => $po->items[1]->id, 'quantity_received_now' => 6],
                    ['purchase_order_item_id' => $po->items[2]->id, 'quantity_received_now' => 6],
                    ['purchase_order_item_id' => $po->items[3]->id, 'quantity_received_now' => 6],
                ],
            ]);

        $grnRes->assertStatus(201)
            ->assertJsonPath('success', true);

        // 3. Assert Exact Stock Amounts Received
        $this->assertEquals(6, InventoryStock::where('product_variant_size_id', $this->pvs1->id)->value('stock_quantity'));
        $this->assertEquals(6, InventoryStock::where('product_variant_size_id', $this->pvs2->id)->value('stock_quantity'));
        $this->assertEquals(6, InventoryStock::where('product_variant_size_id', $this->pvs3->id)->value('stock_quantity'));
        $this->assertEquals(6, InventoryStock::where('product_variant_size_id', $this->pvs4->id)->value('stock_quantity'));

        // 4. Assert Auditable Stock Movements (type = PURCHASE)
        $this->assertDatabaseHas('stock_movements', [
            'product_variant_size_id' => $this->pvs1->id,
            'quantity_change' => 6,
            'stock_after' => 6,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_variant_size_id' => $this->pvs4->id,
            'quantity_change' => 6,
            'stock_after' => 6,
        ]);

        // 5. Assert Purchase Bill created & linked to supplier
        $this->assertDatabaseHas('purchase_bills', [
            'purchase_order_id' => $poId,
            'supplier_id' => $this->supplier->id,
            'payment_status' => 'unpaid',
        ]);

        // 6. Assert PO Status updated to fully received
        $this->assertEquals('received', PurchaseOrder::find($poId)->status->value ?? PurchaseOrder::find($poId)->status);
    }

    /** @test */
    public function grn_confirmation_blocks_over_receiving(): void
    {
        $poRes = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchases/orders', [
                'supplier_id' => $this->supplier->id,
                'store_id' => $this->store->id,
                'items' => [
                    ['product_variant_size_id' => $this->pvs1->id, 'quantity_ordered' => 5, 'cost_price' => 750.00],
                ],
            ]);

        $poId = $poRes->json('data.id');
        $po = PurchaseOrder::with('items')->find($poId);

        // Attempt receiving 10 units when only 5 were ordered
        $grnRes = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchases/grn', [
                'purchase_order_id' => $poId,
                'items' => [
                    ['purchase_order_item_id' => $po->items[0]->id, 'quantity_received_now' => 10],
                ],
            ]);

        $grnRes->assertStatus(422)
            ->assertJsonPath('success', false);

        // Assert no stock was added
        $this->assertEquals(0, InventoryStock::where('product_variant_size_id', $this->pvs1->id)->value('stock_quantity'));
    }

    /** @test */
    public function grn_confirmation_blocks_duplicate_receiving_on_fully_received_po(): void
    {
        $poRes = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchases/orders', [
                'supplier_id' => $this->supplier->id,
                'store_id' => $this->store->id,
                'items' => [
                    ['product_variant_size_id' => $this->pvs1->id, 'quantity_ordered' => 5, 'cost_price' => 750.00],
                ],
            ]);

        $poId = $poRes->json('data.id');
        $po = PurchaseOrder::with('items')->find($poId);

        // 1st receive: 5 units
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchases/grn', [
                'purchase_order_id' => $poId,
                'items' => [
                    ['purchase_order_item_id' => $po->items[0]->id, 'quantity_received_now' => 5],
                ],
            ])->assertStatus(201);

        // 2nd receive attempt on fully received PO should be rejected with 422
        $grnRes = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchases/grn', [
                'purchase_order_id' => $poId,
                'items' => [
                    ['purchase_order_item_id' => $po->items[0]->id, 'quantity_received_now' => 5],
                ],
            ]);

        $grnRes->assertStatus(422);

        // Assert stock remains exactly 5 (not duplicated to 10)
        $this->assertEquals(5, InventoryStock::where('product_variant_size_id', $this->pvs1->id)->value('stock_quantity'));
    }

    /** @test */
    public function purchase_order_detail_api_returns_complete_printable_payload(): void
    {
        $poRes = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchases/orders', [
                'supplier_id' => $this->supplier->id,
                'store_id' => $this->store->id,
                'items' => [
                    ['product_variant_size_id' => $this->pvs1->id, 'quantity_ordered' => 6, 'cost_price' => 750.00],
                    ['product_variant_size_id' => $this->pvs4->id, 'quantity_ordered' => 6, 'cost_price' => 359.00],
                ],
            ]);

        $poId = $poRes->json('data.id');

        $showRes = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/purchases/orders/{$poId}");

        $showRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.supplier_name', 'Fine Leathers')
            ->assertJsonPath('data.supplier_company', 'Fine Leathers Ltd')
            ->assertJsonPath('data.supplier_phone', '9876543210')
            ->assertJsonPath('data.items.0.article_number', 'RP-401')
            ->assertJsonPath('data.items.0.sku', 'RP-401-MAROON-UK6')
            ->assertJsonPath('data.items.0.size_display', 'IND 6')
            ->assertJsonPath('data.items.0.quantity_ordered', 6)
            ->assertJsonPath('data.items.0.cost_price', 750);
    }
}
