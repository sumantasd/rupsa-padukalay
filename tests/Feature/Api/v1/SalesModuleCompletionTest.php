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
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\Role;
use App\Models\Size;
use App\Models\Store;
use App\Models\StoreCreditAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesModuleCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Store $store;
    protected Customer $customer;
    protected ProductVariantSize $variantSize1;
    protected ProductVariantSize $variantSize2;
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
            'name' => 'Sales Admin User',
            'username' => 'sales_admin_' . rand(1000, 9999),
            'email' => 'sales_admin_' . rand(1000, 9999) . '@rupsa.com',
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
            'name' => 'Sourav Ganguly',
            'mobile_number' => '9830098300',
            'is_active' => true,
        ]);

        $cat = Category::create(['name' => 'Formal Footwear', 'slug' => 'formal-footwear']);
        $brand = Brand::create(['name' => 'Apex Footwear', 'slug' => 'apex-footwear']);
        $colorBlack = Color::create(['name' => 'Black', 'code' => '#000000']);
        $sizeInd8 = Size::create(['system' => 'IND', 'size_number' => '8', 'display_name' => 'IND 8']);
        $sizeInd9 = Size::create(['system' => 'IND', 'size_number' => '9', 'display_name' => 'IND 9']);

        $product = Product::create([
            'article_number' => 'RP-SALE-01',
            'name' => 'Classic Executive Oxford',
            'slug' => 'classic-executive-oxford',
            'category_id' => $cat->id,
            'brand_id' => $brand->id,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color_id' => $colorBlack->id,
        ]);

        $this->variantSize1 = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $sizeInd8->id,
            'sku' => 'RP-SALE-01-BLK-8',
            'mrp' => 2000.00,
            'selling_price' => 1800.00,
        ]);

        $this->variantSize2 = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $sizeInd9->id,
            'sku' => 'RP-SALE-01-BLK-9',
            'mrp' => 2500.00,
            'selling_price' => 2200.00,
        ]);

        InventoryStock::create([
            'store_id' => $this->store->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'stock_quantity' => 10,
        ]);

        InventoryStock::create([
            'store_id' => $this->store->id,
            'product_variant_size_id' => $this->variantSize2->id,
            'stock_quantity' => 10,
        ]);

        // Sample POS Sale Invoice
        $this->invoice = Invoice::create([
            'invoice_number' => 'INV-20260903-0001',
            'client_trans_uuid' => 'uuid-sale-001',
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 3600.00,
            'discount_amount' => 0.00,
            'taxable_amount' => 3600.00,
            'grand_total' => 3600.00,
            'paid_amount' => 3600.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->adminUser->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'sku_snapshot' => 'RP-SALE-01-BLK-8',
            'article_number_snapshot' => 'RP-SALE-01',
            'product_name_snapshot' => 'Classic Executive Oxford',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '8',
            'cost_price' => 1000.00,
            'mrp' => 2000.00,
            'unit_price' => 1800.00,
            'quantity' => 2,
            'subtotal' => 3600.00,
        ]);

        InvoicePayment::create([
            'invoice_id' => $this->invoice->id,
            'payment_method' => 'upi',
            'amount' => 3600.00,
            'transaction_reference' => 'UTR9988776655',
            'payment_time' => now(),
        ]);
    }

    public function test_sales_invoices_index_returns_paginated_invoices_with_search_and_filters()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/pos/sales?search=Sourav');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $items = $response->json('data.items');
        $this->assertCount(1, $items);
        $this->assertEquals('INV-20260903-0001', $items[0]['invoice_number']);
        $this->assertEquals('Sourav Ganguly', $items[0]['customer']['name']);
    }

    public function test_sales_return_increases_stock_and_issues_store_credit()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/pos/sales/{$this->invoice->id}/return", [
                'items' => [
                    [
                        'product_variant_size_id' => $this->variantSize1->id,
                        'quantity' => 1,
                        'restock_condition' => 'resellable',
                    ],
                ],
                'refund_mode' => 'store_credit',
                'reason' => 'Wrong Size',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.refund_mode', 'store_credit');

        $this->assertEquals(1800.00, $response->json('data.total_refund_amount'));

        // Check Inventory Stock restored (10 original - 2 sold + 1 returned = 9)
        $stock = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)
            ->where('store_id', $this->store->id)
            ->first();
        $this->assertEquals(11, $stock->stock_quantity); // 10 initial stock + 1 returned = 11

        // Check Customer Store Credit Account
        $scAccount = StoreCreditAccount::where('customer_id', $this->customer->id)->first();
        $this->assertNotNull($scAccount);
        $this->assertEquals(1800.00, $scAccount->current_balance);
    }

    public function test_exchange_restocks_old_sku_and_deducts_new_sku_with_price_difference()
    {
        // Exchange 1 pair of Size 8 (₹1800) for 1 pair of Size 9 (₹2200) -> Customer pays ₹400
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/pos/sales/{$this->invoice->id}/exchange", [
                'returned_items' => [
                    [
                        'product_variant_size_id' => $this->variantSize1->id,
                        'quantity' => 1,
                        'restock_condition' => 'resellable',
                    ],
                ],
                'replacement_items' => [
                    [
                        'product_variant_size_id' => $this->variantSize2->id,
                        'quantity' => 1,
                        'unit_price' => 2200.00,
                    ],
                ],
                'payment_method' => 'cash',
                'reason' => 'Footwear Size Exchange',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        // Check stock: Size 8 increased by 1 (10 -> 11), Size 9 decreased by 1 (10 -> 9)
        $stock1 = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first();
        $stock2 = InventoryStock::where('product_variant_size_id', $this->variantSize2->id)->first();

        $this->assertEquals(11, $stock1->stock_quantity);
        $this->assertEquals(9, $stock2->stock_quantity);
    }

    public function test_cannot_return_more_quantity_than_originally_purchased()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/pos/sales/{$this->invoice->id}/return", [
                'items' => [
                    [
                        'product_variant_size_id' => $this->variantSize1->id,
                        'quantity' => 5, // Exceeds purchased 2 pcs
                        'restock_condition' => 'resellable',
                    ],
                ],
                'refund_mode' => 'cash',
                'reason' => 'Over Return Test',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
