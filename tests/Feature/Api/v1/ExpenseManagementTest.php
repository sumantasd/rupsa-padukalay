<?php

namespace Tests\Feature\Api\v1;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Permission;
use App\Models\PosSession;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $cashier;
    protected User $noPermUser;
    protected Store $store1;
    protected Store $store2;
    protected Warehouse $warehouse1;
    protected Warehouse $warehouse2;
    protected ExpenseCategory $categoryTea;
    protected ExpenseCategory $categoryRent;
    protected PosSession $openSession1;

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

        $this->warehouse1 = Warehouse::create(['code' => 'WH-001', 'name' => 'Main Warehouse', 'is_active' => true]);
        $this->warehouse1->stores()->attach($this->store1->id);
        $this->warehouse2 = Warehouse::create(['code' => 'WH-002', 'name' => 'Branch Warehouse', 'is_active' => true]);
        $this->warehouse2->stores()->attach($this->store2->id);

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

        $this->categoryTea = ExpenseCategory::create(['name' => 'Tea & Refreshments']);
        $this->categoryRent = ExpenseCategory::create(['name' => 'Store Rent']);

        $this->openSession1 = PosSession::create([
            'store_id' => $this->store1->id,
            'user_id' => $this->cashier->id,
            'opened_at' => now(),
            'opening_cash' => 1000.00,
            'closing_cash_system' => 1000.00,
            'status' => 'open',
        ]);
    }

    public function test_1_unauthenticated_request_rejected(): void
    {
        $this->getJson('/api/v1/expenses')->assertStatus(401);
        $this->postJson('/api/v1/expenses', [])->assertStatus(401);
    }

    public function test_2_user_without_permission_rejected(): void
    {
        Sanctum::actingAs($this->noPermUser);

        $this->getJson('/api/v1/expenses')->assertStatus(403);
        $this->postJson('/api/v1/expenses', [
            'expense_category_id' => $this->categoryTea->id,
            'store_id' => $this->store1->id,
            'amount' => 150.00,
            'payment_method' => 'cash',
            'expense_date' => '2026-09-02',
        ])->assertStatus(403);
    }

    public function test_3_authorized_expense_creation(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson('/api/v1/expenses', [
            'expense_category_id' => $this->categoryTea->id,
            'store_id' => $this->store1->id,
            'amount' => 250.00,
            'payment_method' => 'cash',
            'description' => 'Evening tea for staff',
            'voucher_number' => 'VOUCH-001',
            'expense_date' => '2026-09-02',
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Expense recorded successfully.',
                'data' => [
                    'expense_category_id' => $this->categoryTea->id,
                    'store_id' => $this->store1->id,
                    'amount' => 250.00,
                    'payment_method' => 'cash',
                    'description' => 'Evening tea for staff',
                    'voucher_number' => 'VOUCH-001',
                    'expense_date' => '2026-09-02',
                    'pos_session_id' => $this->openSession1->id,
                    'created_by' => $this->cashier->id,
                ],
            ]);
    }

    public function test_4_validation_failure(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson('/api/v1/expenses', [
            'store_id' => $this->store1->id,
        ]);

        $res->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['expense_category_id', 'amount', 'payment_method', 'expense_date']]);
    }

    public function test_5_positive_amount_enforcement(): void
    {
        Sanctum::actingAs($this->cashier);

        // Zero amount
        $this->postJson('/api/v1/expenses', [
            'expense_category_id' => $this->categoryTea->id,
            'store_id' => $this->store1->id,
            'amount' => 0.00,
            'payment_method' => 'cash',
            'expense_date' => '2026-09-02',
        ])->assertStatus(422)->assertJsonStructure(['message', 'errors']);

        // Negative amount
        $this->postJson('/api/v1/expenses', [
            'expense_category_id' => $this->categoryTea->id,
            'store_id' => $this->store1->id,
            'amount' => -100.00,
            'payment_method' => 'cash',
            'expense_date' => '2026-09-02',
        ])->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_6_authorized_store_access(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson('/api/v1/expenses', [
            'expense_category_id' => $this->categoryTea->id,
            'store_id' => $this->store1->id,
            'amount' => 120.00,
            'payment_method' => 'cash',
            'expense_date' => '2026-09-02',
        ]);

        $res->assertStatus(201);
    }

    public function test_7_unauthorized_store_access_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        // Cashier is assigned to store1, attempting to create expense for store2
        $res = $this->postJson('/api/v1/expenses', [
            'expense_category_id' => $this->categoryTea->id,
            'store_id' => $this->store2->id,
            'amount' => 500.00,
            'payment_method' => 'cash',
            'expense_date' => '2026-09-02',
        ]);

        $res->assertStatus(403);
    }

    public function test_8_expense_listing(): void
    {
        Sanctum::actingAs($this->cashier);

        Expense::create([
            'expense_category_id' => $this->categoryTea->id,
            'store_id' => $this->store1->id,
            'amount' => 300.00,
            'payment_method' => 'cash',
            'expense_date' => '2026-09-02',
            'created_by' => $this->cashier->id,
        ]);

        $res = $this->getJson('/api/v1/expenses');

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Expenses retrieved successfully.',
            ])
            ->assertJsonCount(1, 'data.items');
    }

    public function test_9_pagination(): void
    {
        Sanctum::actingAs($this->cashier);

        for ($i = 1; $i <= 7; $i++) {
            Expense::create([
                'expense_category_id' => $this->categoryTea->id,
                'store_id' => $this->store1->id,
                'amount' => 100.00 * $i,
                'payment_method' => 'cash',
                'expense_date' => '2026-09-02',
                'created_by' => $this->cashier->id,
            ]);
        }

        $res = $this->getJson('/api/v1/expenses?per_page=3');

        $res->assertStatus(200)
            ->assertJsonCount(3, 'data.items')
            ->assertJson([
                'data' => [
                    'pagination' => [
                        'current_page' => 1,
                        'per_page' => 3,
                        'total' => 7,
                        'last_page' => 3,
                    ],
                ],
            ]);
    }

    public function test_10_filtering(): void
    {
        Sanctum::actingAs($this->cashier);

        Expense::create([
            'expense_category_id' => $this->categoryTea->id,
            'store_id' => $this->store1->id,
            'amount' => 200.00,
            'payment_method' => 'cash',
            'expense_date' => '2026-09-01',
            'created_by' => $this->cashier->id,
        ]);

        Expense::create([
            'expense_category_id' => $this->categoryRent->id,
            'store_id' => $this->store1->id,
            'amount' => 10000.00,
            'payment_method' => 'card',
            'expense_date' => '2026-09-02',
            'created_by' => $this->cashier->id,
        ]);

        $res = $this->getJson("/api/v1/expenses?expense_category_id={$this->categoryRent->id}");

        $res->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJson([
                'data' => [
                    'items' => [
                        ['expense_category_id' => $this->categoryRent->id, 'amount' => 10000.00],
                    ],
                ],
            ]);
    }

    public function test_11_expense_detail(): void
    {
        Sanctum::actingAs($this->cashier);

        $expense = Expense::create([
            'expense_category_id' => $this->categoryTea->id,
            'store_id' => $this->store1->id,
            'amount' => 450.00,
            'payment_method' => 'upi',
            'description' => 'Staff lunch',
            'expense_date' => '2026-09-02',
            'created_by' => $this->cashier->id,
        ]);

        $res = $this->getJson("/api/v1/expenses/{$expense->id}");

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $expense->id,
                    'amount' => 450.00,
                    'payment_method' => 'upi',
                    'description' => 'Staff lunch',
                ],
            ]);
    }

    public function test_12_expense_update(): void
    {
        Sanctum::actingAs($this->cashier);

        $expense = Expense::create([
            'expense_category_id' => $this->categoryTea->id,
            'store_id' => $this->store1->id,
            'amount' => 300.00,
            'payment_method' => 'cash',
            'expense_date' => '2026-09-02',
            'created_by' => $this->cashier->id,
        ]);

        $res = $this->putJson("/api/v1/expenses/{$expense->id}", [
            'amount' => 350.00,
            'description' => 'Updated snacks amount',
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $expense->id,
                    'amount' => 350.00,
                    'description' => 'Updated snacks amount',
                ],
            ]);
    }

    public function test_13_invalid_update_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        $expense = Expense::create([
            'expense_category_id' => $this->categoryTea->id,
            'store_id' => $this->store1->id,
            'amount' => 300.00,
            'payment_method' => 'cash',
            'expense_date' => '2026-09-02',
            'created_by' => $this->cashier->id,
        ]);

        $res = $this->putJson("/api/v1/expenses/{$expense->id}", [
            'amount' => -50.00,
        ]);

        $res->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_14_expense_deletion(): void
    {
        Sanctum::actingAs($this->cashier);

        $expense = Expense::create([
            'expense_category_id' => $this->categoryTea->id,
            'store_id' => $this->store1->id,
            'amount' => 200.00,
            'payment_method' => 'cash',
            'expense_date' => '2026-09-02',
            'created_by' => $this->cashier->id,
        ]);

        $res = $this->deleteJson("/api/v1/expenses/{$expense->id}");

        $res->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_15_super_admin_cross_store_access(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->postJson('/api/v1/expenses', [
            'expense_category_id' => $this->categoryRent->id,
            'store_id' => $this->store2->id,
            'amount' => 15000.00,
            'payment_method' => 'card',
            'expense_date' => '2026-09-02',
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'data' => [
                    'store_id' => $this->store2->id,
                    'amount' => 15000.00,
                ],
            ]);
    }

    public function test_16_invalid_store_warehouse_relationship(): void
    {
        Sanctum::actingAs($this->cashier);

        // store1 and warehouse2 (which belongs to store2)
        $res = $this->postJson('/api/v1/expenses', [
            'expense_category_id' => $this->categoryTea->id,
            'store_id' => $this->store1->id,
            'warehouse_id' => $this->warehouse2->id,
            'amount' => 500.00,
            'payment_method' => 'cash',
            'expense_date' => '2026-09-02',
        ]);

        $res->assertStatus(422)->assertJsonStructure(['message', 'errors' => ['warehouse_id']]);
    }

    public function test_17_payment_method_validation(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson('/api/v1/expenses', [
            'expense_category_id' => $this->categoryTea->id,
            'store_id' => $this->store1->id,
            'amount' => 500.00,
            'payment_method' => 'crypto',
            'expense_date' => '2026-09-02',
        ]);

        $res->assertStatus(422)->assertJsonStructure(['message', 'errors' => ['payment_method']]);
    }

    public function test_18_date_validation(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson('/api/v1/expenses', [
            'expense_category_id' => $this->categoryTea->id,
            'store_id' => $this->store1->id,
            'amount' => 500.00,
            'payment_method' => 'cash',
            'expense_date' => 'invalid-date-str',
        ]);

        $res->assertStatus(422)->assertJsonStructure(['message', 'errors' => ['expense_date']]);
    }

    public function test_19_atomic_transaction_behaviour(): void
    {
        Sanctum::actingAs($this->cashier);

        $countBefore = Expense::count();

        // Failed payload (invalid category)
        $this->postJson('/api/v1/expenses', [
            'expense_category_id' => 999999,
            'store_id' => $this->store1->id,
            'amount' => 500.00,
            'payment_method' => 'cash',
            'expense_date' => '2026-09-02',
        ])->assertStatus(422);

        $countAfter = Expense::count();
        $this->assertEquals($countBefore, $countAfter);
    }

    public function test_20_standardized_api_response(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson('/api/v1/expense-categories');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);
    }
}
