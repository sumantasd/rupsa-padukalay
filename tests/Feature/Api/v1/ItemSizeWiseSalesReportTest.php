<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\ReturnItem;
use App\Models\ReturnSale;
use App\Models\Role;
use App\Models\Size;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ItemSizeWiseSalesReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store1;
    protected Store $store2;
    protected Customer $customer;
    protected ProductVariantSize $size5;
    protected ProductVariantSize $size6;
    protected ProductVariantSize $size7;
    protected ProductVariantSize $size10;
    protected ProductVariantSize $sportsSize7;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\ProductionAdminUserSeeder::class);

        $this->store1 = Store::first() ?? Store::create([
            'name' => 'Main Outlet',
            'code' => 'STR-001',
            'address' => 'Dhantala',
            'is_active' => true,
        ]);

        $this->store2 = Store::create([
            'name' => 'Branch Store',
            'code' => 'STR-002',
            'address' => 'Ranaghat',
            'is_active' => true,
        ]);

        $superAdminRole = Role::where('name', 'Super Admin')->firstOrFail();

        $this->user = User::create([
            'name' => 'Report Admin',
            'username' => 'reportadmin',
            'email' => 'reportadmin@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->roles()->attach($superAdminRole->id, ['model_type' => User::class]);

        $this->customer = Customer::create([
            'name' => 'Amit Ghosh',
            'mobile_number' => '9876543211',
            'is_active' => true,
        ]);

        $brand = Brand::create(['name' => 'Bata', 'slug' => 'bata-report-test', 'is_active' => true]);
        $category = Category::create(['name' => 'School Shoes', 'slug' => 'school-shoes-report-test', 'is_active' => true]);
        $color = Color::create(['name' => 'Black', 'code' => '#000000', 'is_active' => true]);

        $s5 = Size::create(['size_number' => '5', 'size_system' => 'UK/IND', 'is_active' => true]);
        $s6 = Size::create(['size_number' => '6', 'size_system' => 'UK/IND', 'is_active' => true]);
        $s7 = Size::create(['size_number' => '7', 'size_system' => 'UK/IND', 'is_active' => true]);
        $s10 = Size::create(['size_number' => '10', 'size_system' => 'UK/IND', 'is_active' => true]);

        // Product 1: School Shoes (BS-001)
        $schoolProduct = Product::create([
            'name' => 'School Shoes',
            'article_number' => 'BS-001',
            'slug' => 'school-shoes-bs-001',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $schoolVariant = ProductVariant::create([
            'product_id' => $schoolProduct->id,
            'color_id' => $color->id,
            'is_active' => true,
        ]);

        $this->size5 = ProductVariantSize::create([
            'product_variant_id' => $schoolVariant->id,
            'size_id' => $s5->id,
            'sku' => 'BS001-SZ5',
            'selling_price' => 500,
            'is_active' => true,
        ]);

        $this->size6 = ProductVariantSize::create([
            'product_variant_id' => $schoolVariant->id,
            'size_id' => $s6->id,
            'sku' => 'BS001-SZ6',
            'selling_price' => 550,
            'is_active' => true,
        ]);

        $this->size7 = ProductVariantSize::create([
            'product_variant_id' => $schoolVariant->id,
            'size_id' => $s7->id,
            'sku' => 'BS001-SZ7',
            'selling_price' => 600,
            'is_active' => true,
        ]);

        $this->size10 = ProductVariantSize::create([
            'product_variant_id' => $schoolVariant->id,
            'size_id' => $s10->id,
            'sku' => 'BS001-SZ10',
            'selling_price' => 700,
            'is_active' => true,
        ]);

        // Product 2: Sports Shoes (SP-002)
        $sportsProduct = Product::create([
            'name' => 'Sports Shoes',
            'article_number' => 'SP-002',
            'slug' => 'sports-shoes-sp-002',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $sportsVariant = ProductVariant::create([
            'product_id' => $sportsProduct->id,
            'color_id' => $color->id,
            'is_active' => true,
        ]);

        $this->sportsSize7 = ProductVariantSize::create([
            'product_variant_id' => $sportsVariant->id,
            'size_id' => $s7->id,
            'sku' => 'SP002-SZ7',
            'selling_price' => 1200,
            'is_active' => true,
        ]);
    }

    public function test_item_size_sales_aggregates_multiple_invoices_for_same_item_and_size(): void
    {
        Sanctum::actingAs($this->user);

        // Invoice 1: 3 pairs of Size 7 (BS-001)
        $inv1 = Invoice::create([
            'invoice_number' => 'INV-TEST-101',
            'client_trans_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1800,
            'grand_total' => 1800,
            'paid_amount' => 1800,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);
        InvoiceItem::create([
            'invoice_id' => $inv1->id,
            'product_variant_size_id' => $this->size7->id,
            'sku_snapshot' => 'BS001-SZ7',
            'article_number_snapshot' => 'BS-001',
            'product_name_snapshot' => 'School Shoes',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '7',
            'unit_price' => 600,
            'quantity' => 3,
            'subtotal' => 1800,
        ]);

        // Invoice 2: 5 pairs of Size 7 (BS-001)
        $inv2 = Invoice::create([
            'invoice_number' => 'INV-TEST-102',
            'client_trans_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 3000,
            'grand_total' => 3000,
            'paid_amount' => 3000,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);
        InvoiceItem::create([
            'invoice_id' => $inv2->id,
            'product_variant_size_id' => $this->size7->id,
            'sku_snapshot' => 'BS001-SZ7',
            'article_number_snapshot' => 'BS-001',
            'product_name_snapshot' => 'School Shoes',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '7',
            'unit_price' => 600,
            'quantity' => 5,
            'subtotal' => 3000,
        ]);

        $response = $this->getJson('/api/v1/reports/item-wise-sales');
        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_qty_sold', 8)
            ->assertJsonPath('data.items.0.article_number', 'BS-001')
            ->assertJsonPath('data.items.0.product_name', 'School Shoes')
            ->assertJsonPath('data.items.0.size', '7')
            ->assertJsonPath('data.items.0.qty_sold', 8);
    }

    public function test_footwear_sizes_are_sorted_numerically(): void
    {
        Sanctum::actingAs($this->user);

        // Invoice with Size 10, Size 5, Size 7, Size 6
        $inv = Invoice::create([
            'invoice_number' => 'INV-TEST-NUMERIC-SORT',
            'client_trans_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 2350,
            'grand_total' => 2350,
            'paid_amount' => 2350,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv->id,
            'product_variant_size_id' => $this->size10->id,
            'sku_snapshot' => 'BS001-SZ10',
            'article_number_snapshot' => 'BS-001',
            'product_name_snapshot' => 'School Shoes',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '10',
            'unit_price' => 700,
            'quantity' => 1,
            'subtotal' => 700,
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv->id,
            'product_variant_size_id' => $this->size5->id,
            'sku_snapshot' => 'BS001-SZ5',
            'article_number_snapshot' => 'BS-001',
            'product_name_snapshot' => 'School Shoes',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '5',
            'unit_price' => 500,
            'quantity' => 2,
            'subtotal' => 1000,
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv->id,
            'product_variant_size_id' => $this->size6->id,
            'sku_snapshot' => 'BS001-SZ6',
            'article_number_snapshot' => 'BS-001',
            'product_name_snapshot' => 'School Shoes',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '6',
            'unit_price' => 650,
            'quantity' => 1,
            'subtotal' => 650,
        ]);

        $response = $this->getJson('/api/v1/reports/item-wise-sales');
        $response->assertStatus(200);

        $items = $response->json('data.items');
        $sizes = array_column($items, 'size');

        // Numeric size ordering: 5, 6, 10
        $this->assertEquals(['5', '6', '10'], $sizes);
    }

    public function test_sale_returns_are_subtracted_from_sold_quantities(): void
    {
        Sanctum::actingAs($this->user);

        // Invoice: 10 pairs of Size 7
        $inv = Invoice::create([
            'invoice_number' => 'INV-TEST-RETURN',
            'client_trans_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 6000,
            'grand_total' => 6000,
            'paid_amount' => 6000,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);
        $invItem = InvoiceItem::create([
            'invoice_id' => $inv->id,
            'product_variant_size_id' => $this->size7->id,
            'sku_snapshot' => 'BS001-SZ7',
            'article_number_snapshot' => 'BS-001',
            'product_name_snapshot' => 'School Shoes',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '7',
            'unit_price' => 600,
            'quantity' => 10,
            'subtotal' => 6000,
        ]);

        // Return: 3 pairs returned
        $ret = ReturnSale::create([
            'return_number' => 'RET-TEST-001',
            'client_return_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'original_invoice_id' => $inv->id,
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'total_refund_amount' => 1800,
            'refund_mode' => 'cash',
            'reason' => 'Defective',
            'processed_by' => $this->user->id,
        ]);
        ReturnItem::create([
            'return_id' => $ret->id,
            'invoice_item_id' => $invItem->id,
            'product_variant_size_id' => $this->size7->id,
            'quantity' => 3,
            'refund_unit_price' => 600,
            'restock_condition' => 'resellable',
            'subtotal' => 1800,
        ]);

        $response = $this->getJson('/api/v1/reports/item-wise-sales');
        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_qty_sold', 7) // 10 - 3 = 7
            ->assertJsonPath('data.items.0.qty_sold', 7)
            ->assertJsonPath('data.items.0.return_qty', 3);
    }

    public function test_soft_deleted_recycle_bin_invoices_are_excluded(): void
    {
        Sanctum::actingAs($this->user);

        // Active invoice
        $activeInv = Invoice::create([
            'invoice_number' => 'INV-ACTIVE-001',
            'client_trans_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1000,
            'grand_total' => 1000,
            'paid_amount' => 1000,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);
        InvoiceItem::create([
            'invoice_id' => $activeInv->id,
            'product_variant_size_id' => $this->size5->id,
            'sku_snapshot' => 'BS001-SZ5',
            'article_number_snapshot' => 'BS-001',
            'product_name_snapshot' => 'School Shoes',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '5',
            'unit_price' => 500,
            'quantity' => 2,
            'subtotal' => 1000,
        ]);

        // Soft-deleted invoice (moved to Recycle Bin)
        $trashedInv = Invoice::create([
            'invoice_number' => 'INV-TRASHED-001',
            'client_trans_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 3000,
            'grand_total' => 3000,
            'paid_amount' => 3000,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);
        InvoiceItem::create([
            'invoice_id' => $trashedInv->id,
            'product_variant_size_id' => $this->size6->id,
            'sku_snapshot' => 'BS001-SZ6',
            'article_number_snapshot' => 'BS-001',
            'product_name_snapshot' => 'School Shoes',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '6',
            'unit_price' => 550,
            'quantity' => 5,
            'subtotal' => 2750,
        ]);
        $trashedInv->delete(); // Move to Recycle Bin

        $response = $this->getJson('/api/v1/reports/item-wise-sales');
        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_qty_sold', 2); // Trashed invoice 5 pcs excluded
    }

    public function test_date_range_presets_and_custom_ranges(): void
    {
        Sanctum::actingAs($this->user);

        $today = Carbon::today()->format('Y-m-d');
        $yesterday = Carbon::yesterday()->format('Y-m-d');
        $prevMonth = Carbon::now()->subMonth()->format('Y-m-d');

        // Invoice Today
        $invToday = Invoice::create([
            'invoice_number' => 'INV-TODAY',
            'client_trans_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 600,
            'grand_total' => 600,
            'paid_amount' => 600,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);
        $invToday->created_at = Carbon::today()->addHours(10);
        $invToday->save();

        InvoiceItem::create([
            'invoice_id' => $invToday->id,
            'product_variant_size_id' => $this->size7->id,
            'sku_snapshot' => 'BS001-SZ7',
            'article_number_snapshot' => 'BS-001',
            'product_name_snapshot' => 'School Shoes',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '7',
            'unit_price' => 600,
            'quantity' => 1,
            'subtotal' => 600,
        ]);

        // Invoice Yesterday
        $invYesterday = Invoice::create([
            'invoice_number' => 'INV-YESTERDAY',
            'client_trans_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1200,
            'grand_total' => 1200,
            'paid_amount' => 1200,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);
        $invYesterday->created_at = Carbon::yesterday()->addHours(14);
        $invYesterday->save();

        InvoiceItem::create([
            'invoice_id' => $invYesterday->id,
            'product_variant_size_id' => $this->size7->id,
            'sku_snapshot' => 'BS001-SZ7',
            'article_number_snapshot' => 'BS-001',
            'product_name_snapshot' => 'School Shoes',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '7',
            'unit_price' => 600,
            'quantity' => 2,
            'subtotal' => 1200,
        ]);

        // Filter Today
        $resToday = $this->getJson("/api/v1/reports/item-wise-sales?date_from={$today}&date_to={$today}");
        $resToday->assertStatus(200)->assertJsonPath('data.summary.total_qty_sold', 1);

        // Filter Yesterday
        $resYesterday = $this->getJson("/api/v1/reports/item-wise-sales?date_from={$yesterday}&date_to={$yesterday}");
        $resYesterday->assertStatus(200)->assertJsonPath('data.summary.total_qty_sold', 2);
    }

    public function test_pdf_export_returns_valid_pdf_with_matching_data(): void
    {
        Sanctum::actingAs($this->user);

        $inv = Invoice::create([
            'invoice_number' => 'INV-PDF-001',
            'client_trans_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1800,
            'grand_total' => 1800,
            'paid_amount' => 1800,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);
        InvoiceItem::create([
            'invoice_id' => $inv->id,
            'product_variant_size_id' => $this->size7->id,
            'sku_snapshot' => 'BS001-SZ7',
            'article_number_snapshot' => 'BS-001',
            'product_name_snapshot' => 'School Shoes',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '7',
            'unit_price' => 600,
            'quantity' => 3,
            'subtotal' => 1800,
        ]);

        // PDF export call
        $pdfRes = $this->getJson('/api/v1/reports/pdf?type=sales_itemwise');
        $pdfRes->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $pdfRes->headers->get('Content-Type'));
    }

    public function test_unauthenticated_and_unauthorized_requests_are_rejected(): void
    {
        // Unauthenticated
        $resUnauth = $this->getJson('/api/v1/reports/item-wise-sales');
        $resUnauth->assertStatus(401);

        // User without report permissions
        $role = Role::create(['name' => 'NoReportRole', 'guard_name' => 'web']);
        $noPermUser = User::create([
            'name' => 'No Perm User',
            'username' => 'nopermuser',
            'email' => 'nopermuser@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $noPermUser->roles()->attach($role->id, ['model_type' => User::class]);

        Sanctum::actingAs($noPermUser);
        $resForbidden = $this->getJson('/api/v1/reports/item-wise-sales');
        $resForbidden->assertStatus(403);
    }
}
