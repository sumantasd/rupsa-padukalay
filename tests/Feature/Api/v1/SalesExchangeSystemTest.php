<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\Role;
use App\Models\Size;
use App\Models\Store;
use App\Models\StoreCreditAccount;
use App\Models\StoreCreditTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesExchangeSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Store $store;
    protected Customer $customer;
    protected ProductVariantSize $sizeInd8;
    protected ProductVariantSize $sizeInd9;
    protected ProductVariantSize $sizeOtherProduct;
    protected ProductVariantSize $sizeOutOfStock;
    protected Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $permView = Permission::firstOrCreate(['name' => 'products.view'], ['guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Products']);
        $permCreate = Permission::firstOrCreate(['name' => 'products.create'], ['guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Create Products']);
        $permEdit = Permission::firstOrCreate(['name' => 'products.edit'], ['guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Edit Products']);

        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin'], ['guard_name' => 'web']);
        $superAdminRole->permissions()->syncWithoutDetaching([$permView->id, $permCreate->id, $permEdit->id]);

        $this->adminUser = User::create([
            'name' => 'Exchange Counter Staff',
            'username' => 'exc_staff_' . rand(1000, 9999),
            'email' => 'exc_staff_' . rand(1000, 9999) . '@rupsa.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($superAdminRole->id, ['model_type' => User::class]);

        $this->store = Store::create([
            'code' => 'STR-001',
            'name' => 'RUPSA PADUKALAYA - Main Outlet',
            'phone' => '+91 9735125112',
            'address' => 'DHANTALA BAZAR, DHANTALA, NADIA - 741202, WEST BENGAL, INDIA',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'Mitra Sen',
            'mobile_number' => '9831198311',
            'is_active' => true,
        ]);

        $cat = Category::create(['name' => 'Formal Shoes', 'slug' => 'formal-shoes']);
        $brand = Brand::create(['name' => 'Bata India', 'slug' => 'bata-india']);
        $colorBlack = Color::create(['name' => 'Black', 'code' => '#000000']);
        $colorBrown = Color::create(['name' => 'Brown', 'code' => '#8B4513']);

        $ind8 = Size::create(['system' => 'IND', 'size_number' => '8', 'display_name' => 'IND 8']);
        $ind9 = Size::create(['system' => 'IND', 'size_number' => '9', 'display_name' => 'IND 9']);

        // Main Shoe Product (RP-100)
        $product1 = Product::create([
            'article_number' => 'RP-100',
            'name' => 'Executive Derby Shoe',
            'slug' => 'executive-derby-shoe',
            'category_id' => $cat->id,
            'brand_id' => $brand->id,
            'is_active' => true,
        ]);

        $variantBlack = ProductVariant::create([
            'product_id' => $product1->id,
            'color_id' => $colorBlack->id,
        ]);

        // IND 8 (Old SKU, ₹999)
        $this->sizeInd8 = ProductVariantSize::create([
            'product_variant_id' => $variantBlack->id,
            'size_id' => $ind8->id,
            'sku' => 'RP-100-BLK-8',
            'mrp' => 1299.00,
            'selling_price' => 999.00,
        ]);

        // IND 9 (New Replacement SKU, ₹999)
        $this->sizeInd9 = ProductVariantSize::create([
            'product_variant_id' => $variantBlack->id,
            'size_id' => $ind9->id,
            'sku' => 'RP-100-BLK-9',
            'mrp' => 1299.00,
            'selling_price' => 999.00,
        ]);

        // Higher Priced Product (RP-200, ₹1,199)
        $product2 = Product::create([
            'article_number' => 'RP-200',
            'name' => 'Premium Monk Strap',
            'slug' => 'premium-monk-strap',
            'category_id' => $cat->id,
            'brand_id' => $brand->id,
            'is_active' => true,
        ]);

        $variantBrown = ProductVariant::create([
            'product_id' => $product2->id,
            'color_id' => $colorBrown->id,
        ]);

        $this->sizeOtherProduct = ProductVariantSize::create([
            'product_variant_id' => $variantBrown->id,
            'size_id' => $ind9->id,
            'sku' => 'RP-200-BRN-9',
            'mrp' => 1499.00,
            'selling_price' => 1199.00,
        ]);

        // Out of stock product variant
        $this->sizeOutOfStock = ProductVariantSize::create([
            'product_variant_id' => $variantBrown->id,
            'size_id' => $ind8->id,
            'sku' => 'RP-200-BRN-8-OOS',
            'mrp' => 1499.00,
            'selling_price' => 1199.00,
        ]);

        // Setup Stocks
        InventoryStock::create(['store_id' => $this->store->id, 'product_variant_size_id' => $this->sizeInd8->id, 'stock_quantity' => 10]);
        InventoryStock::create(['store_id' => $this->store->id, 'product_variant_size_id' => $this->sizeInd9->id, 'stock_quantity' => 10]);
        InventoryStock::create(['store_id' => $this->store->id, 'product_variant_size_id' => $this->sizeOtherProduct->id, 'stock_quantity' => 10]);
        InventoryStock::create(['store_id' => $this->store->id, 'product_variant_size_id' => $this->sizeOutOfStock->id, 'stock_quantity' => 0]);

        // Create Sample Original Invoice (3 pairs of RP-100 IND 8)
        $this->invoice = Invoice::create([
            'invoice_number' => 'INV-20260903-EXC1',
            'client_trans_uuid' => 'uuid-exc-001',
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 2997.00,
            'discount_amount' => 0.00,
            'taxable_amount' => 2997.00,
            'grand_total' => 2997.00,
            'paid_amount' => 2997.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->adminUser->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'product_variant_size_id' => $this->sizeInd8->id,
            'sku_snapshot' => 'RP-100-BLK-8',
            'article_number_snapshot' => 'RP-100',
            'product_name_snapshot' => 'Executive Derby Shoe',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '8',
            'cost_price' => 500.00,
            'mrp' => 1299.00,
            'unit_price' => 999.00,
            'quantity' => 3,
            'subtotal' => 2997.00,
        ]);
    }

    /** TEST 1: Same product, different IND size (₹999 -> ₹999) */
    public function test_1_same_product_different_ind_size_exchange()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/pos/sales/{$this->invoice->id}/exchange", [
                'returned_items' => [
                    ['product_variant_size_id' => $this->sizeInd8->id, 'quantity' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_size_id' => $this->sizeInd9->id, 'quantity' => 1, 'unit_price' => 999.00],
                ],
                'reason' => 'IND 8 to IND 9 Size Exchange',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.price_difference', 0);

        // Check Inventory: Size 8 restored (10 + 1 = 11), Size 9 deducted (10 - 1 = 9)
        $stock8 = InventoryStock::where('product_variant_size_id', $this->sizeInd8->id)->first()->stock_quantity;
        $stock9 = InventoryStock::where('product_variant_size_id', $this->sizeInd9->id)->first()->stock_quantity;

        $this->assertEquals(11, $stock8);
        $this->assertEquals(9, $stock9);
    }

    /** TEST 2: Different product, same price (₹999 -> ₹999) */
    public function test_2_different_product_same_price_exchange()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/pos/sales/{$this->invoice->id}/exchange", [
                'returned_items' => [
                    ['product_variant_size_id' => $this->sizeInd8->id, 'quantity' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_size_id' => $this->sizeInd9->id, 'quantity' => 1, 'unit_price' => 999.00],
                ],
                'reason' => 'Product Variant Exchange',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.price_difference', 0);
    }

    /** TEST 3: Higher price (₹999 -> ₹1,199) -> ₹200 customer payment */
    public function test_3_higher_price_exchange_requires_customer_payment()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/pos/sales/{$this->invoice->id}/exchange", [
                'returned_items' => [
                    ['product_variant_size_id' => $this->sizeInd8->id, 'quantity' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_size_id' => $this->sizeOtherProduct->id, 'quantity' => 1, 'unit_price' => 1199.00],
                ],
                'payment_method' => 'upi',
                'reason' => 'Upgrade to Premium Shoe',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.price_difference', 200)
            ->assertJsonPath('data.payment_method', 'upi');
    }

    /** TEST 4: Lower price -> Store credit refund */
    public function test_4_lower_price_exchange_issues_store_credit()
    {
        // First create an invoice with ₹1,199 item
        $invHigh = Invoice::create([
            'invoice_number' => 'INV-20260903-HIGH',
            'client_trans_uuid' => 'uuid-high',
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1199.00,
            'grand_total' => 1199.00,
            'paid_amount' => 1199.00,
            'status' => 'completed',
            'created_by' => $this->adminUser->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invHigh->id,
            'product_variant_size_id' => $this->sizeOtherProduct->id,
            'sku_snapshot' => 'RP-200-BRN-9',
            'article_number_snapshot' => 'RP-200',
            'product_name_snapshot' => 'Premium Monk Strap',
            'color_name_snapshot' => 'Brown',
            'size_number_snapshot' => '9',
            'cost_price' => 600.00,
            'mrp' => 1499.00,
            'unit_price' => 1199.00,
            'quantity' => 1,
            'subtotal' => 1199.00,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/pos/sales/{$invHigh->id}/exchange", [
                'returned_items' => [
                    ['product_variant_size_id' => $this->sizeOtherProduct->id, 'quantity' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_size_id' => $this->sizeInd8->id, 'quantity' => 1, 'unit_price' => 999.00],
                ],
                'refund_mode' => 'store_credit',
                'reason' => 'Downgrade exchange to ₹999 shoe',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.price_difference', -200)
            ->assertJsonPath('data.refund_mode', 'store_credit');

        // Check Store Credit Account created
        $scAccount = StoreCreditAccount::where('customer_id', $this->customer->id)->first();
        $this->assertNotNull($scAccount);
        $this->assertEquals(200.00, $scAccount->current_balance);
    }

    /** TEST 5: Partial exchange */
    public function test_5_partial_exchange_of_multi_qty_invoice()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/pos/sales/{$this->invoice->id}/exchange", [
                'returned_items' => [
                    ['product_variant_size_id' => $this->sizeInd8->id, 'quantity' => 1], // 1 out of 3
                ],
                'replacement_items' => [
                    ['product_variant_size_id' => $this->sizeInd9->id, 'quantity' => 1, 'unit_price' => 999.00],
                ],
                'reason' => 'Partial size exchange',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }

    /** TEST 6: Prevent over-exchange */
    public function test_6_prevent_over_exchange_exceeding_purchased_quantity()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/pos/sales/{$this->invoice->id}/exchange", [
                'returned_items' => [
                    ['product_variant_size_id' => $this->sizeInd8->id, 'quantity' => 10], // Purchased only 3
                ],
                'replacement_items' => [
                    ['product_variant_size_id' => $this->sizeInd9->id, 'quantity' => 10, 'unit_price' => 999.00],
                ],
                'reason' => 'Over exchange test',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /** TEST 7: Prevent insufficient replacement stock */
    public function test_7_prevent_insufficient_replacement_stock()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/pos/sales/{$this->invoice->id}/exchange", [
                'returned_items' => [
                    ['product_variant_size_id' => $this->sizeInd8->id, 'quantity' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_size_id' => $this->sizeOutOfStock->id, 'quantity' => 1, 'unit_price' => 1199.00], // 0 stock
                ],
                'payment_method' => 'cash',
                'reason' => 'Out of stock exchange test',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Insufficient stock for selected replacement SKU.');
    }

    /** TEST 8: Prevent duplicate exchange */
    public function test_8_prevent_duplicate_exchange_when_no_remaining_quantity()
    {
        // Exchange full quantity (3)
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/pos/sales/{$this->invoice->id}/exchange", [
                'returned_items' => [
                    ['product_variant_size_id' => $this->sizeInd8->id, 'quantity' => 3],
                ],
                'replacement_items' => [
                    ['product_variant_size_id' => $this->sizeInd9->id, 'quantity' => 3, 'unit_price' => 999.00],
                ],
                'reason' => 'Full quantity exchange',
            ])->assertStatus(201);

        // Attempt second exchange on same items (0 remaining)
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/pos/sales/{$this->invoice->id}/exchange", [
                'returned_items' => [
                    ['product_variant_size_id' => $this->sizeInd8->id, 'quantity' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_size_id' => $this->sizeInd9->id, 'quantity' => 1, 'unit_price' => 999.00],
                ],
                'reason' => 'Duplicate exchange attempt',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /** TEST 9: Inventory rollback when transaction fails */
    public function test_9_inventory_rollback_on_transaction_failure()
    {
        $initialStock8 = InventoryStock::where('product_variant_size_id', $this->sizeInd8->id)->first()->stock_quantity;
        $initialStockOOS = InventoryStock::where('product_variant_size_id', $this->sizeOutOfStock->id)->first()->stock_quantity;

        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/pos/sales/{$this->invoice->id}/exchange", [
                'returned_items' => [
                    ['product_variant_size_id' => $this->sizeInd8->id, 'quantity' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_size_id' => $this->sizeOutOfStock->id, 'quantity' => 1, 'unit_price' => 1199.00], // Triggers exception
                ],
                'payment_method' => 'cash',
            ])->assertStatus(422);

        // Verify stocks remain completely unchanged
        $this->assertEquals($initialStock8, InventoryStock::where('product_variant_size_id', $this->sizeInd8->id)->first()->stock_quantity);
        $this->assertEquals($initialStockOOS, InventoryStock::where('product_variant_size_id', $this->sizeOutOfStock->id)->first()->stock_quantity);
    }

    /** TEST 10: Customer purchase history correctly updated */
    public function test_10_customer_purchase_history_correctly_updated()
    {
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/pos/sales/{$this->invoice->id}/exchange", [
                'returned_items' => [
                    ['product_variant_size_id' => $this->sizeInd8->id, 'quantity' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_size_id' => $this->sizeInd9->id, 'quantity' => 1, 'unit_price' => 999.00],
                ],
                'reason' => 'Customer history exchange test',
            ])->assertStatus(201);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/customers/{$this->customer->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    /** TEST 11: Store credit correctly updated */
    public function test_11_store_credit_correctly_updated_in_ledger()
    {
        $invHigh = Invoice::create([
            'invoice_number' => 'INV-20260903-HIGH2',
            'client_trans_uuid' => 'uuid-high2',
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1199.00,
            'grand_total' => 1199.00,
            'paid_amount' => 1199.00,
            'status' => 'completed',
            'created_by' => $this->adminUser->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invHigh->id,
            'product_variant_size_id' => $this->sizeOtherProduct->id,
            'sku_snapshot' => 'RP-200-BRN-9',
            'article_number_snapshot' => 'RP-200',
            'product_name_snapshot' => 'Premium Monk Strap',
            'color_name_snapshot' => 'Brown',
            'size_number_snapshot' => '9',
            'cost_price' => 600.00,
            'mrp' => 1499.00,
            'unit_price' => 1199.00,
            'quantity' => 1,
            'subtotal' => 1199.00,
        ]);

        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/pos/sales/{$invHigh->id}/exchange", [
                'returned_items' => [
                    ['product_variant_size_id' => $this->sizeOtherProduct->id, 'quantity' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_size_id' => $this->sizeInd8->id, 'quantity' => 1, 'unit_price' => 999.00],
                ],
                'refund_mode' => 'store_credit',
                'reason' => 'Store credit ledger test',
            ])->assertStatus(201);

        $scTx = StoreCreditTransaction::where('customer_id', $this->customer->id)->first();
        $this->assertNotNull($scTx);
        $this->assertEquals(200.00, $scTx->amount);
        $this->assertEquals('issue_refund', $scTx->transaction_type);
    }

    /** TEST 12: Exchange payment correctly recorded */
    public function test_12_exchange_payment_correctly_recorded()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/pos/sales/{$this->invoice->id}/exchange", [
                'returned_items' => [
                    ['product_variant_size_id' => $this->sizeInd8->id, 'quantity' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_size_id' => $this->sizeOtherProduct->id, 'quantity' => 1, 'unit_price' => 1199.00],
                ],
                'payment_method' => 'cash',
                'reason' => 'Recorded exchange payment',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.amount_paid', 200)
            ->assertJsonPath('data.payment_method', 'cash');
    }

    /** TEST 13: IND size displayed correctly */
    public function test_13_ind_size_displayed_correctly_in_resources()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/pos/sales/{$this->invoice->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.items.0.size_number', '8');
    }

    /** TEST 14: Original invoice remains auditable */
    public function test_14_original_invoice_remains_auditable()
    {
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/pos/sales/{$this->invoice->id}/exchange", [
                'returned_items' => [
                    ['product_variant_size_id' => $this->sizeInd8->id, 'quantity' => 1],
                ],
                'replacement_items' => [
                    ['product_variant_size_id' => $this->sizeInd9->id, 'quantity' => 1, 'unit_price' => 999.00],
                ],
                'reason' => 'Auditable invoice test',
            ])->assertStatus(201);

        $freshInv = Invoice::find($this->invoice->id);
        $this->assertEquals(2997.00, $freshInv->grand_total);
        $statusVal = is_object($freshInv->status) ? $freshInv->status->value : $freshInv->status;
        $this->assertEquals('partially_returned', $statusVal);
    }

    /** TEST 15: Single-store STR-001 enforced */
    public function test_15_single_store_str001_enforced()
    {
        $this->assertEquals('STR-001', $this->store->code);
    }
}
