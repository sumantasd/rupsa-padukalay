<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\ReturnSale;
use App\Models\Role;
use App\Models\Size;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\ProductionAdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosCashierRoleTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
    protected User $superAdmin;
    protected Store $store;
    protected Product $product;
    protected ProductVariantSize $variantSize;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed standard production RBAC roles and permissions
        $this->seed(ProductionAdminUserSeeder::class);

        $this->store = Store::where('code', 'STR-001')->firstOrFail();
        $cashierRole = Role::where('name', 'POS Cashier')->firstOrFail();
        $superAdminRole = Role::where('name', 'Super Admin')->firstOrFail();

        // 2. Create Cashier & Super Admin Users
        $this->cashier = User::create([
            'name' => 'Test Cashier',
            'username' => 'cashier_test',
            'email' => 'cashier@rupsa.in',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $this->cashier->roles()->attach($cashierRole->id, ['model_type' => User::class]);
        $this->cashier->stores()->attach($this->store->id, ['is_default' => true]);

        $this->superAdmin = User::create([
            'name' => 'Test Admin',
            'username' => 'admin_test',
            'email' => 'admin@rupsa.in',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $this->superAdmin->roles()->attach($superAdminRole->id, ['model_type' => User::class]);
        $this->superAdmin->stores()->attach($this->store->id, ['is_default' => true]);

        // 3. Seed Catalog (Product -> Variant -> Size -> Stock)
        $brand = Brand::create(['name' => 'Khadim', 'slug' => 'khadim', 'is_active' => true]);
        $category = Category::create(['name' => 'Formal Shoes', 'slug' => 'formal-shoes', 'is_active' => true]);
        $color = Color::create(['name' => 'Black', 'code' => 'BLK', 'hex_code' => '#000000', 'is_active' => true]);
        $size = Size::create(['size_number' => '7', 'size_system' => 'IND', 'is_active' => true]);

        $this->product = Product::create([
            'article_number' => 'KHD-101',
            'name' => 'Khadim Formal Shoe',
            'slug' => 'khd-101-khadim-formal-shoe',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'gender' => 'men',
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'color_id' => $color->id,
            'is_active' => true,
        ]);

        $this->variantSize = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $size->id,
            'sku' => 'KHD-101-BLK-7',
            'barcode' => '8901234567890',
            'cost_price' => 800,
            'mrp' => 1499,
            'selling_price' => 1299,
            'is_active' => true,
        ]);

        \App\Models\InventoryStock::create([
            'product_variant_size_id' => $this->variantSize->id,
            'store_id' => $this->store->id,
            'stock_quantity' => 10,
            'reorder_level' => 2,
        ]);
    }

    /** @test */
    public function pos_cashier_can_authenticate_and_get_token()
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'cashier_test',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.user.username', 'cashier_test');
    }

    /** @test */
    public function pos_cashier_can_open_pos_session()
    {
        $user = User::find($this->cashier->id);
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/pos/sessions', [
                'store_id' => $this->store->id,
                'opening_cash' => 500,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.store_id', $this->store->id);
    }

    /** @test */
    public function pos_cashier_can_create_a_pos_sale()
    {
        $user = User::find($this->cashier->id);
        $session = \App\Models\PosSession::create([
            'client_session_uuid' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'user_id' => $user->id,
            'opened_at' => now(),
            'opening_cash' => 500,
            'status' => 'open',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/pos/sales', [
                'client_trans_uuid' => (string) Str::uuid(),
                'store_id' => $this->store->id,
                'pos_session_id' => $session->id,
                'payment_method' => 'cash',
                'payments' => [
                    ['payment_method' => 'cash', 'amount' => 1299],
                ],
                'items' => [
                    [
                        'product_variant_size_id' => $this->variantSize->id,
                        'sku' => $this->variantSize->sku,
                        'quantity' => 1,
                        'unit_price' => 1299,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.grand_total', 1299);
    }

    /** @test */
    public function pos_cashier_can_view_product_details_with_size_variants()
    {
        $user = User::find($this->cashier->id);
        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/products/{$this->product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.variants.0.sizes.0.sku', 'KHD-101-BLK-7')
            ->assertJsonPath('data.variants.0.sizes.0.size.size_number', '7');
    }

    /** @test */
    public function pos_cashier_can_view_sales_invoices_returns_and_exchanges()
    {
        $user = User::find($this->cashier->id);
        $responseInvoices = $this->actingAs($user, 'sanctum')->getJson('/api/v1/pos/sales');
        $responseInvoices->assertStatus(200);

        $responseReturns = $this->actingAs($user, 'sanctum')->getJson('/api/v1/pos/sales/returns');
        $responseReturns->assertStatus(200);

        $responseExchanges = $this->actingAs($user, 'sanctum')->getJson('/api/v1/pos/exchanges');
        $responseExchanges->assertStatus(200);
    }

    /** @test */
    public function pos_cashier_cannot_delete_sales_invoice()
    {
        $user = User::find($this->cashier->id);
        $invoice = Invoice::create([
            'client_trans_uuid' => (string) Str::uuid(),
            'invoice_number' => 'INV-TEST-001',
            'store_id' => $this->store->id,
            'created_by' => $user->id,
            'subtotal' => 1299,
            'grand_total' => 1299,
            'paid_amount' => 1299,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/pos/sales/{$invoice->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function pos_cashier_cannot_delete_sales_return()
    {
        $user = User::find($this->cashier->id);
        $invoice = Invoice::create([
            'client_trans_uuid' => (string) Str::uuid(),
            'invoice_number' => 'INV-TEST-002',
            'store_id' => $this->store->id,
            'created_by' => $user->id,
            'subtotal' => 1299,
            'grand_total' => 1299,
            'paid_amount' => 1299,
            'status' => 'completed',
        ]);

        $returnSale = ReturnSale::create([
            'client_return_uuid' => (string) Str::uuid(),
            'return_number' => 'RET-TEST-001',
            'original_invoice_id' => $invoice->id,
            'store_id' => $this->store->id,
            'total_refund_amount' => 1299,
            'refund_mode' => 'cash',
            'processed_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/pos/sales/returns/{$returnSale->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function pos_cashier_cannot_delete_exchange()
    {
        $user = User::find($this->cashier->id);
        $invoice = Invoice::create([
            'client_trans_uuid' => (string) Str::uuid(),
            'invoice_number' => 'INV-TEST-003',
            'store_id' => $this->store->id,
            'created_by' => $user->id,
            'subtotal' => 1299,
            'grand_total' => 1299,
            'paid_amount' => 1299,
            'status' => 'completed',
        ]);

        $exchange = ReturnSale::create([
            'client_return_uuid' => (string) Str::uuid(),
            'return_number' => 'EXC-TEST-001',
            'original_invoice_id' => $invoice->id,
            'store_id' => $this->store->id,
            'total_refund_amount' => 0,
            'refund_mode' => 'exchange_offset',
            'processed_by' => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/pos/exchanges/{$exchange->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function pos_cashier_can_get_printer_settings_but_cannot_modify_them()
    {
        $user = User::find($this->cashier->id);

        // GET printer settings works for cashier so receipt printer can read configuration
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/settings/printer')
            ->assertStatus(200);

        // POST update printer settings is forbidden for cashier
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/settings/printer', ['printer_width' => '58mm'])
            ->assertStatus(403);
    }

    /** @test */
    public function pos_cashier_cannot_access_front_site_settings_or_user_role_management()
    {
        $user = User::find($this->cashier->id);
        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/website-settings')
            ->assertStatus(403);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/users')
            ->assertStatus(403);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/roles')
            ->assertStatus(403);
    }

    /** @test */
    public function super_admin_can_delete_sales_invoice()
    {
        $user = User::find($this->superAdmin->id);
        $invoice = Invoice::create([
            'client_trans_uuid' => (string) Str::uuid(),
            'invoice_number' => 'INV-TEST-004',
            'store_id' => $this->store->id,
            'created_by' => $user->id,
            'subtotal' => 1299,
            'grand_total' => 1299,
            'paid_amount' => 1299,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/pos/sales/{$invoice->id}");

        $response->assertStatus(200);
    }
}
