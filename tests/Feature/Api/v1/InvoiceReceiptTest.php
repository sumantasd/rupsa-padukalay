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

class InvoiceReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $cashier;
    protected User $noPermUser;
    protected Store $store1;
    protected Store $store2;
    protected Customer $customer;
    protected ProductVariantSize $variantSize1;
    protected PosSession $openSession1;
    protected Invoice $invoiceWithCustomer;
    protected Invoice $walkInInvoice;

    protected function setUp(): void
    {
        parent::setUp();

        $permView = Permission::create(['name' => 'products.view', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Products']);
        $permCreate = Permission::create(['name' => 'products.create', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Create Products']);

        $superAdminRole = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdminRole->permissions()->attach([$permView->id, $permCreate->id]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->superAdmin->roles()->attach($superAdminRole->id, ['model_type' => User::class]);

        $cashierRole = Role::create(['name' => 'Cashier', 'guard_name' => 'web']);
        $cashierRole->permissions()->attach([$permView->id, $permCreate->id]);

        $this->store1 = Store::create(['code' => 'ST-001', 'name' => 'Main Store', 'address' => '123 Main St, Kolkata', 'phone' => '03312345678', 'email' => 'store1@rupsa.com', 'is_active' => true]);
        $this->store2 = Store::create(['code' => 'ST-002', 'name' => 'Branch Store', 'address' => '456 Branch Rd, Howrah', 'phone' => '03387654321', 'email' => 'store2@rupsa.com', 'is_active' => true]);

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
            'name' => 'Amit Banerjee',
            'mobile_number' => '9830098300',
            'city' => 'Kolkata',
            'address' => '78 Park Street',
        ]);

        $brand = Brand::create(['name' => 'Bata', 'slug' => 'bata', 'is_active' => true]);
        $category = Category::create(['name' => 'Formal', 'slug' => 'formal', 'is_active' => true]);
        $colorBlack = Color::create(['name' => 'Black', 'code' => 'BLK']);
        $size8 = Size::create(['size_number' => '08', 'size_system' => 'UK', 'sort_order' => 8]);

        $product = Product::create([
            'article_number' => 'ART805',
            'name' => 'Men Formal Shoe',
            'slug' => 'art805-men-formal-shoe',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
        ]);

        $variant1 = ProductVariant::create(['product_id' => $product->id, 'color_id' => $colorBlack->id]);

        $this->variantSize1 = ProductVariantSize::create([
            'product_variant_id' => $variant1->id,
            'size_id' => $size8->id,
            'sku' => 'ART805-BLK-08',
            'barcode' => '8901234567890',
            'cost_price' => 500.00,
            'mrp' => 1299.00,
            'selling_price' => 1000.00,
        ]);

        InventoryStock::create(['product_variant_size_id' => $this->variantSize1->id, 'store_id' => $this->store1->id, 'stock_quantity' => 45]);

        $this->openSession1 = PosSession::create([
            'store_id' => $this->store1->id,
            'user_id' => $this->cashier->id,
            'opened_at' => now(),
            'opening_cash' => 1000.00,
            'closing_cash_system' => 1000.00,
            'status' => 'open',
        ]);

        // Invoice 1: Customer sale with GST
        $this->invoiceWithCustomer = Invoice::create([
            'invoice_number' => 'INV-20260902-001',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 2000.00,
            'discount_amount' => 100.00,
            'is_gst_enabled' => true,
            'taxable_amount' => 1900.00,
            'total_cgst' => 95.00,
            'total_sgst' => 95.00,
            'total_igst' => 0.00,
            'total_tax' => 190.00,
            'grand_total' => 2090.00,
            'paid_amount' => 2090.00,
            'change_returned' => 0.00,
            'payment_status' => 'paid',
            'sale_type' => 'pos_counter',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $this->invoiceWithCustomer->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'sku_snapshot' => $this->variantSize1->sku,
            'article_number_snapshot' => 'ART805',
            'product_name_snapshot' => 'Men Formal Shoe',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '08',
            'hsn_code_snapshot' => '6403',
            'cost_price' => 500.00,
            'mrp' => 1299.00,
            'unit_price' => 1000.00,
            'quantity' => 2,
            'discount_amount' => 100.00,
            'tax_rate_percentage' => 10.00,
            'taxable_value' => 1900.00,
            'cgst_amount' => 95.00,
            'sgst_amount' => 95.00,
            'igst_amount' => 0.00,
            'total_tax_amount' => 190.00,
            'subtotal' => 1900.00,
        ]);

        InvoicePayment::create([
            'invoice_id' => $this->invoiceWithCustomer->id,
            'payment_method' => 'cash',
            'amount' => 1000.00,
            'notes' => 'Cash Payment',
            'payment_time' => now(),
        ]);

        InvoicePayment::create([
            'invoice_id' => $this->invoiceWithCustomer->id,
            'payment_method' => 'upi',
            'amount' => 1090.00,
            'transaction_reference' => 'UTR998877',
            'notes' => 'UPI Split',
            'payment_time' => now(),
        ]);

        // Invoice 2: Walk-in customer sale
        $this->walkInInvoice = Invoice::create([
            'invoice_number' => 'INV-20260902-002',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'customer_id' => null,
            'subtotal' => 1000.00,
            'discount_amount' => 0.00,
            'is_gst_enabled' => false,
            'taxable_amount' => 1000.00,
            'total_cgst' => 0.00,
            'total_sgst' => 0.00,
            'total_igst' => 0.00,
            'total_tax' => 0.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'change_returned' => 0.00,
            'payment_status' => 'paid',
            'sale_type' => 'pos_counter',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $this->walkInInvoice->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'sku_snapshot' => $this->variantSize1->sku,
            'article_number_snapshot' => 'ART805',
            'product_name_snapshot' => 'Men Formal Shoe',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '08',
            'hsn_code_snapshot' => '6403',
            'cost_price' => 500.00,
            'mrp' => 1299.00,
            'unit_price' => 1000.00,
            'quantity' => 1,
            'discount_amount' => 0.00,
            'subtotal' => 1000.00,
        ]);

        InvoicePayment::create([
            'invoice_id' => $this->walkInInvoice->id,
            'payment_method' => 'card',
            'amount' => 1000.00,
            'transaction_reference' => 'CARD9988',
            'payment_time' => now(),
        ]);
    }

    public function test_1_unauthenticated_request_rejected(): void
    {
        $this->getJson("/api/v1/pos/sales/{$this->invoiceWithCustomer->id}/invoice")->assertStatus(401);
    }

    public function test_2_user_without_permission_rejected(): void
    {
        Sanctum::actingAs($this->noPermUser);

        $this->getJson("/api/v1/pos/sales/{$this->invoiceWithCustomer->id}/invoice")->assertStatus(403);
    }

    public function test_3_authorized_user_can_retrieve_invoice(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson("/api/v1/pos/sales/{$this->invoiceWithCustomer->id}/invoice");

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Invoice receipt details retrieved successfully.',
            ]);
    }

    public function test_4_complete_invoice_data_returned(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson("/api/v1/pos/sales/{$this->invoiceWithCustomer->id}/invoice");

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'header' => [
                        'invoice_id' => $this->invoiceWithCustomer->id,
                        'invoice_number' => 'INV-20260902-001',
                        'sale_type' => 'pos_counter',
                        'status' => 'completed',
                        'payment_status' => 'paid',
                    ],
                    'store' => [
                        'id' => $this->store1->id,
                        'code' => 'ST-001',
                        'name' => 'Main Store',
                    ],
                    'cashier' => [
                        'id' => $this->cashier->id,
                        'name' => 'John Cashier',
                        'username' => 'john_cashier',
                    ],
                    'financial_totals' => [
                        'subtotal' => 2000.00,
                        'discount_amount' => 100.00,
                        'tax_total' => 190.00,
                        'grand_total' => 2090.00,
                        'paid_amount' => 2090.00,
                        'remaining_balance' => 0.00,
                    ],
                ],
            ]);
    }

    public function test_5_line_items_returned_correctly(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson("/api/v1/pos/sales/{$this->invoiceWithCustomer->id}/invoice");

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'items' => [
                        [
                            'sku' => 'ART805-BLK-08',
                            'article_number' => 'ART805',
                            'product_name' => 'Men Formal Shoe',
                            'color' => 'Black',
                            'size' => '08',
                            'hsn_code' => '6403',
                            'quantity' => 2,
                            'unit_price' => 1000.00,
                            'discount_amount' => 100.00,
                            'subtotal' => 1900.00,
                        ],
                    ],
                ],
            ]);
    }

    public function test_6_tax_breakdown_returned_correctly(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson("/api/v1/pos/sales/{$this->invoiceWithCustomer->id}/invoice");

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'tax_summary' => [
                        'is_gst_enabled' => true,
                        'taxable_amount' => 1900.00,
                        'total_cgst' => 95.00,
                        'total_sgst' => 95.00,
                        'total_igst' => 0.00,
                        'total_tax' => 190.00,
                    ],
                ],
            ]);
    }

    public function test_7_payment_information_returned_correctly(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson("/api/v1/pos/sales/{$this->invoiceWithCustomer->id}/invoice");

        $res->assertStatus(200)
            ->assertJsonCount(2, 'data.payments')
            ->assertJson([
                'data' => [
                    'payments' => [
                        ['payment_method' => 'cash', 'amount' => 1000.00],
                        ['payment_method' => 'upi', 'amount' => 1090.00, 'transaction_reference' => 'UTR998877'],
                    ],
                ],
            ]);
    }

    public function test_8_customer_information_returned_when_available(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson("/api/v1/pos/sales/{$this->invoiceWithCustomer->id}/invoice");

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'customer' => [
                        'id' => $this->customer->id,
                        'name' => 'Amit Banerjee',
                        'mobile_number' => '9830098300',
                        'city' => 'Kolkata',
                    ],
                ],
            ]);
    }

    public function test_9_anonymous_walk_in_sale_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson("/api/v1/pos/sales/{$this->walkInInvoice->id}/invoice");

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'customer' => [
                        'id' => null,
                        'name' => 'Walk-in Customer',
                    ],
                ],
            ]);
    }

    public function test_10_unauthorized_store_access_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        $store2Invoice = Invoice::create([
            'invoice_number' => 'INV-STORE2-999',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store2->id,
            'grand_total' => 500.00,
            'paid_amount' => 500.00,
            'payment_status' => 'paid',
            'sale_type' => 'pos_counter',
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->getJson("/api/v1/pos/sales/{$store2Invoice->id}/invoice")->assertStatus(403);
    }

    public function test_11_super_admin_cross_store_access_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $store2Invoice = Invoice::create([
            'invoice_number' => 'INV-STORE2-888',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store2->id,
            'grand_total' => 700.00,
            'paid_amount' => 700.00,
            'payment_status' => 'paid',
            'sale_type' => 'pos_counter',
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
        ]);

        $res = $this->getJson("/api/v1/pos/sales/{$store2Invoice->id}/invoice");

        $res->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_12_nonexistent_sale_invoice_returns_404(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->getJson('/api/v1/pos/sales/999999/invoice')->assertStatus(404);
    }

    public function test_13_re_print_does_not_create_duplicate_transaction(): void
    {
        Sanctum::actingAs($this->cashier);

        $countBefore = Invoice::count();

        // Perform multiple re-prints
        $this->getJson("/api/v1/pos/sales/{$this->invoiceWithCustomer->id}/invoice")->assertStatus(200);
        $this->getJson("/api/v1/pos/sales/{$this->invoiceWithCustomer->id}/invoice")->assertStatus(200);
        $this->getJson("/api/v1/pos/sales/{$this->invoiceWithCustomer->id}/invoice")->assertStatus(200);

        $countAfter = Invoice::count();
        $this->assertEquals($countBefore, $countAfter);
    }

    public function test_14_re_print_does_not_change_inventory(): void
    {
        Sanctum::actingAs($this->cashier);

        $stock = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first();
        $qtyBefore = $stock->stock_quantity;

        // Perform re-print calls
        $this->getJson("/api/v1/pos/sales/{$this->invoiceWithCustomer->id}/invoice")->assertStatus(200);
        $this->getJson("/api/v1/pos/sales/{$this->invoiceWithCustomer->id}/invoice")->assertStatus(200);

        $stockAfter = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first();
        $this->assertEquals($qtyBefore, $stockAfter->stock_quantity);
    }

    public function test_15_re_print_does_not_create_duplicate_stock_movement(): void
    {
        Sanctum::actingAs($this->cashier);

        $movCountBefore = StockMovement::count();

        $this->getJson("/api/v1/pos/sales/{$this->invoiceWithCustomer->id}/invoice")->assertStatus(200);
        $this->getJson("/api/v1/pos/sales/{$this->invoiceWithCustomer->id}/invoice")->assertStatus(200);

        $movCountAfter = StockMovement::count();
        $this->assertEquals($movCountBefore, $movCountAfter);
    }

    public function test_16_re_print_does_not_create_duplicate_payment(): void
    {
        Sanctum::actingAs($this->cashier);

        $payCountBefore = InvoicePayment::count();

        $this->getJson("/api/v1/pos/sales/{$this->invoiceWithCustomer->id}/invoice")->assertStatus(200);
        $this->getJson("/api/v1/pos/sales/{$this->invoiceWithCustomer->id}/invoice")->assertStatus(200);

        $payCountAfter = InvoicePayment::count();
        $this->assertEquals($payCountBefore, $payCountAfter);
    }

    public function test_17_standardized_api_response_maintained(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson("/api/v1/pos/sales/{$this->invoiceWithCustomer->id}/invoice");

        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'header' => ['invoice_id', 'invoice_number', 'client_trans_uuid', 'invoice_date', 'sale_type', 'status', 'payment_status'],
                    'store' => ['id', 'code', 'name', 'address', 'phone', 'email'],
                    'cashier' => ['id', 'name', 'username'],
                    'customer' => ['id', 'name'],
                    'items',
                    'tax_summary' => ['is_gst_enabled', 'taxable_amount', 'total_cgst', 'total_sgst', 'total_igst', 'total_tax'],
                    'financial_totals' => ['subtotal', 'discount_amount', 'tax_total', 'grand_total', 'paid_amount', 'remaining_balance', 'change_returned'],
                    'payments',
                    'receipt_footer' => ['note', 'terms'],
                ],
            ]);
    }
}
