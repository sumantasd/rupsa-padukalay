<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Permission;
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
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StorePerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $storeManager;
    protected User $unauthorizedUser;
    protected Store $store1;
    protected Store $store2;
    protected ProductVariantSize $variantSize1;

    protected function setUp(): void
    {
        parent::setUp();

        $permReports = Permission::create([
            'name' => 'reports.view',
            'guard_name' => 'web',
            'module_group' => 'Reports',
            'display_name' => 'View Reports',
        ]);

        // Super Admin
        $superAdminRole = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdminRole->permissions()->attach($permReports->id);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'username' => 'superadmin_perf',
            'email' => 'superadmin_perf@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->superAdmin->roles()->attach($superAdminRole->id, ['model_type' => User::class]);

        // Store Manager with reports.view permission
        $managerRole = Role::create(['name' => 'Store Manager', 'guard_name' => 'web']);
        $managerRole->permissions()->attach($permReports->id);

        $this->storeManager = User::create([
            'name' => 'Store Manager',
            'username' => 'mgr_perf',
            'email' => 'mgr_perf@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->storeManager->roles()->attach($managerRole->id, ['model_type' => User::class]);

        // Unauthorized User (no permissions)
        $this->unauthorizedUser = User::create([
            'name' => 'Unauthorized Cashier',
            'username' => 'unauth_perf',
            'email' => 'unauth_perf@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        // Stores
        $this->store1 = Store::create([
            'code' => 'STR-001',
            'name' => 'Main Outlet',
            'city' => 'Kolkata',
            'is_active' => true,
        ]);

        $this->store2 = Store::create([
            'code' => 'STR-002',
            'name' => 'Branch Outlet',
            'city' => 'Kolkata',
            'is_active' => true,
        ]);

        // Assign only store1 to storeManager
        $this->storeManager->stores()->attach($this->store1->id);

        // Product Catalog Setup
        $brand = Brand::create(['name' => 'Bata', 'slug' => 'bata', 'is_active' => true]);
        $category = Category::create(['name' => 'Formal', 'slug' => 'formal', 'is_active' => true]);
        $color = Color::create(['name' => 'Black', 'code' => 'BLK']);
        $size = Size::create(['size_number' => '08', 'size_system' => 'UK']);

        $product = Product::create([
            'name' => 'Leather Oxford Shoes',
            'slug' => 'leather-oxford-shoes',
            'article_number' => 'ART-101',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color_id' => $color->id,
            'sku_prefix' => 'ART101-BLK',
            'is_active' => true,
        ]);

        $this->variantSize1 = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $size->id,
            'sku' => 'ART101-BLK-8',
            'cost_price' => 500.00,
            'selling_price' => 1000.00,
            'mrp' => 1200.00,
            'is_active' => true,
        ]);

        // Stock in store1
        InventoryStock::create([
            'store_id' => $this->store1->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'stock_quantity' => 50,
            'reorder_level' => 10,
        ]);
    }

    public function test_unauthenticated_performance_request_is_rejected(): void
    {
        $this->getJson("/api/v1/stores/{$this->store1->id}/performance")->assertStatus(401);
    }

    public function test_unauthorized_user_without_permission_is_denied(): void
    {
        Sanctum::actingAs($this->unauthorizedUser);

        $this->getJson("/api/v1/stores/{$this->store1->id}/performance")->assertStatus(403);
    }

    public function test_non_superadmin_accessing_unassigned_store_performance_is_denied(): void
    {
        Sanctum::actingAs($this->storeManager);

        // Manager has access to store1, but not store2
        $this->getJson("/api/v1/stores/{$this->store2->id}/performance")->assertStatus(403);
    }

    public function test_authorized_user_can_access_store_performance(): void
    {
        Sanctum::actingAs($this->storeManager);

        $res = $this->getJson("/api/v1/stores/{$this->store1->id}/performance");

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'store' => [
                        'id' => $this->store1->id,
                        'code' => 'STR-001',
                    ],
                ],
            ]);
    }

    public function test_sales_totals_net_sales_and_cogs_calculation_accuracy(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Create completed invoice
        $invoice = Invoice::create([
            'client_trans_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store1->id,
            'invoice_number' => 'INV-PERF-01',
            'subtotal' => 2000.00,
            'discount_amount' => 200.00,
            'taxable_amount' => 1800.00,
            'total_tax' => 200.00,
            'grand_total' => 2000.00,
            'paid_amount' => 2000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
            'created_at' => now(),
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'sku_snapshot' => $this->variantSize1->sku,
            'article_number_snapshot' => 'ART-101',
            'product_name_snapshot' => 'Leather Oxford Shoes',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '08',
            'quantity' => 2,
            'unit_price' => 1000.00,
            'cost_price' => 500.00,
            'mrp' => 1200.00,
            'subtotal' => 2000.00,
            'taxable_value' => 1800.00,
            'total_tax_amount' => 200.00,
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_method' => 'cash',
            'amount' => 2000.00,
            'payment_time' => now(),
            'created_at' => now(),
        ]);

        $res = $this->getJson("/api/v1/stores/{$this->store1->id}/performance?period=this_month");

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'kpis' => [
                        'total_sales' => 2000.00,
                        'net_sales' => 2000.00,
                        'total_orders' => 1,
                        'items_sold' => 2,
                        'cogs' => 1000.00, // 2 * 500
                        'gross_profit' => 1000.00, // 2000 - 1000
                    ],
                ],
            ]);
    }

    public function test_returns_and_exchanges_adjust_net_sales_and_cogs(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Invoice
        $invoice = Invoice::create([
            'client_trans_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store1->id,
            'invoice_number' => 'INV-PERF-02',
            'subtotal' => 2000.00,
            'discount_amount' => 0.00,
            'taxable_amount' => 1800.00,
            'total_tax' => 200.00,
            'grand_total' => 2000.00,
            'paid_amount' => 2000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
            'created_at' => now(),
        ]);

        $invItem = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'sku_snapshot' => $this->variantSize1->sku,
            'article_number_snapshot' => 'ART-101',
            'product_name_snapshot' => 'Leather Oxford Shoes',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '08',
            'quantity' => 2,
            'unit_price' => 1000.00,
            'cost_price' => 500.00,
            'mrp' => 1200.00,
            'subtotal' => 2000.00,
            'taxable_value' => 1800.00,
            'total_tax_amount' => 200.00,
        ]);

        // Return 1 item
        $returnSale = ReturnSale::create([
            'client_return_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'original_invoice_id' => $invoice->id,
            'store_id' => $this->store1->id,
            'return_number' => 'RET-PERF-01',
            'total_refund_amount' => 1000.00,
            'refund_mode' => 'cash',
            'processed_by' => $this->superAdmin->id,
            'created_at' => now(),
        ]);

        ReturnItem::create([
            'return_id' => $returnSale->id,
            'invoice_item_id' => $invItem->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'quantity' => 1,
            'refund_unit_price' => 1000.00,
            'subtotal' => 1000.00,
        ]);

        $res = $this->getJson("/api/v1/stores/{$this->store1->id}/performance?period=this_month");

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'kpis' => [
                        'total_sales' => 2000.00,
                        'returns' => 1000.00,
                        'net_sales' => 1000.00, // 2000 - 1000
                        'cogs' => 500.00, // (2*500) - (1*500)
                        'gross_profit' => 500.00, // 1000 - 500
                    ],
                ],
            ]);
    }

    public function test_operating_expenses_deducted_for_net_profit(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Sales: Gross Profit = 1000
        $invoice = Invoice::create([
            'client_trans_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store1->id,
            'invoice_number' => 'INV-PERF-03',
            'subtotal' => 2000.00,
            'discount_amount' => 0.00,
            'taxable_amount' => 1800.00,
            'total_tax' => 200.00,
            'grand_total' => 2000.00,
            'paid_amount' => 2000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
            'created_at' => now(),
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'sku_snapshot' => $this->variantSize1->sku,
            'article_number_snapshot' => 'ART-101',
            'product_name_snapshot' => 'Leather Oxford Shoes',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '08',
            'quantity' => 2,
            'unit_price' => 1000.00,
            'cost_price' => 500.00,
            'mrp' => 1200.00,
            'subtotal' => 2000.00,
            'taxable_value' => 1800.00,
            'total_tax_amount' => 200.00,
        ]);

        $expCat = \App\Models\ExpenseCategory::create(['name' => 'Utilities']);

        // Operating Expense of 300
        Expense::create([
            'expense_category_id' => $expCat->id,
            'store_id' => $this->store1->id,
            'voucher_number' => 'EXP-PERF-01',
            'amount' => 300.00,
            'expense_date' => now()->toDateString(),
            'created_by' => $this->superAdmin->id,
        ]);

        $res = $this->getJson("/api/v1/stores/{$this->store1->id}/performance?period=this_month");

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'kpis' => [
                        'gross_profit' => 1000.00,
                        'operating_expenses' => 300.00,
                        'net_profit' => 700.00, // 1000 - 300
                    ],
                ],
            ]);
    }

    public function test_empty_date_range_handles_zero_metrics_gracefully(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Query future period with no transactions
        $res = $this->getJson("/api/v1/stores/{$this->store1->id}/performance?date_from=2030-01-01&date_to=2030-01-31");

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'kpis' => [
                        'total_sales' => 0.00,
                        'net_sales' => 0.00,
                        'total_orders' => 0,
                        'items_sold' => 0,
                        'gross_profit' => 0.00,
                        'net_profit' => 0.00,
                    ],
                    'top_products' => [],
                ],
            ]);
    }
}
