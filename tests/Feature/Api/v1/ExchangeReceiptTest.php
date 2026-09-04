<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\Role;
use App\Models\Size;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExchangeReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Store $store;
    protected Category $category;
    protected Brand $brand;
    protected Color $color;
    protected Size $size8;
    protected Size $size9;
    protected ProductVariantSize $variantSizeOld;
    protected ProductVariantSize $variantSizeNew;
    protected Customer $customer;
    protected Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $permView = Permission::firstOrCreate(['name' => 'pos.view'], ['guard_name' => 'web', 'module_group' => 'POS', 'display_name' => 'View POS']);
        $permEdit = Permission::firstOrCreate(['name' => 'pos.edit'], ['guard_name' => 'web', 'module_group' => 'POS', 'display_name' => 'Edit POS']);

        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin'], ['guard_name' => 'web']);
        $superAdminRole->permissions()->syncWithoutDetaching([$permView->id, $permEdit->id]);

        $this->adminUser = User::create([
            'name' => 'Exchange Master Admin',
            'username' => 'exc_admin_' . rand(1000, 9999),
            'email' => 'exc_admin_' . rand(1000, 9999) . '@rupsa.com',
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

        $this->category = Category::create(['name' => 'Formal Shoes', 'slug' => 'formal-shoes']);
        $this->brand = Brand::create(['name' => 'Bata India', 'slug' => 'bata-india']);
        $this->color = Color::create(['name' => 'Black', 'code' => '#000000']);
        $this->size8 = Size::create(['system' => 'IND', 'size_number' => '8', 'display_name' => 'IND 8']);
        $this->size9 = Size::create(['system' => 'IND', 'size_number' => '9', 'display_name' => 'IND 9']);

        $product = Product::create([
            'article_number' => 'RP-EXC-01',
            'name' => 'Executive Leather Loafer',
            'slug' => 'executive-leather-loafer',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color_id' => $this->color->id,
        ]);

        $this->variantSizeOld = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $this->size8->id,
            'sku' => 'RP-EXC-01-BLK-8',
            'mrp' => 1899.00,
            'selling_price' => 1699.00,
        ]);

        $this->variantSizeNew = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $this->size9->id,
            'sku' => 'RP-EXC-01-BLK-9',
            'mrp' => 1899.00,
            'selling_price' => 1699.00,
        ]);

        \App\Models\InventoryStock::create([
            'store_id' => $this->store->id,
            'product_variant_size_id' => $this->variantSizeNew->id,
            'stock_quantity' => 10,
        ]);

        $this->customer = Customer::create([
            'name' => 'Sourav Ganguly',
            'mobile_number' => '9830098300',
            'address' => 'Dhantala, Nadia',
        ]);

        $this->invoice = Invoice::create([
            'invoice_number' => 'INV-EXC-TEST-1',
            'client_trans_uuid' => 'uuid-exc-01',
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'grand_total' => 1699.00,
            'paid_amount' => 1699.00,
            'status' => 'completed',
            'created_by' => $this->adminUser->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'product_variant_size_id' => $this->variantSizeOld->id,
            'sku_snapshot' => 'RP-EXC-01-BLK-8',
            'article_number_snapshot' => 'RP-EXC-01',
            'product_name_snapshot' => 'Executive Leather Loafer',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => 'IND 8',
            'quantity' => 1,
            'unit_price' => 1699.00,
            'subtotal' => 1699.00,
        ]);
    }

    public function test_even_exchange_receipt_payload()
    {
        $payload = [
            'returned_items' => [
                [
                    'product_variant_size_id' => $this->variantSizeOld->id,
                    'quantity' => 1,
                    'restock_condition' => 'resellable',
                ],
            ],
            'replacement_items' => [
                [
                    'product_variant_size_id' => $this->variantSizeNew->id,
                    'quantity' => 1,
                    'unit_price' => 1699.00,
                ],
            ],
            'reason' => 'IND 8 to IND 9 Size Exchange',
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/pos/sales/{$this->invoice->id}/exchange", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.price_difference', 0)
            ->assertJsonPath('data.returned_total', 1699)
            ->assertJsonPath('data.replacement_total', 1699)
            ->assertJsonPath('data.original_invoice_number', 'INV-EXC-TEST-1')
            ->assertJsonPath('data.customer_name', 'Sourav Ganguly')
            ->assertJsonPath('data.customer_mobile', '9830098300');

        $data = $response->json('data');
        $this->assertNotEmpty($data['returned_items']);
        $this->assertNotEmpty($data['replacement_items']);
        $this->assertEquals('RP-EXC-01', $data['returned_items'][0]['article_number']);
        $this->assertEquals('8', $data['returned_items'][0]['size_number']);
        $this->assertEquals('RP-EXC-01', $data['replacement_items'][0]['article_number']);
        $this->assertEquals('9', $data['replacement_items'][0]['size_number']);
    }

    public function test_exchange_with_extra_payment()
    {
        $expensiveProduct = Product::create([
            'article_number' => 'RP-EXP-01',
            'name' => 'Premium Leather Boot',
            'slug' => 'premium-leather-boot',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'is_active' => true,
        ]);
        $expensiveVariant = ProductVariant::create(['product_id' => $expensiveProduct->id, 'color_id' => $this->color->id]);
        $expensiveSize = ProductVariantSize::create([
            'product_variant_id' => $expensiveVariant->id,
            'size_id' => $this->size9->id,
            'sku' => 'RP-EXP-01-BLK-9',
            'selling_price' => 2499.00,
        ]);

        \App\Models\InventoryStock::create([
            'store_id' => $this->store->id,
            'product_variant_size_id' => $expensiveSize->id,
            'stock_quantity' => 10,
        ]);

        $payload = [
            'returned_items' => [
                ['product_variant_size_id' => $this->variantSizeOld->id, 'quantity' => 1, 'restock_condition' => 'resellable'],
            ],
            'replacement_items' => [
                ['product_variant_size_id' => $expensiveSize->id, 'quantity' => 1, 'unit_price' => 2499.00],
            ],
            'payment_method' => 'upi',
            'reason' => 'Upgraded to Premium Boot',
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/pos/sales/{$this->invoice->id}/exchange", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.price_difference', 800)
            ->assertJsonPath('data.amount_paid', 800)
            ->assertJsonPath('data.payment_method', 'upi');
    }

    public function test_exchange_with_store_credit_refund()
    {
        $cheaperProduct = Product::create([
            'article_number' => 'RP-CHP-01',
            'name' => 'Casual Canvas Slipper',
            'slug' => 'casual-canvas-slipper',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'is_active' => true,
        ]);
        $cheaperVariant = ProductVariant::create(['product_id' => $cheaperProduct->id, 'color_id' => $this->color->id]);
        $cheaperSize = ProductVariantSize::create([
            'product_variant_id' => $cheaperVariant->id,
            'size_id' => $this->size8->id,
            'sku' => 'RP-CHP-01-BLK-8',
            'selling_price' => 999.00,
        ]);

        \App\Models\InventoryStock::create([
            'store_id' => $this->store->id,
            'product_variant_size_id' => $cheaperSize->id,
            'stock_quantity' => 10,
        ]);

        $payload = [
            'returned_items' => [
                ['product_variant_size_id' => $this->variantSizeOld->id, 'quantity' => 1, 'restock_condition' => 'resellable'],
            ],
            'replacement_items' => [
                ['product_variant_size_id' => $cheaperSize->id, 'quantity' => 1, 'unit_price' => 999.00],
            ],
            'refund_mode' => 'store_credit',
            'reason' => 'Exchanged for slipper, store credit refund',
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/pos/sales/{$this->invoice->id}/exchange", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.price_difference', -700)
            ->assertJsonPath('data.total_refund_amount', 700)
            ->assertJsonPath('data.refund_mode', 'store_credit');
    }
}
