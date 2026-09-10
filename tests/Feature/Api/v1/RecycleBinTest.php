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
use App\Models\Size;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecycleBinTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;
    protected Customer $customer;
    protected Invoice $invoice;
    protected ReturnSale $saleReturn;
    protected ReturnSale $saleExchange;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\ProductionAdminUserSeeder::class);

        $this->store = Store::first() ?? Store::create([
            'name' => 'Main Outlet',
            'code' => 'STR-001',
            'address' => 'Dhantala',
            'is_active' => true,
        ]);

        $superAdminRole = \App\Models\Role::where('name', 'Super Admin')->firstOrFail();

        $this->user = User::create([
            'name' => 'Recycle Admin',
            'username' => 'recycleadmin',
            'email' => 'recycleadmin@test.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->roles()->attach($superAdminRole->id, ['model_type' => User::class]);

        $this->customer = Customer::create([
            'name' => 'Rahul Sen',
            'mobile_number' => '9876543210',
            'is_active' => true,
        ]);

        $brand = Brand::create(['name' => 'Bata', 'slug' => 'bata-recycle-test', 'is_active' => true]);
        $category = Category::create(['name' => 'School Shoes', 'slug' => 'school-shoes-recycle-test', 'is_active' => true]);
        $color = Color::create(['name' => 'Black', 'code' => '#000000', 'is_active' => true]);
        $size = Size::create(['size_number' => '7', 'size_system' => 'UK/IND', 'is_active' => true]);

        $product = Product::create([
            'name' => 'Bata School Shoes',
            'article_number' => 'BS-001',
            'slug' => 'bata-school-shoes-recycle-test',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color_id' => $color->id,
            'is_active' => true,
        ]);

        $variantSize = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $size->id,
            'sku' => 'SKU-RECYCLE-07',
            'barcode' => '8909990007',
            'selling_price' => 899,
            'is_active' => true,
        ]);

        // 1. Create a Sale Invoice
        $this->invoice = Invoice::create([
            'invoice_number' => 'INV-RECYCLE-001',
            'client_trans_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 899,
            'grand_total' => 899,
            'paid_amount' => 899,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'product_variant_size_id' => $variantSize->id,
            'sku_snapshot' => 'SKU-RECYCLE-07',
            'article_number_snapshot' => 'BS-001',
            'product_name_snapshot' => 'Bata School Shoes',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '7',
            'unit_price' => 899,
            'quantity' => 1,
            'subtotal' => 899,
        ]);

        // 2. Create a Sale Return
        $this->saleReturn = ReturnSale::create([
            'return_number' => 'RET-RECYCLE-001',
            'client_return_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'original_invoice_id' => $this->invoice->id,
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'total_refund_amount' => 899,
            'refund_mode' => 'cash',
            'reason' => 'Defective size fit',
            'processed_by' => $this->user->id,
        ]);

        // 3. Create a Sale Exchange
        $this->saleExchange = ReturnSale::create([
            'return_number' => 'EXC-RECYCLE-001',
            'client_return_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'original_invoice_id' => $this->invoice->id,
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'total_refund_amount' => 899,
            'price_difference' => 100,
            'refund_mode' => 'exchange_offset',
            'reason' => 'Exchanged for size 8',
            'processed_by' => $this->user->id,
        ]);
    }

    public function test_deleting_sales_moves_record_to_recycle_bin_and_allows_restore(): void
    {
        Sanctum::actingAs($this->user);

        // Create standalone Invoice without returns/exchanges attached
        $standaloneInvoice = Invoice::create([
            'invoice_number' => 'INV-STANDALONE-001',
            'client_trans_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 899,
            'grand_total' => 899,
            'paid_amount' => 899,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        // Delete Invoice
        $response = $this->deleteJson("/api/v1/pos/sales/{$standaloneInvoice->id}");
        $response->assertStatus(200);

        // Assert Soft Deleted
        $this->assertSoftDeleted('invoices', ['id' => $standaloneInvoice->id]);

        // Check Recycle Bin Sales tab
        $rbResponse = $this->getJson('/api/v1/recycle-bin?type=sales');
        $rbResponse->assertStatus(200)
            ->assertJsonPath('data.data.0.id', $standaloneInvoice->id)
            ->assertJsonPath('data.data.0.type', 'sales')
            ->assertJsonPath('data.data.0.code_or_sku', 'INV-STANDALONE-001');

        // Restore Invoice from Recycle Bin
        $restoreResponse = $this->postJson("/api/v1/recycle-bin/sales/{$standaloneInvoice->id}/restore");
        $restoreResponse->assertStatus(200);

        // Assert Restored in Database
        $this->assertDatabaseHas('invoices', [
            'id' => $standaloneInvoice->id,
            'deleted_at' => null,
        ]);
    }

    public function test_deleting_sale_return_moves_record_to_recycle_bin_and_allows_restore(): void
    {
        Sanctum::actingAs($this->user);

        // Delete Return
        $response = $this->deleteJson("/api/v1/pos/sales/returns/{$this->saleReturn->id}");
        $response->assertStatus(200);

        // Assert Soft Deleted
        $this->assertSoftDeleted('returns', ['id' => $this->saleReturn->id]);

        // Check Recycle Bin Sale Returns tab
        $rbResponse = $this->getJson('/api/v1/recycle-bin?type=sale_returns');
        $rbResponse->assertStatus(200)
            ->assertJsonPath('data.data.0.id', $this->saleReturn->id)
            ->assertJsonPath('data.data.0.type', 'sales_returns')
            ->assertJsonPath('data.data.0.code_or_sku', 'RET-RECYCLE-001');

        // Restore Return from Recycle Bin
        $restoreResponse = $this->postJson("/api/v1/recycle-bin/sale_returns/{$this->saleReturn->id}/restore");
        $restoreResponse->assertStatus(200);

        // Assert Restored
        $this->assertDatabaseHas('returns', [
            'id' => $this->saleReturn->id,
            'deleted_at' => null,
        ]);
    }

    public function test_deleting_sale_exchange_moves_record_to_recycle_bin_and_allows_restore(): void
    {
        Sanctum::actingAs($this->user);

        // Delete Exchange
        $response = $this->deleteJson("/api/v1/pos/exchanges/{$this->saleExchange->id}");
        $response->assertStatus(200);

        // Assert Soft Deleted
        $this->assertSoftDeleted('returns', ['id' => $this->saleExchange->id]);

        // Check Recycle Bin Sale Exchanges tab
        $rbResponse = $this->getJson('/api/v1/recycle-bin?type=sale_exchanges');
        $rbResponse->assertStatus(200)
            ->assertJsonPath('data.data.0.id', $this->saleExchange->id)
            ->assertJsonPath('data.data.0.type', 'sales_exchanges')
            ->assertJsonPath('data.data.0.code_or_sku', 'EXC-RECYCLE-001');

        // Restore Exchange from Recycle Bin
        $restoreResponse = $this->postJson("/api/v1/recycle-bin/sale_exchanges/{$this->saleExchange->id}/restore");
        $restoreResponse->assertStatus(200);

        // Assert Restored
        $this->assertDatabaseHas('returns', [
            'id' => $this->saleExchange->id,
            'deleted_at' => null,
        ]);
    }

    public function test_permanent_delete_sales_force_deletes_record_and_items(): void
    {
        Sanctum::actingAs($this->user);

        $inv = Invoice::create([
            'invoice_number' => 'INV-FORCE-001',
            'client_trans_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 500,
            'grand_total' => 500,
            'paid_amount' => 500,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);
        $inv->delete(); // soft delete

        // Force delete from Recycle Bin
        $fdResponse = $this->deleteJson("/api/v1/recycle-bin/sales/{$inv->id}/force-delete");
        $fdResponse->assertStatus(200);

        $this->assertDatabaseMissing('invoices', ['id' => $inv->id]);
    }

    public function test_permanent_delete_sale_returns_force_deletes_record_and_items(): void
    {
        Sanctum::actingAs($this->user);

        $ret = ReturnSale::create([
            'return_number' => 'RET-FORCE-001',
            'client_return_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'original_invoice_id' => $this->invoice->id,
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'total_refund_amount' => 300,
            'refund_mode' => 'cash',
            'reason' => 'Defective',
            'processed_by' => $this->user->id,
        ]);
        $ret->delete(); // soft delete

        // Force delete from Recycle Bin
        $fdResponse = $this->deleteJson("/api/v1/recycle-bin/sale_returns/{$ret->id}/force-delete");
        $fdResponse->assertStatus(200);

        $this->assertDatabaseMissing('returns', ['id' => $ret->id]);
    }

    public function test_permanent_delete_sale_exchanges_force_deletes_record_and_items(): void
    {
        Sanctum::actingAs($this->user);

        $exc = ReturnSale::create([
            'return_number' => 'EXC-FORCE-001',
            'client_return_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'original_invoice_id' => $this->invoice->id,
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'total_refund_amount' => 300,
            'price_difference' => 50,
            'refund_mode' => 'exchange_offset',
            'reason' => 'Size Exchange',
            'processed_by' => $this->user->id,
        ]);
        $exc->delete(); // soft delete

        // Force delete from Recycle Bin
        $fdResponse = $this->deleteJson("/api/v1/recycle-bin/sale_exchanges/{$exc->id}/force-delete");
        $fdResponse->assertStatus(200);

        $this->assertDatabaseMissing('returns', ['id' => $exc->id]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/recycle-bin?type=sales');
        $response->assertStatus(401);
    }
}
