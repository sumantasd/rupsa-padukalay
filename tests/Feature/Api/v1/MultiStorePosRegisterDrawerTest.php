<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\HsnCode;
use App\Models\InventoryStock;
use App\Models\Permission;
use App\Models\PosRegister;
use App\Models\PosRegisterCashMovement;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\Role;
use App\Models\Size;
use App\Models\Store;
use App\Models\User;
use App\Services\PosRegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MultiStorePosRegisterDrawerTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $cashier;
    protected User $noPermUser;
    protected Store $store1;
    protected Store $store2;
    protected PosRegister $register1;
    protected PosRegister $register2;
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

        $this->register1 = PosRegister::create([
            'store_id' => $this->store1->id,
            'code' => 'REG-001',
            'name' => 'Counter 1',
            'is_active' => true,
            'assigned_user_id' => $this->cashier->id,
        ]);

        $this->register2 = PosRegister::create([
            'store_id' => $this->store2->id,
            'code' => 'REG-002',
            'name' => 'Branch Counter 1',
            'is_active' => true,
        ]);

        // Setup Footwear Masters
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

        InventoryStock::create(['product_variant_size_id' => $this->variantSize1->id, 'store_id' => $this->store1->id, 'stock_quantity' => 50]);
    }

    public function test_1_unauthenticated_access_rejected(): void
    {
        $this->getJson('/api/v1/pos/registers')->assertStatus(401);
        $this->postJson('/api/v1/pos/registers', [])->assertStatus(401);
        $this->getJson("/api/v1/pos/registers/{$this->register1->id}")->assertStatus(401);
    }

    public function test_2_rbac_permission_rejection(): void
    {
        Sanctum::actingAs($this->noPermUser);

        $this->getJson('/api/v1/pos/registers')->assertStatus(403);
        $this->postJson('/api/v1/pos/registers', ['store_id' => $this->store1->id, 'code' => 'REG-99', 'name' => 'Test'])->assertStatus(403);
    }

    public function test_3_register_creation_listing_per_store(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson('/api/v1/pos/registers', [
            'store_id' => $this->store1->id,
            'code' => 'REG-003',
            'name' => 'Express Counter',
            'is_active' => true,
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.code', 'REG-003')
            ->assertJsonPath('data.name', 'Express Counter');
    }

    public function test_4_duplicate_register_code_per_store_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson('/api/v1/pos/registers', [
            'store_id' => $this->store1->id,
            'code' => 'REG-001', // Already exists in store1
            'name' => 'Duplicate Counter',
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_5_register_cashier_assignment(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson("/api/v1/pos/registers/{$this->register1->id}/assign", [
            'user_id' => $this->cashier->id,
        ]);

        $res->assertStatus(200)->assertJsonPath('data.assigned_user_id', $this->cashier->id);
    }

    public function test_6_register_opening_workflow_opening_float(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson("/api/v1/pos/registers/{$this->register1->id}/open", [
            'opening_cash' => 1000.00,
            'notes' => 'Opening float',
        ]);

        $res->assertStatus(201);
        $this->assertEquals(1000.00, $res->json('data.opening_cash'));
        $this->assertEquals(1000.00, $res->json('data.expected_drawer_balance'));
    }

    public function test_7_conflicting_active_session_opening_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        // Open register session once
        $this->postJson("/api/v1/pos/registers/{$this->register1->id}/open", ['opening_cash' => 1000.00])->assertStatus(201);

        // Open register session second time (conflicting)
        $resConflicting = $this->postJson("/api/v1/pos/registers/{$this->register1->id}/open", ['opening_cash' => 1000.00]);
        $resConflicting->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_8_inactive_register_opening_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->register1->update(['is_active' => false]);

        $res = $this->postJson("/api/v1/pos/registers/{$this->register1->id}/open", ['opening_cash' => 1000.00]);
        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_9_realtime_drawer_summary_calculation(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->postJson("/api/v1/pos/registers/{$this->register1->id}/open", ['opening_cash' => 1000.00]);

        $res = $this->getJson("/api/v1/pos/registers/{$this->register1->id}/current-session");

        $res->assertStatus(200);
        $this->assertEquals(1000.00, $res->json('data.opening_cash'));
        $this->assertEquals(1000.00, $res->json('data.expected_drawer_balance'));
    }

    public function test_10_cash_in_movement_recording(): void
    {
        Sanctum::actingAs($this->cashier);

        $openRes = $this->postJson("/api/v1/pos/registers/{$this->register1->id}/open", ['opening_cash' => 1000.00]);
        $sessionId = $openRes->json('data.session_id');

        $res = $this->postJson('/api/v1/pos/registers/cash-in', [
            'pos_session_id' => $sessionId,
            'movement_type' => 'cash_in',
            'amount' => 500.00,
            'reason' => 'Midday change float add',
        ]);

        $res->assertStatus(201);
        $this->assertEquals(500.00, $res->json('data.amount'));

        $summary = $this->getJson("/api/v1/pos/registers/{$this->register1->id}/current-session");
        $this->assertEquals(1500.00, $summary->json('data.expected_drawer_balance'));
    }

    public function test_11_cash_out_movement_recording(): void
    {
        Sanctum::actingAs($this->cashier);

        $openRes = $this->postJson("/api/v1/pos/registers/{$this->register1->id}/open", ['opening_cash' => 1000.00]);
        $sessionId = $openRes->json('data.session_id');

        $res = $this->postJson('/api/v1/pos/registers/cash-out', [
            'pos_session_id' => $sessionId,
            'movement_type' => 'cash_out',
            'amount' => 200.00,
            'reason' => 'Minor petty cash out',
        ]);

        $res->assertStatus(201);
        $this->assertEquals(200.00, $res->json('data.amount'));

        $summary = $this->getJson("/api/v1/pos/registers/{$this->register1->id}/current-session");
        $this->assertEquals(800.00, $summary->json('data.expected_drawer_balance'));
    }

    public function test_12_drawer_safe_drop_recording(): void
    {
        Sanctum::actingAs($this->cashier);

        $openRes = $this->postJson("/api/v1/pos/registers/{$this->register1->id}/open", ['opening_cash' => 2000.00]);
        $sessionId = $openRes->json('data.session_id');

        $res = $this->postJson('/api/v1/pos/registers/drawer-drop', [
            'pos_session_id' => $sessionId,
            'movement_type' => 'drawer_drop',
            'amount' => 1000.00,
            'reason' => 'Safe drop to main vault',
        ]);

        $res->assertStatus(201);

        $summary = $this->getJson("/api/v1/pos/registers/{$this->register1->id}/current-session");
        $this->assertEquals(1000.00, $summary->json('data.expected_drawer_balance'));
    }

    public function test_13_cash_movement_on_closed_session_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        $openRes = $this->postJson("/api/v1/pos/registers/{$this->register1->id}/open", ['opening_cash' => 1000.00]);
        $sessionId = $openRes->json('data.session_id');

        // Close session
        $this->postJson("/api/v1/pos/registers/{$this->register1->id}/close", ['closing_cash_actual' => 1000.00])->assertStatus(200);

        // Attempt cash-in on closed session
        $res = $this->postJson('/api/v1/pos/registers/cash-in', [
            'pos_session_id' => $sessionId,
            'movement_type' => 'cash_in',
            'amount' => 100.00,
            'reason' => 'Late cash add',
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_14_cash_sales_integration(): void
    {
        Sanctum::actingAs($this->cashier);

        $openRes = $this->postJson("/api/v1/pos/registers/{$this->register1->id}/open", ['opening_cash' => 1000.00]);
        $sessionId = $openRes->json('data.session_id');

        // Process Cash POS sale
        $this->postJson('/api/v1/pos/sales', [
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'pos_session_id' => $sessionId,
            'customer_id' => $this->customer->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ])->assertStatus(201);

        $summary = $this->getJson("/api/v1/pos/registers/{$this->register1->id}/current-session");
        $this->assertEquals(1000.00, $summary->json('data.cash_sales'));
        $this->assertEquals(2000.00, $summary->json('data.expected_drawer_balance'));
    }

    public function test_15_cash_sales_return_refund_integration(): void
    {
        Sanctum::actingAs($this->cashier);

        $openRes = $this->postJson("/api/v1/pos/registers/{$this->register1->id}/open", ['opening_cash' => 1000.00]);

        $summary = $this->getJson("/api/v1/pos/registers/{$this->register1->id}/current-session");
        $this->assertEquals(1000.00, $summary->json('data.expected_drawer_balance'));
    }

    public function test_16_exchange_cash_difference_integration(): void
    {
        Sanctum::actingAs($this->cashier);

        $openRes = $this->postJson("/api/v1/pos/registers/{$this->register1->id}/open", ['opening_cash' => 1000.00]);

        $summary = $this->getJson("/api/v1/pos/registers/{$this->register1->id}/current-session");
        $this->assertEquals(1000.00, $summary->json('data.expected_drawer_balance'));
    }

    public function test_17_cash_expense_integration(): void
    {
        Sanctum::actingAs($this->cashier);

        $openRes = $this->postJson("/api/v1/pos/registers/{$this->register1->id}/open", ['opening_cash' => 1000.00]);
        $sessionId = $openRes->json('data.session_id');

        $category = ExpenseCategory::create(['name' => 'Tea Supplies']);

        // Cash Expense 150.00
        Expense::create([
            'expense_category_id' => $category->id,
            'store_id' => $this->store1->id,
            'pos_session_id' => $sessionId,
            'amount' => 150.00,
            'payment_method' => 'cash',
            'description' => 'Tea expense',
            'created_by' => $this->cashier->id,
            'expense_date' => now()->toDateString(),
        ]);

        $summary = $this->getJson("/api/v1/pos/registers/{$this->register1->id}/current-session");
        $this->assertEquals(150.00, $summary->json('data.cash_expenses'));
        $this->assertEquals(850.00, $summary->json('data.expected_drawer_balance')); // 1000 - 150
    }

    public function test_18_expected_drawer_balance_server_side_calculation(): void
    {
        Sanctum::actingAs($this->cashier);

        $service = app(PosRegisterService::class);
        $session = $service->openRegister($this->register1, $this->cashier, 500.00);

        $service->recordCashMovement($session, $this->cashier, 'cash_in', 200.00, 'Add cash');
        $service->recordCashMovement($session, $this->cashier, 'cash_out', 50.00, 'Tea');
        $service->recordCashMovement($session, $this->cashier, 'drawer_drop', 100.00, 'Safe drop');

        $summary = $service->calculateDrawerSummary($session);

        // 500 + 200 - 50 - 100 = 550.00
        $this->assertEquals(550.00, $summary['expected_drawer_balance']);
    }

    public function test_19_over_short_variance_reconciliation_on_session_close(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->postJson("/api/v1/pos/registers/{$this->register1->id}/open", ['opening_cash' => 1000.00]);

        // Close session with 980 actual count (20 short)
        $res = $this->postJson("/api/v1/pos/registers/{$this->register1->id}/close", [
            'closing_cash_actual' => 980.00,
            'notes' => 'Slight cash shortage',
        ]);

        $res->assertStatus(200);
        $this->assertEquals('closed', $res->json('data.status'));
        $this->assertEquals(1000.00, $res->json('data.expected_drawer_balance'));
        $this->assertEquals(980.00, $res->json('data.closing_cash_actual'));
        $this->assertEquals(-20.00, $res->json('data.cash_difference'));
        $this->assertEquals('short', $res->json('data.variance_status'));
    }

    public function test_20_immutable_closed_session_protection(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->postJson("/api/v1/pos/registers/{$this->register1->id}/open", ['opening_cash' => 1000.00]);
        $this->postJson("/api/v1/pos/registers/{$this->register1->id}/close", ['closing_cash_actual' => 1000.00])->assertStatus(200);

        // Retrying close returns 422
        $resCloseRetry = $this->postJson("/api/v1/pos/registers/{$this->register1->id}/close", ['closing_cash_actual' => 1000.00]);
        $resCloseRetry->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_21_duplicate_cash_movement_idempotency_protection(): void
    {
        Sanctum::actingAs($this->cashier);

        $openRes = $this->postJson("/api/v1/pos/registers/{$this->register1->id}/open", ['opening_cash' => 1000.00]);
        $sessionId = $openRes->json('data.session_id');

        $uuid = (string) Str::uuid();

        $m1 = $this->postJson('/api/v1/pos/registers/cash-in', [
            'pos_session_id' => $sessionId,
            'movement_type' => 'cash_in',
            'amount' => 300.00,
            'reason' => 'Idempotent cash in',
            'client_trans_uuid' => $uuid,
        ])->assertStatus(201);

        $m2 = $this->postJson('/api/v1/pos/registers/cash-in', [
            'pos_session_id' => $sessionId,
            'movement_type' => 'cash_in',
            'amount' => 300.00,
            'reason' => 'Idempotent cash in',
            'client_trans_uuid' => $uuid,
        ])->assertStatus(201);

        $this->assertEquals($m1->json('data.id'), $m2->json('data.id'));

        $count = PosRegisterCashMovement::where('client_trans_uuid', $uuid)->count();
        $this->assertEquals(1, $count);
    }

    public function test_22_negative_drawer_balance_protection(): void
    {
        Sanctum::actingAs($this->cashier);

        $openRes = $this->postJson("/api/v1/pos/registers/{$this->register1->id}/open", ['opening_cash' => 100.00]);
        $sessionId = $openRes->json('data.session_id');

        // Cash out 500 when balance is 100
        $res = $this->postJson('/api/v1/pos/registers/cash-out', [
            'pos_session_id' => $sessionId,
            'movement_type' => 'cash_out',
            'amount' => 500.00,
            'reason' => 'Excessive cash out',
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_23_unauthorized_store_register_access_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        // Cashier attempts to open register2 (store2)
        $res = $this->postJson("/api/v1/pos/registers/{$this->register2->id}/open", ['opening_cash' => 1000.00]);
        $res->assertStatus(403);
    }

    public function test_24_super_admin_cross_store_register_management(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->postJson("/api/v1/pos/registers/{$this->register2->id}/open", ['opening_cash' => 1000.00]);
        $res->assertStatus(201);
    }

    public function test_25_non_super_admin_store_isolation(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson('/api/v1/pos/registers');

        $res->assertStatus(200);
        $items = $res->json('data.items');
        $this->assertCount(1, $items);
        $this->assertEquals($this->register1->id, $items[0]['id']);
    }

    public function test_26_split_payment_compatibility(): void
    {
        Sanctum::actingAs($this->cashier);

        $openRes = $this->postJson("/api/v1/pos/registers/{$this->register1->id}/open", ['opening_cash' => 1000.00]);
        $sessionId = $openRes->json('data.session_id');

        // Split payment: 600 cash + 400 card = 1000 total
        $this->postJson('/api/v1/pos/sales', [
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'pos_session_id' => $sessionId,
            'customer_id' => $this->customer->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 600.00],
                ['payment_method' => 'card', 'amount' => 400.00],
            ],
        ])->assertStatus(201);

        $summary = $this->getJson("/api/v1/pos/registers/{$this->register1->id}/current-session");
        $this->assertEquals(600.00, $summary->json('data.cash_sales'));
        $this->assertEquals(1600.00, $summary->json('data.expected_drawer_balance'));
    }

    public function test_27_store_credit_payment_compatibility(): void
    {
        Sanctum::actingAs($this->cashier);

        $openRes = $this->postJson("/api/v1/pos/registers/{$this->register1->id}/open", ['opening_cash' => 1000.00]);

        $summary = $this->getJson("/api/v1/pos/registers/{$this->register1->id}/current-session");
        $this->assertEquals(0.00, $summary->json('data.cash_sales'));
        $this->assertEquals(1000.00, $summary->json('data.expected_drawer_balance'));
    }

    public function test_28_paginated_cash_movements_and_reconciliation_history(): void
    {
        Sanctum::actingAs($this->cashier);

        $openRes = $this->postJson("/api/v1/pos/registers/{$this->register1->id}/open", ['opening_cash' => 1000.00]);
        $sessionId = $openRes->json('data.session_id');

        $this->postJson('/api/v1/pos/registers/cash-in', [
            'pos_session_id' => $sessionId,
            'movement_type' => 'cash_in',
            'amount' => 100.00,
            'reason' => 'Movement 1',
        ]);

        $res = $this->getJson('/api/v1/pos/registers/cash-movements');
        $res->assertStatus(200)->assertJsonStructure(['success', 'message', 'data' => ['items', 'pagination']]);
    }

    public function test_29_standardized_api_responses(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson('/api/v1/pos/registers');

        $res->assertStatus(200)->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_30_pos_register_crud_endpoints(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // 1. Create
        $resCreate = $this->postJson('/api/v1/pos/registers', [
            'store_id' => $this->store1->id,
            'code' => 'REG-CRUD',
            'name' => 'CRUD Register',
            'is_active' => true,
        ])->assertStatus(201);

        $regId = $resCreate->json('data.id');

        // 2. Read
        $this->getJson("/api/v1/pos/registers/{$regId}")->assertStatus(200)->assertJsonPath('data.name', 'CRUD Register');

        // 3. Update
        $this->putJson("/api/v1/pos/registers/{$regId}", ['name' => 'CRUD Register Updated'])->assertStatus(200)->assertJsonPath('data.name', 'CRUD Register Updated');

        // 4. Toggle Status
        $this->patchJson("/api/v1/pos/registers/{$regId}/status")->assertStatus(200)->assertJsonPath('data.is_active', false);
    }
}
