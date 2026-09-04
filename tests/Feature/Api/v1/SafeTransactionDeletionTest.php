<?php

namespace Tests\Feature\Api\v1;

use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\DayClosing;
use App\Models\HsnCode;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\Permission;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\PurchaseBill;
use App\Models\PurchaseBillItem;
use App\Models\PurchaseReturn;
use App\Models\ReturnSale;
use App\Models\Role;
use App\Models\Size;
use App\Models\Store;
use App\Models\StoreCreditAccount;
use App\Models\StoreCreditTransaction;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SafeTransactionDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $cashier;
    protected User $noPermUser;
    protected Store $store;
    protected Customer $customer;
    protected Supplier $supplier;
    protected ProductVariantSize $variantSize;
    protected InventoryStock $stock;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Core Permissions
        $permView = Permission::create(['name' => 'products.view', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Products']);
        $permCreate = Permission::create(['name' => 'products.create', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Create Products']);
        $permDeleteSales = Permission::create(['name' => 'sales.delete', 'guard_name' => 'web', 'module_group' => 'POS', 'display_name' => 'Delete Sales']);
        $permDeletePurchases = Permission::create(['name' => 'purchases.delete', 'guard_name' => 'web', 'module_group' => 'Procurement', 'display_name' => 'Delete Purchases']);
        $permReports = Permission::create(['name' => 'reports.view', 'guard_name' => 'web', 'module_group' => 'Reports', 'display_name' => 'View Reports']);

        // 2. Roles & Users
        $adminRole = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $adminRole->permissions()->attach([$permView->id, $permCreate->id, $permDeleteSales->id, $permDeletePurchases->id, $permReports->id]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->superAdmin->roles()->attach($adminRole->id, ['model_type' => User::class]);

        $cashierRole = Role::create(['name' => 'Cashier', 'guard_name' => 'web']);
        $cashierRole->permissions()->attach([$permView->id, $permCreate->id, $permReports->id]);

        $this->cashier = User::create([
            'name' => 'Cashier User',
            'username' => 'cashier',
            'email' => 'cashier@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->cashier->roles()->attach($cashierRole->id, ['model_type' => User::class]);

        $this->noPermUser = User::create([
            'name' => 'No Perm User',
            'username' => 'noperm',
            'email' => 'noperm@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        // Store & Master Data
        $this->store = Store::create(['code' => 'ST-001', 'name' => 'Main Outlet', 'is_active' => true]);
        $this->cashier->stores()->attach($this->store->id);
        $this->superAdmin->stores()->attach($this->store->id);

        $hsn = HsnCode::create(['code' => '6403', 'description' => 'Footwear', 'default_gst_rate' => 12.00]);
        $brand = Brand::create(['name' => 'Bata', 'slug' => 'bata']);
        $cat = Category::create(['name' => 'Formal', 'slug' => 'formal', 'hsn_code_id' => $hsn->id]);
        $product = Product::create(['article_number' => 'ART100', 'name' => 'Men Leather Shoe', 'slug' => 'men-leather-shoe', 'brand_id' => $brand->id, 'category_id' => $cat->id, 'hsn_code_id' => $hsn->id]);
        $color = Color::create(['name' => 'Brown', 'code' => 'BRN']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color_id' => $color->id]);
        $size = Size::create(['size_number' => '09']);

        $this->variantSize = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $size->id,
            'sku' => 'ART100-BRN-09',
            'cost_price' => 400.00,
            'mrp' => 1000.00,
            'selling_price' => 800.00,
        ]);

        $this->stock = InventoryStock::create([
            'product_variant_size_id' => $this->variantSize->id,
            'store_id' => $this->store->id,
            'warehouse_id' => 0,
            'stock_location_id' => 0,
            'stock_quantity' => 50,
        ]);

        $this->customer = Customer::create([
            'name' => 'Rahul Das',
            'mobile_number' => '9830098300',
            'reward_points' => 0,
            'total_purchases_count' => 0,
            'total_spent_amount' => 0.00,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'Subhash Footwear Suppliers',
            'company_name' => 'Subhash Footwear Pvt Ltd',
            'phone' => '9831198311',
            'is_active' => true,
        ]);
    }

    public function test_1_authorized_sale_deletion(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $inv = Invoice::create([
            'invoice_number' => 'INV-TEST-001',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 800.00,
            'grand_total' => 800.00,
            'paid_amount' => 800.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv->id,
            'product_variant_size_id' => $this->variantSize->id,
            'article_number_snapshot' => 'ART100',
            'product_name_snapshot' => 'Men Leather Shoe',
            'color_name_snapshot' => 'Brown',
            'size_number_snapshot' => '09',
            'sku_snapshot' => $this->variantSize->sku,
            'quantity' => 2,
            'unit_price' => 400.00,
            'subtotal' => 800.00,
        ]);

        InvoicePayment::create([
            'invoice_id' => $inv->id,
            'payment_method' => 'cash',
            'amount' => 800.00,
            'payment_time' => now(),
        ]);

        $res = $this->deleteJson("/api/v1/pos/sales/{$inv->id}");

        $res->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('invoices', ['id' => $inv->id]);
    }

    public function test_2_unauthorized_sale_deletion_blocked(): void
    {
        Sanctum::actingAs($this->noPermUser);

        $inv = Invoice::create([
            'invoice_number' => 'INV-TEST-002',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'subtotal' => 500.00,
            'grand_total' => 500.00,
            'paid_amount' => 500.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
        ]);

        $res = $this->deleteJson("/api/v1/pos/sales/{$inv->id}");
        $res->assertStatus(403);
    }

    public function test_3_sale_stock_reversal(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $initialStock = $this->stock->stock_quantity; // 50

        // Create sale of 5 items -> stock becomes 45
        $inv = Invoice::create([
            'invoice_number' => 'INV-TEST-003',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'subtotal' => 4000.00,
            'grand_total' => 4000.00,
            'paid_amount' => 4000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv->id,
            'product_variant_size_id' => $this->variantSize->id,
            'article_number_snapshot' => 'ART100',
            'product_name_snapshot' => 'Men Leather Shoe',
            'color_name_snapshot' => 'Brown',
            'size_number_snapshot' => '09',
            'sku_snapshot' => $this->variantSize->sku,
            'quantity' => 5,
            'unit_price' => 800.00,
            'subtotal' => 4000.00,
        ]);

        $this->stock->decrement('stock_quantity', 5);
        $this->assertEquals(45, $this->stock->fresh()->stock_quantity);

        // Delete sale -> stock must return to 50
        $this->deleteJson("/api/v1/pos/sales/{$inv->id}")->assertStatus(200);

        $this->assertEquals(50, $this->stock->fresh()->stock_quantity);
    }

    public function test_4_sale_payment_reversal(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $inv = Invoice::create([
            'invoice_number' => 'INV-TEST-004',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
        ]);

        InvoicePayment::create([
            'invoice_id' => $inv->id,
            'payment_method' => 'cash',
            'amount' => 500.00,
            'payment_time' => now(),
        ]);

        InvoicePayment::create([
            'invoice_id' => $inv->id,
            'payment_method' => 'upi',
            'amount' => 500.00,
            'payment_time' => now(),
        ]);

        $this->deleteJson("/api/v1/pos/sales/{$inv->id}")->assertStatus(200);

        $this->assertDatabaseMissing('invoice_payments', ['invoice_id' => $inv->id]);
    }

    public function test_5_sale_loyalty_and_store_credit_reversal(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $inv = Invoice::create([
            'invoice_number' => 'INV-TEST-005',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
        ]);

        // Customer earned 10 loyalty points
        $account = LoyaltyAccount::create([
            'customer_id' => $this->customer->id,
            'available_points' => 10,
            'lifetime_earned_points' => 10,
            'lifetime_redeemed_points' => 0,
            'status' => 'active',
        ]);

        $this->customer->update(['reward_points' => 10, 'total_purchases_count' => 1, 'total_spent_amount' => 1000.00]);

        LoyaltyTransaction::create([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'transaction_type' => 'earn',
            'points' => 10,
            'points_balance_before' => 0,
            'points_balance_after' => 10,
            'reference_type' => 'invoice',
            'reference_id' => $inv->id,
            'performed_by' => $this->superAdmin->id,
        ]);

        $this->deleteJson("/api/v1/pos/sales/{$inv->id}")->assertStatus(200);

        // Loyalty points should be reversed back to 0
        $this->assertEquals(0, $account->fresh()->available_points);
        $this->assertEquals(0, $this->customer->fresh()->reward_points);
        $this->assertEquals(0, $this->customer->fresh()->total_purchases_count);
    }

    public function test_6_sale_deletion_blocked_when_dependent_return_exists(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $inv = Invoice::create([
            'invoice_number' => 'INV-TEST-006',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
        ]);

        ReturnSale::create([
            'return_number' => 'RET-TEST-001',
            'client_return_uuid' => (string) Str::uuid(),
            'original_invoice_id' => $inv->id,
            'store_id' => $this->store->id,
            'total_refund_amount' => 200.00,
            'refund_status' => 'completed',
            'processed_by' => $this->superAdmin->id,
        ]);

        $res = $this->deleteJson("/api/v1/pos/sales/{$inv->id}");
        $res->assertStatus(422)
            ->assertJsonPath('message', 'This sale has return/refund transactions and cannot be deleted. Reverse/cancel the return first.');
    }

    public function test_7_sale_deletion_blocked_by_day_closing_rules(): void
    {
        // Give cashier sales.delete permission, but NOT day_closing.reopen
        $perm = Permission::where('name', 'sales.delete')->first();
        $this->cashier->roles->first()->permissions()->attach($perm->id);

        Sanctum::actingAs($this->cashier);

        $dateStr = '2026-08-01';

        $inv = Invoice::create([
            'invoice_number' => 'INV-TEST-007',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'subtotal' => 500.00,
            'grand_total' => 500.00,
            'paid_amount' => 500.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        // Explicitly update created_at in DB
        DB::table('invoices')->where('id', $inv->id)->update(['created_at' => "{$dateStr} 12:00:00"]);

        DayClosing::create([
            'closing_number' => 'DC-001',
            'store_id' => $this->store->id,
            'closing_date' => $dateStr,
            'status' => 'closed',
            'closed_by' => $this->superAdmin->id,
        ]);

        // Cashier attempts to delete sale from a closed day -> blocked 422
        $res = $this->deleteJson("/api/v1/pos/sales/{$inv->id}");
        $res->assertStatus(422);
    }

    public function test_8_authorized_purchase_deletion(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $bill = PurchaseBill::create([
            'bill_number' => 'BILL-TEST-001',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'bill_date' => now()->toDateString(),
            'subtotal' => 2000.00,
            'grand_total' => 2000.00,
            'paid_amount' => 0.00,
            'due_amount' => 2000.00,
            'payment_status' => 'unpaid',
            'created_by' => $this->superAdmin->id,
        ]);

        PurchaseBillItem::create([
            'purchase_bill_id' => $bill->id,
            'product_variant_size_id' => $this->variantSize->id,
            'quantity' => 5,
            'cost_price' => 400.00,
            'total_cost' => 2000.00,
        ]);

        $res = $this->deleteJson("/api/v1/purchases/bills/{$bill->id}");
        $res->assertStatus(200)->assertJson(['success' => true]);

        $this->assertDatabaseMissing('purchase_bills', ['id' => $bill->id]);
    }

    public function test_9_unauthorized_purchase_deletion_blocked(): void
    {
        Sanctum::actingAs($this->noPermUser);

        $bill = PurchaseBill::create([
            'bill_number' => 'BILL-TEST-002',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'bill_date' => now()->toDateString(),
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 0.00,
            'due_amount' => 1000.00,
            'payment_status' => 'unpaid',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->deleteJson("/api/v1/purchases/bills/{$bill->id}")->assertStatus(403);
    }

    public function test_10_purchase_stock_reversal(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $initialStock = $this->stock->stock_quantity; // 50

        // Create purchase bill that added 10 items to stock -> stock = 60
        $bill = PurchaseBill::create([
            'bill_number' => 'BILL-TEST-010',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'bill_date' => now()->toDateString(),
            'subtotal' => 4000.00,
            'grand_total' => 4000.00,
            'paid_amount' => 0.00,
            'due_amount' => 4000.00,
            'payment_status' => 'unpaid',
            'created_by' => $this->superAdmin->id,
        ]);

        PurchaseBillItem::create([
            'purchase_bill_id' => $bill->id,
            'product_variant_size_id' => $this->variantSize->id,
            'quantity' => 10,
            'cost_price' => 400.00,
            'total_cost' => 4000.00,
        ]);

        $this->stock->increment('stock_quantity', 10);
        $this->assertEquals(60, $this->stock->fresh()->stock_quantity);

        // Delete purchase bill -> stock must deduct 10 and return to 50
        $this->deleteJson("/api/v1/purchases/bills/{$bill->id}")->assertStatus(200);

        $this->assertEquals(50, $this->stock->fresh()->stock_quantity);
    }

    public function test_11_purchase_deletion_blocked_when_stock_consumed(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Create purchase bill for 20 items
        $bill = PurchaseBill::create([
            'bill_number' => 'BILL-TEST-011',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'bill_date' => now()->toDateString(),
            'subtotal' => 8000.00,
            'grand_total' => 8000.00,
            'paid_amount' => 0.00,
            'due_amount' => 8000.00,
            'payment_status' => 'unpaid',
            'created_by' => $this->superAdmin->id,
        ]);

        PurchaseBillItem::create([
            'purchase_bill_id' => $bill->id,
            'product_variant_size_id' => $this->variantSize->id,
            'quantity' => 20,
            'cost_price' => 400.00,
            'total_cost' => 8000.00,
        ]);

        // Reduce available physical stock to 5 (e.g. 15 consumed/sold)
        $this->stock->update(['stock_quantity' => 5]);

        // Attempting to delete purchase bill of 20 items must be BLOCKED (422)
        $res = $this->deleteJson("/api/v1/purchases/bills/{$bill->id}");
        $res->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_12_purchase_deletion_blocked_when_purchase_return_exists(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $bill = PurchaseBill::create([
            'bill_number' => 'BILL-TEST-012',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'bill_date' => now()->toDateString(),
            'subtotal' => 2000.00,
            'grand_total' => 2000.00,
            'paid_amount' => 0.00,
            'due_amount' => 2000.00,
            'payment_status' => 'unpaid',
            'created_by' => $this->superAdmin->id,
        ]);

        PurchaseReturn::create([
            'return_number' => 'PR-TEST-001',
            'purchase_bill_id' => $bill->id,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'total_return_amount' => 400.00,
            'processed_by' => $this->superAdmin->id,
        ]);

        $res = $this->deleteJson("/api/v1/purchases/bills/{$bill->id}");
        $res->assertStatus(422)
            ->assertJsonPath('message', 'This purchase bill has an associated purchase return and cannot be deleted. Reverse/cancel the purchase return first.');
    }

    public function test_13_supplier_balance_and_payment_reversal(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $bill = PurchaseBill::create([
            'bill_number' => 'BILL-TEST-013',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'bill_date' => now()->toDateString(),
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'due_amount' => 0.00,
            'payment_status' => 'paid',
            'created_by' => $this->superAdmin->id,
        ]);

        SupplierPayment::create([
            'payment_number' => 'SP-TEST-001',
            'supplier_id' => $this->supplier->id,
            'purchase_bill_id' => $bill->id,
            'amount' => 1000.00,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
            'recorded_by' => $this->superAdmin->id,
        ]);

        $this->deleteJson("/api/v1/purchases/bills/{$bill->id}")->assertStatus(200);

        $this->assertDatabaseMissing('supplier_payments', ['purchase_bill_id' => $bill->id]);
    }

    public function test_14_reports_reconciliation_after_deletion(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $today = now()->format('Y-m-d');

        // Check sales before
        $salesBefore = $this->getJson("/api/v1/reports/sales-summary?date_from={$today}&date_to={$today}")->json('data.total_grand_total');

        // Create sale
        $inv = Invoice::create([
            'invoice_number' => 'INV-TEST-014',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'subtotal' => 1500.00,
            'grand_total' => 1500.00,
            'paid_amount' => 1500.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $inv->id,
            'product_variant_size_id' => $this->variantSize->id,
            'article_number_snapshot' => 'ART100',
            'product_name_snapshot' => 'Men Leather Shoe',
            'color_name_snapshot' => 'Brown',
            'size_number_snapshot' => '09',
            'sku_snapshot' => $this->variantSize->sku,
            'quantity' => 1,
            'unit_price' => 1500.00,
            'subtotal' => 1500.00,
        ]);

        $salesDuring = $this->getJson("/api/v1/reports/sales-summary?date_from={$today}&date_to={$today}")->json('data.total_grand_total');
        $this->assertEquals($salesBefore + 1500.00, $salesDuring);

        // Delete sale
        $this->deleteJson("/api/v1/pos/sales/{$inv->id}")->assertStatus(200);

        // Verify report returns to previous total
        $salesAfter = $this->getJson("/api/v1/reports/sales-summary?date_from={$today}&date_to={$today}")->json('data.total_grand_total');
        $this->assertEquals($salesBefore, $salesAfter);
    }

    public function test_15_repeated_deletion_protection_and_idempotency(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $inv = Invoice::create([
            'invoice_number' => 'INV-TEST-015',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'subtotal' => 600.00,
            'grand_total' => 600.00,
            'paid_amount' => 600.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
        ]);

        // First deletion succeeds
        $this->deleteJson("/api/v1/pos/sales/{$inv->id}")->assertStatus(200);

        // Subsequent deletion returns 404
        $this->deleteJson("/api/v1/pos/sales/{$inv->id}")->assertStatus(404);
    }

    public function test_16_audit_log_creation(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $inv = Invoice::create([
            'invoice_number' => 'INV-TEST-016',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'subtotal' => 700.00,
            'grand_total' => 700.00,
            'paid_amount' => 700.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->deleteJson("/api/v1/pos/sales/{$inv->id}")->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'invoice_deleted',
            'auditable_type' => Invoice::class,
            'auditable_id' => $inv->id,
        ]);
    }
}
