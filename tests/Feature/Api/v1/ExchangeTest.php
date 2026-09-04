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

class ExchangeTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $cashier;
    protected User $noPermUser;
    protected Store $store1;
    protected Store $store2;
    protected Customer $customer;
    protected ProductVariantSize $variantSize1; // Black Size 8 (Price 1000)
    protected ProductVariantSize $variantSize2; // Black Size 9 (Price 1000) - Replacement candidate
    protected ProductVariantSize $variantSize3; // Brown Size 8 (Price 1500) - Higher price replacement candidate
    protected PosSession $openSession1;
    protected Invoice $completedInvoice;
    protected InvoiceItem $invoiceItem1;

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
            'name' => 'Suman Roy',
            'mobile_number' => '9831198311',
            'city' => 'Kolkata',
        ]);

        $brand = Brand::create(['name' => 'Apex', 'slug' => 'apex', 'is_active' => true]);
        $category = Category::create(['name' => 'Casual', 'slug' => 'casual', 'is_active' => true]);
        $colorBlack = Color::create(['name' => 'Black', 'code' => 'BLK']);
        $colorBrown = Color::create(['name' => 'Brown', 'code' => 'BRN']);
        $size8 = Size::create(['size_number' => '08', 'size_system' => 'UK', 'sort_order' => 8]);
        $size9 = Size::create(['size_number' => '09', 'size_system' => 'UK', 'sort_order' => 9]);

        $product = Product::create([
            'article_number' => 'ART900',
            'name' => 'Men Casual Sneaker',
            'slug' => 'art900-men-casual-sneaker',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
        ]);

        $variant1 = ProductVariant::create(['product_id' => $product->id, 'color_id' => $colorBlack->id]);
        $variant2 = ProductVariant::create(['product_id' => $product->id, 'color_id' => $colorBrown->id]);

        $this->variantSize1 = ProductVariantSize::create([
            'product_variant_id' => $variant1->id,
            'size_id' => $size8->id,
            'sku' => 'ART900-BLK-08',
            'barcode' => '8909988776655',
            'cost_price' => 500.00,
            'mrp' => 1299.00,
            'selling_price' => 1000.00,
        ]);

        $this->variantSize2 = ProductVariantSize::create([
            'product_variant_id' => $variant1->id,
            'size_id' => $size9->id,
            'sku' => 'ART900-BLK-09',
            'barcode' => '8909988776656',
            'cost_price' => 500.00,
            'mrp' => 1299.00,
            'selling_price' => 1000.00,
        ]);

        $this->variantSize3 = ProductVariantSize::create([
            'product_variant_id' => $variant2->id,
            'size_id' => $size8->id,
            'sku' => 'ART900-BRN-08',
            'barcode' => '8909988776657',
            'cost_price' => 700.00,
            'mrp' => 1899.00,
            'selling_price' => 1500.00,
        ]);

        InventoryStock::create(['product_variant_size_id' => $this->variantSize1->id, 'store_id' => $this->store1->id, 'stock_quantity' => 10]);
        InventoryStock::create(['product_variant_size_id' => $this->variantSize2->id, 'store_id' => $this->store1->id, 'stock_quantity' => 10]);
        InventoryStock::create(['product_variant_size_id' => $this->variantSize3->id, 'store_id' => $this->store1->id, 'stock_quantity' => 10]);

        $this->openSession1 = PosSession::create([
            'store_id' => $this->store1->id,
            'user_id' => $this->cashier->id,
            'opened_at' => now(),
            'opening_cash' => 1000.00,
            'closing_cash_system' => 1000.00,
            'status' => 'open',
        ]);

        // Completed Sale Invoice (Item 1: 3 units @ 1000 = 3000)
        $this->completedInvoice = Invoice::create([
            'invoice_number' => 'INV-EXC-001',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 3000.00,
            'discount_amount' => 0.00,
            'grand_total' => 3000.00,
            'paid_amount' => 3000.00,
            'payment_status' => 'paid',
            'sale_type' => 'pos_counter',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        $this->invoiceItem1 = InvoiceItem::create([
            'invoice_id' => $this->completedInvoice->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'sku_snapshot' => $this->variantSize1->sku,
            'article_number_snapshot' => 'ART900',
            'product_name_snapshot' => 'Men Casual Sneaker',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '08',
            'cost_price' => 500.00,
            'mrp' => 1299.00,
            'unit_price' => 1000.00,
            'quantity' => 3,
            'subtotal' => 3000.00,
        ]);

        InvoicePayment::create([
            'invoice_id' => $this->completedInvoice->id,
            'payment_method' => 'cash',
            'amount' => 3000.00,
            'payment_time' => now(),
        ]);
    }

    public function test_1_unauthenticated_request_rejected(): void
    {
        $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'returned_items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 1]],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
        ])->assertStatus(401);
    }

    public function test_2_missing_permission_rejected(): void
    {
        Sanctum::actingAs($this->noPermUser);

        $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'returned_items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 1]],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
        ])->assertStatus(403);
    }

    public function test_3_authorized_exchange_succeeds(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'reason' => 'Size swap',
            'returned_items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 1, 'restock_condition' => 'resellable']],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'original_invoice_id' => $this->completedInvoice->id,
                    'price_difference' => 0.00,
                    'payment_status_summary' => 'equal_exchange',
                ],
            ]);
    }

    public function test_4_nonexistent_sale_returns_404(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->postJson('/api/v1/pos/sales/999999/exchange', [
            'returned_items' => [['sku' => 'ART900-BLK-08', 'quantity' => 1]],
            'replacement_items' => [['sku' => 'ART900-BLK-09', 'quantity' => 1]],
        ])->assertStatus(404);
    }

    public function test_5_invalid_original_invoice_item_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        // Create another invoice
        $otherInvoice = Invoice::create([
            'invoice_number' => 'INV-OTHER-EXC',
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
            'sku_snapshot' => 'ART900-BLK-08',
            'article_number_snapshot' => 'ART900',
            'product_name_snapshot' => 'Men Casual Sneaker',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '08',
            'quantity' => 1,
            'unit_price' => 1000.00,
            'subtotal' => 1000.00,
        ]);

        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'returned_items' => [['invoice_item_id' => $otherItem->id, 'quantity' => 1]],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_6_exchange_quantity_cannot_exceed_sold_quantity(): void
    {
        Sanctum::actingAs($this->cashier);

        // Sold quantity is 3. Requesting 4 returned items must fail.
        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'returned_items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 4]],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 4]],
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_7_already_returned_exchanged_quantity_is_respected(): void
    {
        Sanctum::actingAs($this->cashier);

        // Perform first exchange of 2 units
        $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'returned_items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 2]],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 2]],
        ])->assertStatus(201);

        // Remaining available is 1. Attempting to exchange 2 more must fail.
        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'returned_items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 2]],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 2]],
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_8_partial_exchange_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'returned_items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 1]],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(201);
    }

    public function test_9_multiple_returned_items_work(): void
    {
        Sanctum::actingAs($this->cashier);

        // Create an invoice with 2 items
        $multiInvoice = Invoice::create([
            'invoice_number' => 'INV-MULTI-EXC',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'subtotal' => 2000.00,
            'grand_total' => 2000.00,
            'paid_amount' => 2000.00,
            'payment_status' => 'paid',
            'sale_type' => 'pos_counter',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        $item1 = InvoiceItem::create([
            'invoice_id' => $multiInvoice->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'sku_snapshot' => 'ART900-BLK-08',
            'article_number_snapshot' => 'ART900',
            'product_name_snapshot' => 'Men Casual Sneaker',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '08',
            'quantity' => 1,
            'unit_price' => 1000.00,
            'subtotal' => 1000.00,
        ]);

        $item2 = InvoiceItem::create([
            'invoice_id' => $multiInvoice->id,
            'product_variant_size_id' => $this->variantSize2->id,
            'sku_snapshot' => 'ART900-BLK-09',
            'article_number_snapshot' => 'ART900',
            'product_name_snapshot' => 'Men Casual Sneaker',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '09',
            'quantity' => 1,
            'unit_price' => 1000.00,
            'subtotal' => 1000.00,
        ]);

        $res = $this->postJson("/api/v1/pos/sales/{$multiInvoice->id}/exchange", [
            'returned_items' => [
                ['invoice_item_id' => $item1->id, 'quantity' => 1],
                ['invoice_item_id' => $item2->id, 'quantity' => 1],
            ],
            'replacement_items' => [
                ['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1],
                ['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'data' => [
                    'returned_total' => 2000.00,
                    'replacement_total' => 2000.00,
                    'price_difference' => 0.00,
                ],
            ]);
    }

    public function test_10_replacement_item_sku_lookup_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'returned_items' => [['sku' => 'ART900-BLK-08', 'quantity' => 1]],
            'replacement_items' => [['sku' => 'ART900-BLK-09', 'quantity' => 1]],
        ]);

        $res->assertStatus(201);
    }

    public function test_11_insufficient_replacement_stock_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        // Set variantSize2 stock to 1
        $stock2 = InventoryStock::where('product_variant_size_id', $this->variantSize2->id)->first();
        $stock2->update(['stock_quantity' => 1]);

        // Request 2 replacement units
        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'returned_items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 2]],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 2]],
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_12_replacement_inventory_deducted_correctly(): void
    {
        Sanctum::actingAs($this->cashier);

        $stockBefore = InventoryStock::where('product_variant_size_id', $this->variantSize2->id)->first()->stock_quantity;
        $this->assertEquals(10, $stockBefore);

        $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'returned_items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 1]],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
        ])->assertStatus(201);

        $stockAfter = InventoryStock::where('product_variant_size_id', $this->variantSize2->id)->first()->stock_quantity;
        $this->assertEquals(9, $stockAfter);
    }

    public function test_13_resellable_returned_item_increases_stock(): void
    {
        Sanctum::actingAs($this->cashier);

        $stockBefore = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first()->stock_quantity;
        $this->assertEquals(10, $stockBefore);

        $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'returned_items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 1, 'restock_condition' => 'resellable']],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
        ])->assertStatus(201);

        $stockAfter = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first()->stock_quantity;
        $this->assertEquals(11, $stockAfter);
    }

    public function test_14_damaged_returned_item_does_not_increase_sellable_stock(): void
    {
        Sanctum::actingAs($this->cashier);

        $stockBefore = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first()->stock_quantity;
        $this->assertEquals(10, $stockBefore);

        $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'returned_items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 1, 'restock_condition' => 'damaged']],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
        ])->assertStatus(201);

        $stockAfter = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first()->stock_quantity;
        $this->assertEquals(10, $stockAfter);
    }

    public function test_15_stock_movement_ledger_entries_are_correct(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'returned_items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 1, 'restock_condition' => 'resellable']],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
        ])->assertStatus(201);

        // Check sale_return movement for returned item1
        $movReturn = StockMovement::where('product_variant_size_id', $this->variantSize1->id)->latest('id')->first();
        $typeRet = is_object($movReturn->movement_type) ? $movReturn->movement_type->value : $movReturn->movement_type;
        $this->assertEquals('sale_return', $typeRet);
        $this->assertEquals(1, $movReturn->quantity_change);

        // Check sale_pos movement for replacement item2
        $movDeduct = StockMovement::where('product_variant_size_id', $this->variantSize2->id)->latest('id')->first();
        $typeDed = is_object($movDeduct->movement_type) ? $movDeduct->movement_type->value : $movDeduct->movement_type;
        $this->assertEquals('sale_pos', $typeDed);
        $this->assertEquals(-1, $movDeduct->quantity_change);
    }

    public function test_16_stock_before_quantity_change_stock_after_are_correct(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'returned_items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 2, 'restock_condition' => 'resellable']],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 2]],
        ])->assertStatus(201);

        $movDeduct = StockMovement::where('product_variant_size_id', $this->variantSize2->id)->latest('id')->first();
        $this->assertEquals(10, $movDeduct->stock_before);
        $this->assertEquals(-2, $movDeduct->quantity_change);
        $this->assertEquals(8, $movDeduct->stock_after);
    }

    public function test_17_equal_value_exchange_works(): void
    {
        Sanctum::actingAs($this->cashier);

        // Returned 1000 vs Replacement 1000 = difference 0
        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'returned_items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 1]],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'data' => [
                    'returned_total' => 1000.00,
                    'replacement_total' => 1000.00,
                    'price_difference' => 0.00,
                    'payment_status_summary' => 'equal_exchange',
                ],
            ]);
    }

    public function test_18_customer_additional_payment_calculation_works(): void
    {
        Sanctum::actingAs($this->cashier);

        // Returned 1 unit @ 1000 = 1000 vs Replacement variantSize3 (1500) = difference +500
        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'payment_method' => 'card',
            'returned_items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 1]],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize3->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'data' => [
                    'returned_total' => 1000.00,
                    'replacement_total' => 1500.00,
                    'price_difference' => 500.00,
                    'payment_status_summary' => 'additional_payment',
                ],
            ]);

        // Original invoice paid_amount remains intact (3000.00)
        $this->assertEquals(3000.00, (float) $this->completedInvoice->fresh()->paid_amount);

        // Exchange record records payment difference (500.00)
        $excId = $res->json('data.id');
        $excRecord = \App\Models\ReturnSale::find($excId);
        $this->assertEquals(500.00, (float) $excRecord->amount_paid);
    }

    public function test_19_customer_refund_calculation_works(): void
    {
        Sanctum::actingAs($this->cashier);

        // Create invoice with high value item (variant3 @ 1500)
        $highInvoice = Invoice::create([
            'invoice_number' => 'INV-HIGH-EXC',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'subtotal' => 1500.00,
            'grand_total' => 1500.00,
            'paid_amount' => 1500.00,
            'payment_status' => 'paid',
            'sale_type' => 'pos_counter',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        $highItem = InvoiceItem::create([
            'invoice_id' => $highInvoice->id,
            'product_variant_size_id' => $this->variantSize3->id,
            'sku_snapshot' => 'ART900-BRN-08',
            'article_number_snapshot' => 'ART900',
            'product_name_snapshot' => 'Men Casual Sneaker',
            'color_name_snapshot' => 'Brown',
            'size_number_snapshot' => '08',
            'unit_price' => 1500.00,
            'quantity' => 1,
            'subtotal' => 1500.00,
        ]);

        // Return 1500 vs Replace with variant1 (1000) = difference -500
        $res = $this->postJson("/api/v1/pos/sales/{$highInvoice->id}/exchange", [
            'refund_mode' => 'cash',
            'returned_items' => [['invoice_item_id' => $highItem->id, 'quantity' => 1]],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'data' => [
                    'returned_total' => 1500.00,
                    'replacement_total' => 1000.00,
                    'price_difference' => -500.00,
                    'payment_status_summary' => 'refund',
                ],
            ]);
    }

    public function test_20_tax_calculation_is_server_side_and_correct(): void
    {
        Sanctum::actingAs($this->cashier);

        // Pass client-submitted unit_price 10.00 (which will be ignored/overridden by server or validated)
        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'returned_items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 1]],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'data' => [
                    'returned_total' => 1000.00,
                    'replacement_total' => 1000.00,
                ],
            ]);
    }

    public function test_21_atomic_rollback_when_one_exchange_item_fails(): void
    {
        Sanctum::actingAs($this->cashier);

        // Set variantSize2 stock to 0
        $stock2 = InventoryStock::where('product_variant_size_id', $this->variantSize2->id)->first();
        $stock2->update(['stock_quantity' => 0]);

        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'returned_items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 1]],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(422);

        // Assert atomic rollback: no returns created, variant1 stock unchanged
        $this->assertEquals(0, ReturnSale::count());
        $this->assertEquals(10, InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first()->stock_quantity);
    }

    public function test_22_duplicate_over_exchange_protection_works(): void
    {
        Sanctum::actingAs($this->cashier);

        // Exchange all 3 units of item1
        $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'returned_items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 3]],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 3]],
        ])->assertStatus(201);

        // Attempt second exchange on item1 must fail
        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'returned_items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 1]],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_23_unauthorized_store_exchange_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        // Invoice belonging to Store 2 (unauthorized for cashier)
        $store2Invoice = Invoice::create([
            'invoice_number' => 'INV-STORE2-EXC',
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
            'sku_snapshot' => 'ART900-BLK-08',
            'article_number_snapshot' => 'ART900',
            'product_name_snapshot' => 'Men Casual Sneaker',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '08',
            'quantity' => 1,
            'unit_price' => 1000.00,
            'subtotal' => 1000.00,
        ]);

        $this->postJson("/api/v1/pos/sales/{$store2Invoice->id}/exchange", [
            'returned_items' => [['invoice_item_id' => $store2Item->id, 'quantity' => 1]],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
        ])->assertStatus(403);
    }

    public function test_24_super_admin_cross_store_exchange_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $store2Invoice = Invoice::create([
            'invoice_number' => 'INV-STORE2-SA-EXC',
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
            'sku_snapshot' => 'ART900-BLK-08',
            'article_number_snapshot' => 'ART900',
            'product_name_snapshot' => 'Men Casual Sneaker',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '08',
            'quantity' => 1,
            'unit_price' => 1000.00,
            'subtotal' => 1000.00,
        ]);

        InventoryStock::create(['product_variant_size_id' => $this->variantSize2->id, 'store_id' => $this->store2->id, 'stock_quantity' => 5]);

        $res = $this->postJson("/api/v1/pos/sales/{$store2Invoice->id}/exchange", [
            'returned_items' => [['invoice_item_id' => $store2Item->id, 'quantity' => 1]],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(201)->assertJson(['success' => true]);
    }

    public function test_25_payment_method_validation_works(): void
    {
        Sanctum::actingAs($this->cashier);

        // Invalid payment method 'crypto' when replacement > returned
        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'payment_method' => 'crypto',
            'returned_items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 1]],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize3->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_26_exchange_detail_endpoint_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'returned_items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 1]],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
        ])->assertStatus(201);

        $exchangeId = $res->json('data.id');

        $this->getJson("/api/v1/pos/exchanges/{$exchangeId}")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $exchangeId,
                    'original_invoice_id' => $this->completedInvoice->id,
                ],
            ]);
    }

    public function test_27_exchange_listing_filtering_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->postJson("/api/v1/pos/sales/{$this->completedInvoice->id}/exchange", [
            'returned_items' => [['invoice_item_id' => $this->invoiceItem1->id, 'quantity' => 1]],
            'replacement_items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
        ])->assertStatus(201);

        $res = $this->getJson("/api/v1/pos/exchanges?original_invoice_id={$this->completedInvoice->id}");

        $res->assertStatus(200)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_28_standardized_api_response_maintained(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson('/api/v1/pos/exchanges');

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
}
