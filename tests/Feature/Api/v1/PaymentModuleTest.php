<?php

namespace Tests\Feature\Api\v1;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerRefund;
use App\Models\DayClosing;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\PosRegister;
use App\Models\PosRegisterCashMovement;
use App\Models\PosSession;
use App\Models\Role;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;
    protected Customer $customer;
    protected Supplier $supplier;
    protected PosRegister $register;
    protected PosSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::create([
            'code' => 'STR-001',
            'name' => 'RUPSA PADUKALAYA - Main Outlet',
            'phone' => '9876543210',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'username' => 'rupsa_payments_tester',
            'is_active' => true,
        ]);
        $this->user->stores()->attach($this->store->id);

        $role = Role::create([
            'name' => 'Super Admin',
            'guard_name' => 'web',
            'display_name' => 'Super Admin',
            'module_group' => 'system',
        ]);

        $permissions = [
            'products.view', 'products.create', 'products.edit',
            'pos.billing', 'pos.returns', 'pos.sessions',
            'payments.view', 'payments.create', 'payments.refund',
            'cash_drawer.view', 'cash_drawer.adjust', 'day_closing.view', 'day_closing.create',
        ];

        foreach ($permissions as $p) {
            $perm = Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web'], [
                'display_name' => ucfirst($p),
                'module_group' => 'payments',
            ]);
            $role->permissions()->syncWithoutDetaching([$perm->id]);
        }

        DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_id' => $this->user->id,
            'model_type' => User::class,
        ]);

        $this->customer = Customer::create([
            'name' => 'Rahul Sharma',
            'mobile_number' => '9830012345',
            'email' => 'rahul@example.com',
        ]);

        $this->supplier = Supplier::create([
            'code' => 'SUP-0005',
            'name' => 'Action Shoes Ltd',
            'company_name' => 'Action Footwear',
            'phone' => '9811122233',
            'is_active' => true,
        ]);

        $this->register = PosRegister::create([
            'store_id' => $this->store->id,
            'code' => 'REG-01',
            'name' => 'Main POS Counter',
            'is_active' => true,
        ]);

        $this->session = PosSession::create([
            'store_id' => $this->store->id,
            'pos_register_id' => $this->register->id,
            'user_id' => $this->user->id,
            'opened_at' => now(),
            'opening_cash' => 1000.00,
            'status' => 'open',
        ]);
    }

    /** @test */
    public function customer_payment_collection_updates_invoice_paid_amount_and_drawer(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-20260903-0001',
            'client_trans_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 2000.00,
            'grand_total' => 2000.00,
            'paid_amount' => 500.00,
            'payment_status' => 'partial',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        // Collect ₹1,500 cash payment against invoice due (₹1,500 due)
        $res = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/payments/collections', [
                'customer_id' => $this->customer->id,
                'invoice_id' => $invoice->id,
                'amount' => 1500.00,
                'payment_method' => 'cash',
                'notes' => 'Settling remaining due',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true);

        // Assert invoice payment status updated to paid
        $this->assertEquals('paid', Invoice::find($invoice->id)->payment_status);
        $this->assertEquals(2000.00, Invoice::find($invoice->id)->paid_amount);

        // Assert Cash Drawer Movement created
        $this->assertDatabaseHas('pos_register_cash_movements', [
            'pos_session_id' => $this->session->id,
            'movement_type' => 'cash_in',
            'amount' => 1500.00,
        ]);
    }

    /** @test */
    public function customer_payment_blocks_overpaying_invoice_due(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-20260903-0002',
            'client_trans_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'grand_total' => 1000.00,
            'paid_amount' => 800.00,
            'payment_status' => 'partial',
            'created_by' => $this->user->id,
        ]);

        // Attempt paying ₹500 when remaining due is ₹200
        $res = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/payments/collections', [
                'customer_id' => $this->customer->id,
                'invoice_id' => $invoice->id,
                'amount' => 500.00,
                'payment_method' => 'cash',
            ]);

        $res->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function customer_refund_blocks_exceeding_maximum_refundable_amount(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-20260903-0003',
            'client_trans_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'grand_total' => 1500.00,
            'paid_amount' => 1500.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        // 1st refund: ₹1,000
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/payments/refunds', [
                'invoice_id' => $invoice->id,
                'amount' => 1000.00,
                'refund_method' => 'cash',
                'reason' => 'Damaged Goods',
            ])->assertStatus(201);

        // 2nd refund attempt: ₹1,000 when max remaining refundable is ₹500
        $res = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/payments/refunds', [
                'invoice_id' => $invoice->id,
                'amount' => 1000.00,
                'refund_method' => 'cash',
                'reason' => 'Customer Request',
            ]);

        $res->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function cash_drawer_status_calculates_opening_sales_collections_refunds_and_expected_cash(): void
    {
        // Opening cash: 1,000
        // Record manual cash in of ₹500
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/payments/cash-drawer/movement', [
                'movement_type' => 'cash_in',
                'amount' => 500.00,
                'reason' => 'Added float',
            ])->assertStatus(201);

        $res = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/payments/cash-drawer/status');

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.opening_cash', 1000)
            ->assertJsonPath('data.manual_cash_in', 500)
            ->assertJsonPath('data.expected_cash', 1500);
    }

    /** @test */
    public function day_closing_aggregates_totals_calculates_variance_and_closes_pos_session(): void
    {
        // Perform day closing with actual cash ₹1,500 (Expected ₹1,000, Variance +₹500)
        $res = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/payments/day-closing', [
                'actual_cash' => 1500.00,
                'notes' => 'End of day closing completed',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.actual_cash', '1500.00')
            ->assertJsonPath('data.variance', '500.00');

        // Assert POS Session is now closed
        $this->assertEquals('closed', PosSession::find($this->session->id)->status->value ?? PosSession::find($this->session->id)->status);

        // Assert duplicate closing for same date is rejected
        $dupRes = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/payments/day-closing', [
                'actual_cash' => 1500.00,
                'notes' => 'Duplicate attempt',
            ]);

        $dupRes->assertStatus(422);
    }

    /** @test */
    public function day_closing_summary_endpoint_returns_comprehensive_financial_breakdown_and_expense_categories(): void
    {
        $res = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/payments/day-closing/summary?store_id=' . $this->store->id);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'sales_summary' => ['gross_sales', 'net_sales', 'cash_sales', 'total_digital_sales'],
                    'customer_collections' => ['cash_collections', 'total_collections'],
                    'refunds_summary' => ['cash_refunds', 'total_refunds'],
                    'supplier_summary' => ['purchase_bills_count', 'total_supplier_payments'],
                    'expenses_summary' => ['cash_expenses', 'total_expenses', 'category_breakdown'],
                    'cash_drawer' => ['expected_cash'],
                    'digital_reconciliation_matrix',
                    'high_level_financial_summary',
                    'transaction_counts',
                    'itemized_details' => ['sales', 'collections', 'refunds', 'expenses', 'supplier_payments'],
                ],
            ]);
    }

    /** @test */
    public function day_closing_requires_mandatory_notes_when_variance_is_non_zero(): void
    {
        // Expected cash is 1000. Actual cash 1200 (Variance +200). Empty notes should be rejected with 422
        $res = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/payments/day-closing', [
                'actual_cash' => 1200.00,
                'notes' => '',
            ]);

        $res->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
