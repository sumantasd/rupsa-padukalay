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
use App\Models\PurchaseReturn;
use App\Models\Role;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseModuleCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;
    protected Supplier $supplier;
    protected ProductVariantSize $variantSize;

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
            'username' => 'admin_test_' . uniqid(),
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
            'procurement.view', 'procurement.receive',
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
            'name' => 'ABC Footwear Supplier',
            'company_name' => 'ABC Footwear Ltd',
            'phone' => '9830000000',
            'gstin' => '19ABCDE1234F1Z5',
            'current_balance' => 0.00,
            'is_active' => true,
        ]);

        $brand = Brand::create(['name' => 'Bata', 'slug' => 'bata', 'code' => 'BATA', 'is_active' => true]);
        $category = Category::create(['name' => 'Men Shoes', 'slug' => 'men-shoes', 'code' => 'MEN-SHOE', 'is_active' => true]);
        $color = Color::create(['name' => 'Black', 'code' => 'BLK']);
        $size = Size::create(['size_number' => '8', 'size_system' => 'IND', 'is_active' => true]);

        $product = Product::create([
            'article_number' => 'ART-999',
            'name' => 'Formal Leather Shoe',
            'slug' => 'formal-leather-shoe',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color_id' => $color->id,
        ]);

        $this->variantSize = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $size->id,
            'sku' => 'ART-999-BLK-IND8',
            'cost_price' => 500.00,
            'mrp' => 1000.00,
            'selling_price' => 800.00,
        ]);

        InventoryStock::create([
            'product_variant_size_id' => $this->variantSize->id,
            'store_id' => $this->store->id,
            'stock_quantity' => 0,
            'reorder_level' => 5,
        ]);
    }

    /** @test */
    public function it_rejects_purchase_order_creation_without_a_supplier(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchases/orders', [
                'supplier_id' => null,
                'items' => [
                    [
                        'product_variant_size_id' => $this->variantSize->id,
                        'quantity_ordered' => 10,
                        'cost_price' => 500.00,
                    ],
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['supplier_id']);
    }

    /** @test */
    public function it_creates_purchase_order_with_supplier_without_altering_inventory(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchases/orders', [
                'supplier_id' => $this->supplier->id,
                'store_id' => $this->store->id,
                'order_date' => '2026-09-03',
                'items' => [
                    [
                        'product_variant_size_id' => $this->variantSize->id,
                        'quantity_ordered' => 10,
                        'cost_price' => 500.00,
                    ],
                ],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('purchase_orders', [
            'supplier_id' => $this->supplier->id,
            'grand_total' => 5000.00,
        ]);

        // Inventory stock MUST NOT increase at PO creation
        $inv = InventoryStock::where('product_variant_size_id', $this->variantSize->id)->first();
        $this->assertEquals(0, $inv->stock_quantity);
    }

    /** @test */
    public function it_receives_goods_increases_inventory_and_logs_purchase_stock_movement(): void
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-001',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'order_date' => '2026-09-03',
            'status' => 'ordered',
            'subtotal' => 5000.00,
            'grand_total' => 5000.00,
            'due_amount' => 5000.00,
            'created_by' => $this->user->id,
        ]);

        $poItem = $po->items()->create([
            'product_variant_size_id' => $this->variantSize->id,
            'quantity_ordered' => 10,
            'quantity_received' => 0,
            'cost_price' => 500.00,
            'mrp' => 1000.00,
            'selling_price' => 800.00,
            'total_cost' => 5000.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchases/grn', [
                'purchase_order_id' => $po->id,
                'supplier_invoice_number' => 'INV-SUP-101',
                'items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'quantity_received_now' => 10,
                    ],
                ],
            ]);

        $response->assertStatus(201);

        // Physical inventory stock must now equal 10
        $inv = InventoryStock::where('product_variant_size_id', $this->variantSize->id)->first();
        $this->assertEquals(10, $inv->stock_quantity);

        // Stock movement log must exist with movement_type = PURCHASE
        $this->assertDatabaseHas('stock_movements', [
            'product_variant_size_id' => $this->variantSize->id,
            'quantity_change' => 10,
            'movement_type' => 'purchase',
        ]);

        // Automatic purchase bill must exist
        $this->assertDatabaseHas('purchase_bills', [
            'supplier_id' => $this->supplier->id,
            'grand_total' => 5000.00,
        ]);
    }

    /** @test */
    public function it_prevents_over_receiving_goods_beyond_remaining_po_quantity(): void
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-OVER',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'order_date' => '2026-09-03',
            'status' => 'ordered',
            'subtotal' => 5000.00,
            'grand_total' => 5000.00,
            'due_amount' => 5000.00,
            'created_by' => $this->user->id,
        ]);

        $poItem = $po->items()->create([
            'product_variant_size_id' => $this->variantSize->id,
            'quantity_ordered' => 10,
            'quantity_received' => 0,
            'cost_price' => 500.00,
            'mrp' => 1000.00,
            'selling_price' => 800.00,
            'total_cost' => 5000.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchases/grn', [
                'purchase_order_id' => $po->id,
                'items' => [
                    [
                        'purchase_order_item_id' => $poItem->id,
                        'quantity_received_now' => 15, // OVER RECEIVING REJECTED!
                    ],
                ],
            ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_records_supplier_payment_and_updates_supplier_outstanding_due(): void
    {
        $bill = PurchaseBill::create([
            'bill_number' => 'BILL-TEST-001',
            'supplier_invoice_number' => 'INV-555',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'bill_date' => '2026-09-03',
            'subtotal' => 5000.00,
            'grand_total' => 5000.00,
            'paid_amount' => 0.00,
            'due_amount' => 5000.00,
            'payment_status' => 'unpaid',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/purchases/bills/{$bill->id}/pay", [
                'amount' => 2000.00,
                'payment_method' => 'cash',
                'transaction_reference' => 'TXN-999',
            ]);

        $response->assertStatus(200);

        // Bill paid amount should be 2000 and due 3000
        $this->assertDatabaseHas('purchase_bills', [
            'id' => $bill->id,
            'paid_amount' => 2000.00,
            'due_amount' => 3000.00,
            'payment_status' => 'partially_paid',
        ]);

        // Dynamic supplier calculation check
        $balances = $this->supplier->calculateBalances();
        $this->assertEquals(3000.00, $balances['current_due']);
    }

    /** @test */
    public function it_processes_purchase_return_deducts_inventory_and_adjusts_supplier_liability(): void
    {
        // Set initial stock to 10
        InventoryStock::where('product_variant_size_id', $this->variantSize->id)->update(['stock_quantity' => 10]);

        $bill = PurchaseBill::create([
            'bill_number' => 'BILL-TEST-RET',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'bill_date' => '2026-09-03',
            'subtotal' => 5000.00,
            'grand_total' => 5000.00,
            'paid_amount' => 2000.00,
            'due_amount' => 3000.00,
            'payment_status' => 'partially_paid',
            'created_by' => $this->user->id,
        ]);

        SupplierPayment::create([
            'payment_number' => 'PAY-TEST-RET',
            'supplier_id' => $this->supplier->id,
            'purchase_bill_id' => $bill->id,
            'payment_date' => '2026-09-03',
            'amount' => 2000.00,
            'payment_method' => 'cash',
            'recorded_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchases/returns', [
                'supplier_id' => $this->supplier->id,
                'purchase_bill_id' => $bill->id,
                'reason' => 'Damaged',
                'refund_mode' => 'supplier_credit',
                'items' => [
                    [
                        'product_variant_size_id' => $this->variantSize->id,
                        'quantity' => 2,
                        'cost_price' => 500.00,
                    ],
                ],
            ]);

        $response->assertStatus(201);

        // Inventory must now decrease from 10 to 8
        $inv = InventoryStock::where('product_variant_size_id', $this->variantSize->id)->first();
        $this->assertEquals(8, $inv->stock_quantity);

        // Stock movement log must exist with movement_type = purchase_return
        $this->assertDatabaseHas('stock_movements', [
            'product_variant_size_id' => $this->variantSize->id,
            'quantity_change' => -2,
            'movement_type' => 'purchase_return',
        ]);

        // Supplier due should be (5000 - 1000 return) - 2000 paid = 2000
        $balances = $this->supplier->calculateBalances();
        $this->assertEquals(2000.00, $balances['current_due']);
    }

    /** @test */
    public function it_returns_purchase_order_details_with_item_product_images(): void
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-DETAILS',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'order_date' => '2026-09-04',
            'status' => 'ordered',
            'subtotal' => 500.00,
            'grand_total' => 500.00,
            'due_amount' => 500.00,
            'created_by' => $this->user->id,
        ]);

        $po->items()->create([
            'product_variant_size_id' => $this->variantSize->id,
            'quantity_ordered' => 1,
            'quantity_received' => 0,
            'cost_price' => 500.00,
            'mrp' => 1000.00,
            'selling_price' => 800.00,
            'total_cost' => 500.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/purchases/orders/{$po->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.po_number', 'PO-TEST-DETAILS')
            ->assertJsonPath('data.supplier_name', 'ABC Footwear Supplier')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'po_number',
                    'supplier_name',
                    'supplier_phone',
                    'supplier_gstin',
                    'items' => [
                        '*' => [
                            'id',
                            'product_variant_size_id',
                            'article_number',
                            'product_name',
                            'brand_name',
                            'size_display',
                            'quantity_ordered',
                            'cost_price',
                            'total_cost',
                            'image_url',
                            'primary_image_url',
                            'product_image',
                        ],
                    ],
                ],
            ]);
    }

    /** @test */
    public function it_searches_product_size_matrix_by_article_number_and_brand(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/products/size-matrix-search?q=ART-999');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'article_number' => 'ART-999',
                'product_name' => 'Formal Leather Shoe',
                'brand_name' => 'Bata',
            ]);

        $item = $response->json('data.0');
        $this->assertArrayHasKey('image_url', $item);
        $this->assertArrayHasKey('primary_image_url', $item);
        $this->assertArrayHasKey('product_image', $item);
        if ($item['image_url']) {
            $this->assertStringNotContainsString('/storage/storage/', $item['image_url']);
        }
    }

    /** @test */
    public function it_loads_suppliers_list_with_procurement_view_permission(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/suppliers');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'code' => 'SUP-0001',
                'name' => 'ABC Footwear Supplier',
            ]);
    }
}
