<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CustomerPurchaseHistoryDetailTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Customer $customer;
    protected Store $store;
    protected ProductVariantSize $variantSize;

    protected function setUp(): void
    {
        parent::setUp();

        $permView = Permission::create(['name' => 'products.view', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Products']);
        $permCreate = Permission::create(['name' => 'products.create', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Create Products']);

        $superAdminRole = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdminRole->permissions()->attach([$permView->id, $permCreate->id]);

        $this->adminUser = User::create([
            'name' => 'Super Admin',
            'username' => 'cust_admin',
            'email' => 'cust_admin@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($superAdminRole->id, ['model_type' => User::class]);

        $this->store = Store::create([
            'id' => 1,
            'code' => 'STR-001',
            'name' => 'RUPSA PADUKALAYA - Main Outlet',
            'address' => 'Dhantala Bazar, Dhantala',
            'city' => 'Nadia',
            'pincode' => '741202',
            'is_active' => true,
        ]);

        $this->customer = Customer::create([
            'name' => 'Rahul Das',
            'mobile_number' => '9876543210',
            'email' => 'rahul@example.com',
            'city' => 'Nadia',
            'address' => 'Dhantala Bazar',
            'reward_points' => 150,
            'is_active' => true,
        ]);

        $category = Category::create(['name' => 'Gents Shoes', 'slug' => 'gents-shoes', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Bata', 'slug' => 'bata', 'is_active' => true]);
        $color = Color::create(['name' => 'Black', 'code' => 'BLK', 'hex_code' => '#000000', 'is_active' => true]);
        $size = Size::create(['size_number' => '8', 'size_system' => 'UK', 'is_active' => true]);

        $product = Product::create([
            'article_number' => 'RP-FORMAL-01',
            'name' => 'Executive Classic Oxford',
            'slug' => 'rp-formal-01-executive-classic-oxford',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'gender' => 'men',
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color_id' => $color->id,
        ]);

        $this->variantSize = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $size->id,
            'sku' => 'RPFORMAL01-BLK-08',
            'cost_price' => 500,
            'mrp' => 1299,
            'selling_price' => 999,
            'is_active' => true,
        ]);
    }

    public function test_customer_purchase_history_returns_itemized_invoices_and_pagination()
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-20260903-TEST01',
            'client_trans_uuid' => 'uuid-test-01',
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1998.00,
            'discount_amount' => 100.00,
            'is_gst_enabled' => false,
            'taxable_amount' => 1898.00,
            'grand_total' => 1898.00,
            'paid_amount' => 1898.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->adminUser->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_variant_size_id' => $this->variantSize->id,
            'sku_snapshot' => $this->variantSize->sku,
            'article_number_snapshot' => 'RP-FORMAL-01',
            'product_name_snapshot' => 'Executive Classic Oxford',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '8',
            'cost_price' => 500,
            'mrp' => 1299,
            'unit_price' => 999,
            'quantity' => 2,
            'discount_amount' => 100,
            'subtotal' => 1898,
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_method' => 'cash',
            'amount' => 1898.00,
            'payment_time' => now(),
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/customers/{$this->customer->id}/purchase-history");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.invoice_number', 'INV-20260903-TEST01')
            ->assertJsonPath('data.items.0.items.0.article_number_snapshot', 'RP-FORMAL-01')
            ->assertJsonPath('data.items.0.items.0.size_number_snapshot', '8')
            ->assertJsonPath('data.items.0.items.0.color_name_snapshot', 'Black')
            ->assertJsonPath('data.items.0.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.payments.0.payment_method', 'cash');

    }

    public function test_customer_purchase_summary_calculates_correct_totals_and_items_purchased()
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-20260903-TEST02',
            'client_trans_uuid' => 'uuid-test-02',
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 999.00,
            'discount_amount' => 0.00,
            'is_gst_enabled' => false,
            'taxable_amount' => 999.00,
            'grand_total' => 999.00,
            'paid_amount' => 999.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->adminUser->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_variant_size_id' => $this->variantSize->id,
            'sku_snapshot' => $this->variantSize->sku,
            'article_number_snapshot' => 'RP-FORMAL-01',
            'product_name_snapshot' => 'Executive Classic Oxford',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '8',
            'cost_price' => 500,
            'mrp' => 1299,
            'unit_price' => 999,
            'quantity' => 3,
            'discount_amount' => 0,
            'subtotal' => 999,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/customers/{$this->customer->id}/purchase-summary");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_orders', 1)
            ->assertJsonPath('data.total_net_purchase_value', 999)
            ->assertJsonPath('data.total_items_purchased', 3);
    }

    public function test_customer_directory_returns_real_total_spent_matching_purchase_history()
    {
        $custNoPurchase = Customer::create([
            'name' => 'Empty Customer',
            'mobile_number' => '9000000001',
            'is_active' => true,
        ]);

        $custMultiInvoice = Customer::create([
            'name' => 'Frequent Shopper',
            'mobile_number' => '9000000002',
            'is_active' => true,
        ]);

        // Invoice 1 for Frequent Shopper (Completed ₹1,500)
        Invoice::create([
            'invoice_number' => 'INV-CUST-101',
            'client_trans_uuid' => 'uuid-c-101',
            'store_id' => $this->store->id,
            'customer_id' => $custMultiInvoice->id,
            'subtotal' => 1500.00,
            'discount_amount' => 0.00,
            'taxable_amount' => 1500.00,
            'grand_total' => 1500.00,
            'paid_amount' => 1500.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->adminUser->id,
        ]);

        // Invoice 2 for Frequent Shopper (Completed ₹2,500)
        Invoice::create([
            'invoice_number' => 'INV-CUST-102',
            'client_trans_uuid' => 'uuid-c-102',
            'store_id' => $this->store->id,
            'customer_id' => $custMultiInvoice->id,
            'subtotal' => 2500.00,
            'discount_amount' => 0.00,
            'taxable_amount' => 2500.00,
            'grand_total' => 2500.00,
            'paid_amount' => 2500.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->adminUser->id,
        ]);

        // Invoice 3 for Frequent Shopper (Cancelled ₹5,000 -> MUST BE EXCLUDED)
        Invoice::create([
            'invoice_number' => 'INV-CUST-103-CANCELLED',
            'client_trans_uuid' => 'uuid-c-103',
            'store_id' => $this->store->id,
            'customer_id' => $custMultiInvoice->id,
            'subtotal' => 5000.00,
            'discount_amount' => 0.00,
            'taxable_amount' => 5000.00,
            'grand_total' => 5000.00,
            'paid_amount' => 0.00,
            'payment_status' => 'unpaid',
            'status' => 'cancelled',
            'created_by' => $this->adminUser->id,
        ]);

        // Walk-in Retail Customer Invoice (customer_id = null -> MUST NOT BE ASSIGNED TO ANY CUSTOMER)
        Invoice::create([
            'invoice_number' => 'INV-WALKIN-999',
            'client_trans_uuid' => 'uuid-w-999',
            'store_id' => $this->store->id,
            'customer_id' => null,
            'subtotal' => 9999.00,
            'discount_amount' => 0.00,
            'taxable_amount' => 9999.00,
            'grand_total' => 9999.00,
            'paid_amount' => 9999.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/customers');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $items = collect($response->json('data.items'));

        $emptyCustomerData = $items->firstWhere('id', $custNoPurchase->id);
        $this->assertNotNull($emptyCustomerData);
        $this->assertEquals(0, $emptyCustomerData['total_purchases_count']);
        $this->assertEquals(0.00, $emptyCustomerData['total_spent_amount']);

        $frequentShopperData = $items->firstWhere('id', $custMultiInvoice->id);
        $this->assertNotNull($frequentShopperData);
        $this->assertEquals(2, $frequentShopperData['total_purchases_count']);
        $this->assertEquals(4000.00, $frequentShopperData['total_spent_amount']); // 1500 + 2500, excluding 5000 cancelled
    }
}

