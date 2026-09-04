<?php

namespace Tests\Feature\Api\v1;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\LoyaltyAccount;
use App\Models\Permission;
use App\Models\PurchaseBill;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Role;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportsModuleFixTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Store $store;
    protected Supplier $supplier;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $permReports = Permission::create(['name' => 'reports.view', 'guard_name' => 'web', 'module_group' => 'Reports', 'display_name' => 'View Reports']);
        $permProducts = Permission::create(['name' => 'products.view', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Products']);
        $permProcurement = Permission::create(['name' => 'procurement.view', 'guard_name' => 'web', 'module_group' => 'Procurement', 'display_name' => 'View Procurement']);
        $permPayments = Permission::create(['name' => 'payments.view', 'guard_name' => 'web', 'module_group' => 'Payments', 'display_name' => 'View Payments']);

        $role = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $role->permissions()->attach([$permReports->id, $permProducts->id, $permProcurement->id, $permPayments->id]);

        $this->adminUser = User::create([
            'name' => 'Admin Test User',
            'username' => 'admintest',
            'email' => 'admin@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($role->id, ['model_type' => User::class]);

        $this->store = Store::create([
            'code' => 'ST-MAIN',
            'name' => 'Main Outlet',
            'is_active' => true,
        ]);
        $this->adminUser->stores()->attach($this->store->id);

        $this->supplier = Supplier::create([
            'company_name' => 'Apex Traders',
            'name' => 'Apex Traders',
            'contact_person' => 'Rajesh Kumar',
            'phone' => '9876543210',
            'mobile_number' => '9876543210',
            'email' => 'apex@example.com',
        ]);

        $this->customer = Customer::create([
            'name' => 'Subarna Das',
            'mobile_number' => '9123456789',
            'email' => 'subarna@example.com',
            'city' => 'Kolkata',
            'reward_points' => 50,
        ]);

        LoyaltyAccount::create([
            'customer_id' => $this->customer->id,
            'available_points' => 50,
            'lifetime_earned_points' => 50,
            'lifetime_redeemed_points' => 0,
            'status' => 'active',
        ]);
    }

    public function test_purchase_summary_calculates_actual_database_values_without_exception(): void
    {
        Sanctum::actingAs($this->adminUser);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-2026-001',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'order_date' => now()->format('Y-m-d'),
            'status' => 'draft',
            'subtotal' => 5000.00,
            'grand_total' => 5000.00,
            'due_amount' => 5000.00,
            'created_by' => $this->adminUser->id,
        ]);

        $bill = PurchaseBill::create([
            'bill_number' => 'BILL-2026-001',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'bill_date' => now()->format('Y-m-d'),
            'subtotal' => 10000.00,
            'tax_amount' => 1200.00,
            'grand_total' => 11200.00,
            'paid_amount' => 4000.00,
            'due_amount' => 7200.00,
            'payment_status' => 'partial',
            'created_by' => $this->adminUser->id,
        ]);

        PurchaseReturn::create([
            'return_number' => 'PRET-2026-001',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'total_return_amount' => 1500.00,
            'reason' => 'Defective batch',
            'processed_by' => $this->adminUser->id,
        ]);

        $response = $this->getJson('/api/v1/reports/purchases-summary');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_purchases' => 11200.00,
                    'purchase_bills_count' => 1,
                    'pending_pos_count' => 1,
                    'purchase_returns_total' => 1500.00,
                    'outstanding_supplier_amount' => 7200.00,
                ],
            ]);
    }

    public function test_customer_report_returns_calculated_loyalty_spent_paid_and_outstanding(): void
    {
        Sanctum::actingAs($this->adminUser);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-001',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 2000.00,
            'taxable_amount' => 2000.00,
            'total_tax' => 240.00,
            'grand_total' => 2240.00,
            'paid_amount' => 1500.00,
            'payment_status' => 'partial',
            'status' => 'completed',
            'created_by' => $this->adminUser->id,
        ]);

        CustomerPayment::create([
            'payment_number' => 'PAY-CUST-001',
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'invoice_id' => $invoice->id,
            'amount' => 500.00,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'collected_by' => $this->adminUser->id,
        ]);

        $response = $this->getJson('/api/v1/customers');

        $response->assertStatus(200);

        $items = $response->json('data.items');
        $targetCustomer = collect($items)->firstWhere('id', $this->customer->id);

        $this->assertNotNull($targetCustomer);
        $this->assertEquals(50, $targetCustomer['loyalty_points']);
        $this->assertEquals(2240.00, $targetCustomer['total_purchase_amount']);
        $this->assertEquals(2000.00, $targetCustomer['paid_amount']); // 1500 invoice paid + 500 direct customer payment
        $this->assertEquals(240.00, $targetCustomer['outstanding_balance']); // 2240 - 2000 = 240
    }

    public function test_payment_collections_returns_unified_payment_list(): void
    {
        Sanctum::actingAs($this->adminUser);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-002',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1000.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->adminUser->id,
        ]);

        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_method' => 'upi',
            'amount' => 1000.00,
            'payment_time' => now(),
        ]);

        CustomerPayment::create([
            'payment_number' => 'PAY-DIRECT-001',
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'amount' => 350.00,
            'payment_method' => 'cash',
            'payment_date' => now(),
            'collected_by' => $this->adminUser->id,
        ]);

        $response = $this->getJson('/api/v1/payments/collections');

        $response->assertStatus(200);

        $items = $response->json('data.items');
        $this->assertGreaterThanOrEqual(2, count($items));

        $hasInvoicePayment = collect($items)->contains(fn($i) => ($i['amount'] == 1000.00 && $i['payment_method'] === 'upi'));
        $hasCustomerPayment = collect($items)->contains(fn($i) => ($i['amount'] == 350.00 && $i['payment_method'] === 'cash'));

        $this->assertTrue($hasInvoicePayment, 'Invoice payment should appear in Payment Collections list.');
        $this->assertTrue($hasCustomerPayment, 'Customer payment should appear in Payment Collections list.');
    }

    public function test_store_and_date_filtering_on_purchase_and_customer_reports(): void
    {
        Sanctum::actingAs($this->adminUser);

        $today = now()->format('Y-m-d');
        $futureDate = now()->addDays(5)->format('Y-m-d');

        $resPurchaseToday = $this->getJson("/api/v1/reports/purchases-summary?date_from={$today}&date_to={$today}&store_id={$this->store->id}");
        $resPurchaseToday->assertStatus(200);

        $resPurchaseFuture = $this->getJson("/api/v1/reports/purchases-summary?date_from={$futureDate}&date_to={$futureDate}");
        $resPurchaseFuture->assertStatus(200)
            ->assertJsonPath('data.total_purchases', 0)
            ->assertJsonPath('data.purchase_bills_count', 0);
    }
}
