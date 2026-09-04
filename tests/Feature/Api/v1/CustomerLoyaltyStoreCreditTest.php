<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\HsnCode;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyRule;
use App\Models\LoyaltyTransaction;
use App\Models\Permission;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\Role;
use App\Models\Size;
use App\Models\Store;
use App\Models\StoreCreditAccount;
use App\Models\StoreCreditTransaction;
use App\Models\User;
use App\Services\LoyaltyService;
use App\Services\StoreCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerLoyaltyStoreCreditTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $cashier;
    protected User $noPermUser;
    protected Store $store1;
    protected Store $store2;
    protected PosSession $openSession1;
    protected Customer $customer;
    protected ProductVariantSize $variantSize1;

    protected function setUp(): void
    {
        parent::setUp();

        $permView = Permission::create(['name' => 'products.view', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Products']);
        $permCreate = Permission::create(['name' => 'products.create', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Create Products']);
        $permEdit = Permission::create(['name' => 'products.edit', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Edit Products']);

        $superAdminRole = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdminRole->permissions()->attach([$permView->id, $permCreate->id, $permEdit->id]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->superAdmin->roles()->attach($superAdminRole->id, ['model_type' => User::class]);

        $cashierRole = Role::create(['name' => 'Cashier', 'guard_name' => 'web']);
        $cashierRole->permissions()->attach([$permView->id, $permCreate->id, $permEdit->id]);

        $this->store1 = Store::create(['code' => 'ST-001', 'name' => 'Main Store', 'is_active' => true]);
        $this->store2 = Store::create(['code' => 'ST-002', 'name' => 'Branch Store', 'is_active' => true]);

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
            'mobile_number' => '9876543210',
            'name' => 'Alice Customer',
            'email' => 'alice@example.com',
        ]);

        $this->openSession1 = PosSession::create([
            'store_id' => $this->store1->id,
            'user_id' => $this->cashier->id,
            'opened_at' => now(),
            'opening_cash' => 1000.00,
            'closing_cash_system' => 1000.00,
            'status' => 'open',
        ]);

        // Setup Master Footwear Products
        $hsn = HsnCode::create(['code' => '6403', 'description' => 'Footwear', 'default_gst_rate' => 12.00]);
        $brand = Brand::create(['name' => 'Nike', 'slug' => 'nike']);
        $cat = Category::create(['name' => 'Casual', 'slug' => 'casual', 'hsn_code_id' => $hsn->id]);
        $product = Product::create(['article_number' => 'ART900', 'name' => 'Men Casual Sneaker', 'slug' => 'men-casual-sneaker', 'brand_id' => $brand->id, 'category_id' => $cat->id, 'hsn_code_id' => $hsn->id]);
        $color = Color::create(['name' => 'Black', 'code' => 'BLK']);
        $variant = ProductVariant::create(['product_id' => $product->id, 'color_id' => $color->id]);
        $size8 = Size::create(['size_number' => '08']);

        $this->variantSize1 = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $size8->id,
            'sku' => 'ART900-BLK-08',
            'cost_price' => 500.00,
            'mrp' => 1200.00,
            'selling_price' => 1000.00,
        ]);

        InventoryStock::create([
            'product_variant_size_id' => $this->variantSize1->id,
            'store_id' => $this->store1->id,
            'warehouse_id' => 0,
            'stock_location_id' => 0,
            'stock_quantity' => 50,
        ]);

        // Standard Loyalty Rule (₹100 = 1 point)
        LoyaltyRule::create([
            'rule_name' => 'Standard Footwear Loyalty',
            'earn_rate_amount' => 100.00,
            'earn_points' => 1,
            'redeem_point_value' => 1.00,
            'min_qualifying_amount' => 0.00,
            'min_redemption_points' => 10,
            'is_active' => true,
        ]);
    }

    public function test_1_unauthenticated_access_rejected(): void
    {
        $this->getJson("/api/v1/customers/{$this->customer->id}/loyalty")->assertStatus(401);
        $this->getJson("/api/v1/customers/{$this->customer->id}/loyalty/transactions")->assertStatus(401);
        $this->getJson("/api/v1/customers/{$this->customer->id}/store-credit")->assertStatus(401);
        $this->postJson("/api/v1/customers/{$this->customer->id}/store-credit", [])->assertStatus(401);
    }

    public function test_2_rbac_permission_rejection(): void
    {
        Sanctum::actingAs($this->noPermUser);

        $this->getJson("/api/v1/customers/{$this->customer->id}/loyalty")->assertStatus(403);
        $this->getJson("/api/v1/customers/{$this->customer->id}/store-credit")->assertStatus(403);
        $this->postJson("/api/v1/customers/{$this->customer->id}/store-credit", ['amount' => 100])->assertStatus(403);
    }

    public function test_3_customer_loyalty_account_creation_retrieval(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/loyalty");

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'customer_id' => $this->customer->id,
                    'available_points' => 0,
                    'lifetime_earned_points' => 0,
                    'status' => 'active',
                ],
            ]);
    }

    public function test_4_loyalty_account_balance_accuracy(): void
    {
        Sanctum::actingAs($this->cashier);

        $service = app(LoyaltyService::class);
        $acc = $service->getOrCreateAccount($this->customer);
        $acc->update([
            'available_points' => 50,
            'lifetime_earned_points' => 100,
            'lifetime_redeemed_points' => 50,
        ]);

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/loyalty");

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'available_points' => 50,
                    'lifetime_earned_points' => 100,
                    'lifetime_redeemed_points' => 50,
                ],
            ]);
    }

    public function test_5_loyalty_earning_calculation(): void
    {
        Sanctum::actingAs($this->cashier);

        $inv = Invoice::create([
            'invoice_number' => 'INV-LY-001',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 2000.00,
            'grand_total' => 2000.00,
            'paid_amount' => 2000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        $service = app(LoyaltyService::class);
        $trans = $service->earnPoints($this->customer, $inv, $this->cashier);

        $this->assertNotNull($trans);
        $this->assertEquals(20, $trans->points);

        $acc = LoyaltyAccount::where('customer_id', $this->customer->id)->first();
        $this->assertEquals(20, $acc->available_points);
    }

    public function test_6_loyalty_earning_transaction_ledger(): void
    {
        Sanctum::actingAs($this->cashier);

        $inv = Invoice::create([
            'invoice_number' => 'INV-LY-002',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        app(LoyaltyService::class)->earnPoints($this->customer, $inv, $this->cashier);

        $this->assertDatabaseHas('loyalty_transactions', [
            'customer_id' => $this->customer->id,
            'transaction_type' => 'earn',
            'points' => 10,
            'reference_type' => 'invoice',
            'reference_id' => $inv->id,
        ]);
    }

    public function test_7_duplicate_earning_prevention(): void
    {
        Sanctum::actingAs($this->cashier);

        $inv = Invoice::create([
            'invoice_number' => 'INV-LY-DUP',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        $service = app(LoyaltyService::class);
        $trans1 = $service->earnPoints($this->customer, $inv, $this->cashier);
        $trans2 = $service->earnPoints($this->customer, $inv, $this->cashier);

        $this->assertEquals($trans1->id, $trans2->id);
        $acc = LoyaltyAccount::where('customer_id', $this->customer->id)->first();
        $this->assertEquals(10, $acc->available_points);
    }

    public function test_8_loyalty_redemption_success(): void
    {
        Sanctum::actingAs($this->cashier);

        // Seed 50 points
        $service = app(LoyaltyService::class);
        $acc = $service->getOrCreateAccount($this->customer);
        $acc->update(['available_points' => 50, 'lifetime_earned_points' => 50]);

        $inv = Invoice::create([
            'invoice_number' => 'INV-RDM-001',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 500.00,
            'grand_total' => 500.00,
            'paid_amount' => 500.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        $res = $this->postJson("/api/v1/pos/sales/{$inv->id}/loyalty/redeem", [
            'points' => 20,
            'notes' => 'Redeemed 20 points',
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'discount_value' => 20.00,
                ],
            ]);

        $acc->refresh();
        $this->assertEquals(30, $acc->available_points);
    }

    public function test_9_insufficient_points_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        $inv = Invoice::create([
            'invoice_number' => 'INV-RDM-LOW',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 500.00,
            'grand_total' => 500.00,
            'paid_amount' => 500.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        $res = $this->postJson("/api/v1/pos/sales/{$inv->id}/loyalty/redeem", [
            'points' => 500, // Available is 0
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_10_invalid_redemption_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        $inv = Invoice::create([
            'invoice_number' => 'INV-RDM-MIN',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 500.00,
            'grand_total' => 500.00,
            'paid_amount' => 500.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        // Below min redemption threshold (10 points)
        $res = $this->postJson("/api/v1/pos/sales/{$inv->id}/loyalty/redeem", [
            'points' => 5,
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_11_duplicate_redemption_prevention(): void
    {
        Sanctum::actingAs($this->cashier);

        $service = app(LoyaltyService::class);
        $acc = $service->getOrCreateAccount($this->customer);
        $acc->update(['available_points' => 100, 'lifetime_earned_points' => 100]);

        $inv = Invoice::create([
            'invoice_number' => 'INV-RDM-DUP',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 500.00,
            'grand_total' => 500.00,
            'paid_amount' => 500.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        $service->redeemPoints($this->customer, 20, $inv, $this->cashier);
        $service->redeemPoints($this->customer, 20, $inv, $this->cashier);

        $acc->refresh();
        $this->assertEquals(80, $acc->available_points);
    }

    public function test_12_concurrent_redemption_protection(): void
    {
        Sanctum::actingAs($this->cashier);

        $service = app(LoyaltyService::class);
        $acc = $service->getOrCreateAccount($this->customer);
        $acc->update(['available_points' => 20]);

        $inv = Invoice::create([
            'invoice_number' => 'INV-CONC-RDM',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 500.00,
            'grand_total' => 500.00,
            'paid_amount' => 500.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        // First redemption succeeds (uses 20 points)
        $this->postJson("/api/v1/pos/sales/{$inv->id}/loyalty/redeem", ['points' => 20])->assertStatus(200);

        // Second concurrent redemption fails with 422 (0 points remaining)
        $this->postJson("/api/v1/pos/sales/{$inv->id}/loyalty/redeem", ['points' => 20])->assertStatus(422);
    }

    public function test_13_store_credit_account_retrieval(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/store-credit");

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'customer_id' => $this->customer->id,
                    'current_balance' => 0.00,
                    'status' => 'active',
                ],
            ]);
    }

    public function test_14_store_credit_issue_adjustment(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->postJson("/api/v1/customers/{$this->customer->id}/store-credit", [
            'amount' => 500.00,
            'store_id' => $this->store1->id,
            'notes' => 'Customer goodwill credit',
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'amount' => 500.00,
                    'balance_after' => 500.00,
                ],
            ]);

        $acc = StoreCreditAccount::where('customer_id', $this->customer->id)->first();
        $this->assertEquals(500.00, (float) $acc->current_balance);
    }

    public function test_15_store_credit_payment_usage(): void
    {
        Sanctum::actingAs($this->cashier);

        // Seed Store Credit 1000.00
        app(StoreCreditService::class)->issueOrAdjustCredit(
            $this->customer,
            1000.00,
            'issue_adjustment',
            $this->cashier,
            $this->store1->id
        );

        $uuid = (string) Str::uuid();

        // POS Sale paid via store_credit
        $res = $this->postJson('/api/v1/pos/sales', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'customer_id' => $this->customer->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
            'payment_method' => 'store_credit',
        ]);

        $res->assertStatus(201);

        $acc = StoreCreditAccount::where('customer_id', $this->customer->id)->first();
        $this->assertEquals(0.00, (float) $acc->current_balance); // 1000 - 1000 = 0
    }

    public function test_16_insufficient_store_credit_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        // Balance is 0
        $res = $this->postJson('/api/v1/pos/sales', [
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'customer_id' => $this->customer->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
            'payment_method' => 'store_credit',
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_17_negative_balance_prevention(): void
    {
        Sanctum::actingAs($this->cashier);

        $service = app(StoreCreditService::class);
        $service->issueOrAdjustCredit($this->customer, 100.00);

        $this->expectException(\RuntimeException::class);
        $service->issueOrAdjustCredit($this->customer, -200.00);
    }

    public function test_18_store_credit_ledger_accuracy(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $service = app(StoreCreditService::class);
        $service->issueOrAdjustCredit($this->customer, 300.00, 'issue_adjustment', $this->superAdmin, $this->store1->id);
        $service->issueOrAdjustCredit($this->customer, -100.00, 'payment_used', $this->superAdmin, $this->store1->id);

        $this->assertDatabaseHas('store_credit_transactions', [
            'customer_id' => $this->customer->id,
            'transaction_type' => 'payment_used',
            'amount' => -100.00,
            'balance_before' => 300.00,
            'balance_after' => 200.00,
        ]);
    }

    public function test_19_duplicate_store_credit_refund_prevention(): void
    {
        Sanctum::actingAs($this->cashier);

        $service = app(StoreCreditService::class);
        $uuid = (string) Str::uuid();

        $t1 = $service->issueOrAdjustCredit($this->customer, 200.00, 'issue_refund', $this->cashier, $this->store1->id, 'return', 10, $uuid);
        $t2 = $service->issueOrAdjustCredit($this->customer, 200.00, 'issue_refund', $this->cashier, $this->store1->id, 'return', 10, $uuid);

        $this->assertEquals($t1->id, $t2->id);

        $acc = StoreCreditAccount::where('customer_id', $this->customer->id)->first();
        $this->assertEquals(200.00, (float) $acc->current_balance);
    }

    public function test_20_return_store_credit_integration(): void
    {
        Sanctum::actingAs($this->cashier);

        $service = app(StoreCreditService::class);
        $trans = $service->issueOrAdjustCredit(
            $this->customer,
            250.00,
            'issue_refund',
            $this->cashier,
            $this->store1->id,
            'return',
            99
        );

        $this->assertEquals(250.00, (float) $trans->balance_after);
    }

    public function test_21_exchange_integration(): void
    {
        Sanctum::actingAs($this->cashier);

        $service = app(StoreCreditService::class);
        $service->issueOrAdjustCredit($this->customer, 500.00);

        // Exchange credit adjustment
        $trans = $service->issueOrAdjustCredit(
            $this->customer,
            -150.00,
            'payment_used',
            $this->cashier,
            $this->store1->id,
            'exchange',
            55
        );

        $this->assertEquals(350.00, (float) $trans->balance_after);
    }

    public function test_22_pos_billing_integration(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();

        $res = $this->postJson('/api/v1/pos/sales', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'customer_id' => $this->customer->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ]);

        $res->assertStatus(201);

        // Verify points earned
        $acc = LoyaltyAccount::where('customer_id', $this->customer->id)->first();
        $this->assertEquals(10, $acc->available_points);
    }

    public function test_23_split_payment_integration(): void
    {
        Sanctum::actingAs($this->cashier);

        app(StoreCreditService::class)->issueOrAdjustCredit($this->customer, 500.00);

        $uuid = (string) Str::uuid();

        // Split payment: 500 store_credit + 500 cash = 1000 grand total
        $res = $this->postJson('/api/v1/pos/sales', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'customer_id' => $this->customer->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
            'payments' => [
                ['payment_method' => 'store_credit', 'amount' => 500.00],
                ['payment_method' => 'cash', 'amount' => 500.00],
            ],
        ]);

        $res->assertStatus(201);

        $acc = StoreCreditAccount::where('customer_id', $this->customer->id)->first();
        $this->assertEquals(0.00, (float) $acc->current_balance);
    }

    public function test_24_offline_sync_idempotency_compatibility(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();

        $res = $this->postJson('/api/v1/pos/sales/sync', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'customer_id' => $this->customer->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(200)->assertJsonPath('data.results.0.status', 'synced');

        // Retry offline sync
        $resRetry = $this->postJson('/api/v1/pos/sales/sync', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'customer_id' => $this->customer->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ]);

        $resRetry->assertStatus(200)->assertJsonPath('data.results.0.status', 'already_synced');

        // Loyalty points earned once (10 points)
        $acc = LoyaltyAccount::where('customer_id', $this->customer->id)->first();
        $this->assertEquals(10, $acc->available_points);
    }

    public function test_25_unauthorized_store_access_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        // Cashier attempts to issue store credit for store2 (unauthorized)
        $res = $this->postJson("/api/v1/customers/{$this->customer->id}/store-credit", [
            'amount' => 100.00,
            'store_id' => $this->store2->id,
        ]);

        $res->assertStatus(403);
    }

    public function test_26_super_admin_cross_store_access(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->postJson("/api/v1/customers/{$this->customer->id}/store-credit", [
            'amount' => 200.00,
            'store_id' => $this->store2->id,
        ]);

        $res->assertStatus(200)->assertJsonPath('data.amount', 200);
    }

    public function test_27_server_side_calculation_validation(): void
    {
        Sanctum::actingAs($this->cashier);

        $inv = Invoice::create([
            'invoice_number' => 'INV-SRV-001',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1550.00,
            'grand_total' => 1550.00,
            'paid_amount' => 1550.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        // Server calculates floor(1550 / 100) = 15 points
        $trans = app(LoyaltyService::class)->earnPoints($this->customer, $inv, $this->cashier);
        $this->assertEquals(15, $trans->points);
    }

    public function test_28_atomic_rollback_on_failure(): void
    {
        Sanctum::actingAs($this->cashier);

        $service = app(StoreCreditService::class);
        $service->issueOrAdjustCredit($this->customer, 100.00);

        try {
            DB::transaction(function () use ($service) {
                $service->issueOrAdjustCredit($this->customer, -500.00); // Throws exception
            });
        } catch (\Exception $e) {
            // Expected exception
        }

        $acc = StoreCreditAccount::where('customer_id', $this->customer->id)->first();
        $this->assertEquals(100.00, (float) $acc->current_balance);
    }

    public function test_29_transaction_history_filtering_pagination(): void
    {
        Sanctum::actingAs($this->cashier);

        $service = app(StoreCreditService::class);
        $service->issueOrAdjustCredit($this->customer, 100.00, 'issue_adjustment', $this->cashier, $this->store1->id);
        $service->issueOrAdjustCredit($this->customer, 200.00, 'issue_adjustment', $this->cashier, $this->store1->id);

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/store-credit/transactions?per_page=1");

        $res->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.pagination.total', 2);
    }

    public function test_30_standardized_api_responses(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson("/api/v1/customers/{$this->customer->id}/loyalty");

        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);
    }
}
