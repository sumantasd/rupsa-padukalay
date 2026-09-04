<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Permission;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\ReturnItem;
use App\Models\ReturnSale;
use App\Models\Role;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesReturnTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $cashier;
    protected User $noPermUser;
    protected Store $store1;
    protected Store $store2;
    protected Customer $customer;
    protected ProductVariantSize $variantSize1;
    protected ProductVariantSize $variantSize2;
    protected PosSession $openSession1;
    protected Invoice $completedInvoice;
    protected InvoiceItem $invoiceItem1;
    protected InvoiceItem $invoiceItem2;

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

        $cashierRole = Role::create(['name' => 'Cashier', 'guard_name' => 'web']);
        $cashierRole->permissions()->attach([$permView->id, $permCreate->id, $permEdit->id]);

        $this->store1 = Store::create(['code' => 'ST-001', 'name' => 'Main Store', 'is_active' => true]);
        $this->store2 = Store::create(['code' => 'ST-002', 'name' => 'Branch Store', 'is_active' => true]);

        $this->cashier = User::create([
            'name' => 'John Cashier',
            'username' => 'john_cashier',
            'email' => 'cashier@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->cashier->roles()->attach($cashierRole->id, ['model_type' => User::class]);
        $this->cashier->stores()->attach($this->store1->id);

        $this->noPermUser = User::create([
            'name' => 'No Perm User',
            'username' => 'noperm_user',
            'email' => 'noperm@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'Rahul Sen',
            'mobile_number' => '9832298322',
            'city' => 'Kolkata',
        ]);

        $brand = Brand::create(['name' => 'Bata', 'slug' => 'bata', 'is_active' => true]);
        $category = Category::create(['name' => 'Formal', 'slug' => 'formal', 'is_active' => true]);
        $colorBlack = Color::create(['name' => 'Black', 'code' => 'BLK']);
        $colorBrown = Color::create(['name' => 'Brown', 'code' => 'BRN']);
        $size8 = Size::create(['size_number' => '08', 'size_system' => 'UK', 'sort_order' => 8]);

        $product = Product::create([
            'article_number' => 'ART805',
            'name' => 'Men Formal Shoe',
            'slug' => 'art805-men-formal-shoe',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
        ]);

        $variant1 = ProductVariant::create(['product_id' => $product->id, 'color_id' => $colorBlack->id]);
        $variant2 = ProductVariant::create(['product_id' => $product->id, 'color_id' => $colorBrown->id]);

        $this->variantSize1 = ProductVariantSize::create([
            'product_variant_id' => $variant1->id,
            'size_id' => $size8->id,
            'sku' => 'ART805-BLK-08',
            'barcode' => '8901234567890',
            'cost_price' => 500.00,
            'mrp' => 1299.00,
            'selling_price' => 1000.00,
        ]);

        $this->variantSize2 = ProductVariantSize::create([
            'product_variant_id' => $variant2->id,
            'size_id' => $size8->id,
            'sku' => 'ART805-BRN-08',
            'barcode' => '8901234567891',
            'cost_price' => 550.00,
            'mrp' => 1399.00,
            'selling_price' => 1100.00,
        ]);

        InventoryStock::create(['product_variant_size_id' => $this->variantSize1->id, 'store_id' => $this->store1->id, 'stock_quantity' => 10]);
        InventoryStock::create(['product_variant_size_id' => $this->variantSize2->id, 'store_id' => $this->store1->id, 'stock_quantity' => 10]);

        $this->openSession1 = PosSession::create([
            'store_id' => $this->store1->id,
            'user_id' => $this->cashier->id,
            'opened_at' => now(),
            'opening_cash' => 1000.00,
            'closing_cash_system' => 1000.00,
            'status' => 'open',
        ]);

        // Completed Sale Invoice (Item 1: 5 units @ 1000 = 5000, Item 2: 2 units @ 1100 = 2200) Total = 7200
        $this->completedInvoice = Invoice::create([
            'invoice_number' => 'INV-SALE-001',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 7200.00,
            'discount_amount' => 0.00,
            'grand_total' => 7200.00,
            'paid_amount' => 7200.00,
            'payment_status' => 'paid',
            'sale_type' => 'pos_counter',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        $this->invoiceItem1 = InvoiceItem::create([
            'invoice_id' => $this->completedInvoice->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'sku_snapshot' => $this->variantSize1->sku,
            'article_number_snapshot' => 'ART805',
            'product_name_snapshot' => 'Men Formal Shoe',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '08',
            'cost_price' => 500.00,
            'mrp' => 1299.00,
            'unit_price' => 1000.00,
            'quantity' => 5,
            'subtotal' => 5000.00,
        ]);

        $this->invoiceItem2 = InvoiceItem::create([
            'invoice_id' => $this->completedInvoice->id,
            'product_variant_size_id' => $this->variantSize2->id,
            'sku_snapshot' => $this->variantSize2->sku,
            'article_number_snapshot' => 'ART805',
            'product_name_snapshot' => 'Men Formal Shoe',
            'color_name_snapshot' => 'Brown',
            'size_number_snapshot' => '08',
            'cost_price' => 550.00,
            'mrp' => 1399.00,
            'unit_price' => 1100.00,
            'quantity' => 2,
            'subtotal' => 2200.00,
        ]);

        InvoicePayment::create([
            'invoice_id' => $this->completedInvoice->id,
            'payment_method' => 'cash',
            'amount' => 7200.00,
            'payment_time' => now(),
        ]);
    }

    public function test_1_unauthenticated_request_rejected(): void
    {
        $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/return", [
            'items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 1]],
        ])->assertStatus(401);
    }

    public function test_2_user_without_permission_rejected(): void
    {
        Sanctum::actingAs($this->noPermUser);

        $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/return", [
            'items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 1]],
        ])->assertStatus(403);
    }

    public function test_3_authorized_user_can_create_sales_return(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/return", [
            'refund_mode' => 'cash',
            'reason' => 'Wrong size bought',
            'items' => [
                ['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 2, 'restock_condition' => 'resellable'],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'original_invoice_id' => $this->completedInvoice->id,
                    'store_id' => $this->store1->id,
                    'total_refund_amount' => 2000.00,
                    'refund_mode' => 'cash',
                    'items' => [
                        [
                            'invoice_item_id' => $this->invoiceItem1->id,
                            'quantity' => 2,
                            'refund_unit_price' => 1000.00,
                            'subtotal' => 2000.00,
                        ],
                    ],
                ],
            ]);
    }

    public function test_4_nonexistent_sale_returns_404(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->postJson('/api/v1/pos/sales/999999/return', [
            'items' => [['sku' => 'ART805-BLK-08', 'quantity' => 1]],
        ])->assertStatus(404);
    }

    public function test_5_invalid_sale_item_relationship_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        // Create another invoice with an item
        $otherInvoice = Invoice::create([
            'invoice_number' => 'INV-OTHER-001',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'paid',
            'sale_type' => 'pos_counter',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        $otherItem = InvoiceItem::create([
            'invoice_id' => $otherInvoice->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'sku_snapshot' => 'ART805-BLK-08',
            'article_number_snapshot' => 'ART805',
            'product_name_snapshot' => 'Men Formal Shoe',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '08',
            'cost_price' => 500.00,
            'mrp' => 1299.00,
            'unit_price' => 1000.00,
            'quantity' => 1,
            'subtotal' => 1000.00,
        ]);

        // Attempting to return otherItem against $this->completedInvoice
        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/return", [
            'items' => [['invoice_item_id' => $otherItem->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_6_return_quantity_cannot_exceed_sold_quantity(): void
    {
        Sanctum::actingAs($this->cashier);

        // Sold quantity is 5 for invoiceItem1. Attempting to return 6 must fail.
        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/return", [
            'items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 6]],
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_7_already_returned_quantity_is_respected(): void
    {
        Sanctum::actingAs($this->cashier);

        // Return 3 out of 5 units first
        $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/return", [
            'items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 3]],
        ])->assertStatus(201);

        // Remaining returnable is 2. Attempting to return 3 more must fail.
        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/return", [
            'items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 3]],
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_8_partial_return_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/return", [
            'items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 2]],
        ]);

        $res->assertStatus(201);

        // Invoice status should be partially_returned
        $statusVal = is_object($this->completedInvoice->fresh()->status) ? $this->completedInvoice->fresh()->status->value : $this->completedInvoice->fresh()->status;
        $this->assertEquals('partially_returned', $statusVal);
    }

    public function test_9_multiple_item_return_works(): void
    {
        Sanctum::actingAs($this->cashier);

        // Return all 5 units of item1 and all 2 units of item2
        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/return", [
            'items' => [
                ['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 5],
                ['invoice_item_id' => $this->invoiceItem2->id, 'quantity' => 2],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'data' => [
                    'total_refund_amount' => 7200.00,
                ],
            ]);

        // Invoice status should now be 100% returned
        $statusVal = is_object($this->completedInvoice->fresh()->status) ? $this->completedInvoice->fresh()->status->value : $this->completedInvoice->fresh()->status;
        $this->assertEquals('returned', $statusVal);
    }

    public function test_10_normal_return_increases_sellable_inventory(): void
    {
        Sanctum::actingAs($this->cashier);

        // Physical stock before return = 10
        $stockBefore = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)
            ->where('store_id', $this->store1->id)
            ->first()->stock_quantity;
        $this->assertEquals(10, $stockBefore);

        // Return 3 resellable units
        $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/return", [
            'items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 3, 'restock_condition' => 'resellable']],
        ])->assertStatus(201);

        // Physical stock after return = 10 + 3 = 13
        $stockAfter = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)
            ->where('store_id', $this->store1->id)
            ->first()->stock_quantity;
        $this->assertEquals(13, $stockAfter);
    }

    public function test_11_sale_return_movement_ledger_is_created(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/return", [
            'items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 2, 'restock_condition' => 'resellable']],
        ])->assertStatus(201);

        $movement = StockMovement::where('product_variant_size_id', $this->variantSize1->id)->latest('id')->first();
        $this->assertNotNull($movement);
        $typeVal = is_object($movement->movement_type) ? $movement->movement_type->value : $movement->movement_type;
        $this->assertEquals('sale_return', $typeVal);
        $this->assertEquals(2, $movement->quantity_change);
    }

    public function test_12_stock_before_and_stock_after_are_correct(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/return", [
            'items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 4, 'restock_condition' => 'resellable']],
        ])->assertStatus(201);

        $movement = StockMovement::where('product_variant_size_id', $this->variantSize1->id)->latest('id')->first();
        $this->assertEquals(10, $movement->stock_before);
        $this->assertEquals(14, $movement->stock_after);
    }

    public function test_13_damaged_goods_return_does_not_increase_sellable_stock(): void
    {
        Sanctum::actingAs($this->cashier);

        $stockBefore = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first()->stock_quantity;

        // Return 2 damaged units
        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/return", [
            'items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 2, 'restock_condition' => 'damaged']],
        ]);

        $res->assertStatus(201);

        // Sellable physical stock must NOT change
        $stockAfter = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first()->stock_quantity;
        $this->assertEquals($stockBefore, $stockAfter);

        // Return item record should preserve damaged condition
        $retItem = ReturnItem::where('invoice_item_id', $this->invoiceItem1->id)->first();
        $condVal = is_object($retItem->restock_condition) ? $retItem->restock_condition->value : $retItem->restock_condition;
        $this->assertEquals('damaged', $condVal);
    }

    public function test_14_atomic_rollback_when_one_return_item_fails(): void
    {
        Sanctum::actingAs($this->cashier);

        // Return array: 1 valid (2 units of item1) + 1 invalid (10 units of item2 > 2 available)
        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/return", [
            'items' => [
                ['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 2],
                ['invoice_item_id' => $this->invoiceItem2->id, 'quantity' => 10],
            ],
        ]);

        $res->assertStatus(422);

        // Assert atomic rollback: no returns created, inventory unchanged
        $this->assertEquals(0, ReturnSale::count());
        $this->assertEquals(0, ReturnItem::count());
        $this->assertEquals(10, InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first()->stock_quantity);
    }

    public function test_15_unauthorized_store_return_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        // Invoice belonging to Store 2 (which cashier is NOT authorized to access)
        $store2Invoice = Invoice::create([
            'invoice_number' => 'INV-STORE2-RETURN',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store2->id,
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'paid',
            'sale_type' => 'pos_counter',
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
        ]);

        $store2Item = InvoiceItem::create([
            'invoice_id' => $store2Invoice->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'sku_snapshot' => 'ART805-BLK-08',
            'article_number_snapshot' => 'ART805',
            'product_name_snapshot' => 'Men Formal Shoe',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '08',
            'cost_price' => 500.00,
            'mrp' => 1299.00,
            'unit_price' => 1000.00,
            'quantity' => 1,
            'subtotal' => 1000.00,
        ]);

        $this->postJson("/api/v1/pos/sales/{$store2Invoice->id}/return", [
            'items' => [['invoice_item_id' => $store2Item->id, 'quantity' => 1]],
        ])->assertStatus(403);
    }

    public function test_16_super_admin_cross_store_return_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $store2Invoice = Invoice::create([
            'invoice_number' => 'INV-STORE2-SA',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store2->id,
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'paid',
            'sale_type' => 'pos_counter',
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
        ]);

        $store2Item = InvoiceItem::create([
            'invoice_id' => $store2Invoice->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'sku_snapshot' => 'ART805-BLK-08',
            'article_number_snapshot' => 'ART805',
            'product_name_snapshot' => 'Men Formal Shoe',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '08',
            'cost_price' => 500.00,
            'mrp' => 1299.00,
            'unit_price' => 1000.00,
            'quantity' => 1,
            'subtotal' => 1000.00,
        ]);

        InventoryStock::create(['product_variant_size_id' => $this->variantSize1->id, 'store_id' => $this->store2->id, 'stock_quantity' => 5]);

        $res = $this->postJson("/api/v1/pos/sales/{$store2Invoice->id}/return", [
            'items' => [['invoice_item_id' => $store2Item->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(201)->assertJson(['success' => true]);
    }

    public function test_17_return_listing_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/return", [
            'items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 1]],
        ])->assertStatus(201);

        $res = $this->getJson('/api/v1/pos/sales/returns');

        $res->assertStatus(200)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_18_return_filtering_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/return", [
            'refund_mode' => 'upi',
            'items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 1]],
        ])->assertStatus(201);

        $res = $this->getJson('/api/v1/pos/sales/returns?refund_mode=upi');

        $res->assertStatus(200)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_19_return_detail_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/return", [
            'items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 1]],
        ])->assertStatus(201);

        $returnId = $res->json('data.id');

        $this->getJson("/api/v1/pos/sales/returns/{$returnId}")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $returnId,
                    'original_invoice_id' => $this->completedInvoice->id,
                ],
            ]);
    }

    public function test_20_refund_information_is_correctly_recorded(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/return", [
            'refund_mode' => 'store_credit',
            'reason' => 'Defective zipper',
            'items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 2]],
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'data' => [
                    'total_refund_amount' => 2000.00,
                    'refund_mode' => 'store_credit',
                    'reason' => 'Defective zipper',
                ],
            ]);
    }

    public function test_21_repeated_duplicate_return_attempt_is_safely_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        // Return all 5 units of item1
        $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/return", [
            'items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 5]],
        ])->assertStatus(201);

        // Repeated attempt to return another unit of item1 must fail
        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/return", [
            'items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_22_standardized_api_response_maintained(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson('/api/v1/pos/sales/returns');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'items',
                    'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ]);
    }

    public function test_23_delete_sales_return_reverses_stock_and_invoice_status(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Process a return of 5 items
        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/return", [
            'refund_mode' => 'cash',
            'reason' => 'Defective zipper',
            'items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 5, 'restock_condition' => 'resellable']],
        ]);
        $res->assertStatus(201);
        $returnId = $res->json('data.id');

        // Check stock after return (should have increased by 5)
        $stockRecord = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)
            ->where('store_id', $this->store1->id)
            ->first();
        $stockAfterReturn = $stockRecord->stock_quantity;

        // Delete the sales return
        $delRes = $this->deleteJson("/api/v1/pos/sales/returns/{$returnId}");
        $delRes->assertStatus(200)->assertJson(['success' => true]);

        // Verify return record is deleted
        $this->assertDatabaseMissing('returns', ['id' => $returnId]);

        // Verify stock is reduced back by 5
        $stockRecord->refresh();
        $this->assertEquals($stockAfterReturn - 5, $stockRecord->stock_quantity);

        // Verify invoice status is restored to completed
        $this->completedInvoice->refresh();
        $this->assertEquals('completed', is_object($this->completedInvoice->status) ? $this->completedInvoice->status->value : $this->completedInvoice->status);
    }
}
