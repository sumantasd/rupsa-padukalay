<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Permission;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\Role;
use App\Models\Size;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SplitPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $cashier;
    protected User $noPermUser;
    protected Store $store1;
    protected Store $store2;
    protected Customer $customer;
    protected ProductVariantSize $variantSize1;
    protected PosSession $openSession1;
    protected Invoice $unpaidInvoice;
    protected Invoice $paidInvoice;
    protected Invoice $cancelledInvoice;

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
            'name' => 'Rajesh Kumar',
            'mobile_number' => '9831098310',
            'city' => 'Kolkata',
        ]);

        $brand = Brand::create(['name' => 'Bata', 'slug' => 'bata', 'is_active' => true]);
        $category = Category::create(['name' => 'Formal', 'slug' => 'formal', 'is_active' => true]);
        $colorBlack = Color::create(['name' => 'Black', 'code' => 'BLK']);
        $size8 = Size::create(['size_number' => '08', 'size_system' => 'UK', 'sort_order' => 8]);

        $product = Product::create([
            'article_number' => 'ART805',
            'name' => 'Men Formal Shoe',
            'slug' => 'art805-men-formal-shoe',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
        ]);

        $variant1 = ProductVariant::create(['product_id' => $product->id, 'color_id' => $colorBlack->id]);

        $this->variantSize1 = ProductVariantSize::create([
            'product_variant_id' => $variant1->id,
            'size_id' => $size8->id,
            'sku' => 'ART805-BLK-08',
            'barcode' => '8901234567890',
            'cost_price' => 500.00,
            'mrp' => 1299.00,
            'selling_price' => 1000.00,
        ]);

        InventoryStock::create(['product_variant_size_id' => $this->variantSize1->id, 'store_id' => $this->store1->id, 'stock_quantity' => 50]);

        $this->openSession1 = PosSession::create([
            'store_id' => $this->store1->id,
            'user_id' => $this->cashier->id,
            'opened_at' => now(),
            'opening_cash' => 1000.00,
            'closing_cash_system' => 1000.00,
            'status' => 'open',
        ]);

        // Unpaid Invoice (grand_total = 1200.00, paid_amount = 0.00)
        $this->unpaidInvoice = Invoice::create([
            'invoice_number' => 'INV-UNPAID-001',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1200.00,
            'discount_amount' => 0.00,
            'grand_total' => 1200.00,
            'paid_amount' => 0.00,
            'payment_status' => 'unpaid',
            'sale_type' => 'pos_counter',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $this->unpaidInvoice->id,
            'product_variant_size_id' => $this->variantSize1->id,
            'sku_snapshot' => $this->variantSize1->sku,
            'article_number_snapshot' => 'ART805',
            'product_name_snapshot' => 'Men Formal Shoe',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '08',
            'cost_price' => 500.00,
            'mrp' => 1299.00,
            'unit_price' => 1200.00,
            'quantity' => 1,
            'subtotal' => 1200.00,
        ]);

        // Fully Paid Invoice
        $this->paidInvoice = Invoice::create([
            'invoice_number' => 'INV-PAID-001',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1000.00,
            'discount_amount' => 0.00,
            'grand_total' => 1000.00,
            'paid_amount' => 1000.00,
            'payment_status' => 'paid',
            'sale_type' => 'pos_counter',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        InvoicePayment::create([
            'invoice_id' => $this->paidInvoice->id,
            'payment_method' => 'cash',
            'amount' => 1000.00,
            'payment_time' => now(),
        ]);

        // Cancelled Invoice
        $this->cancelledInvoice = Invoice::create([
            'invoice_number' => 'INV-CANCELLED-001',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'grand_total' => 500.00,
            'paid_amount' => 0.00,
            'payment_status' => 'unpaid',
            'sale_type' => 'pos_counter',
            'status' => 'cancelled',
            'created_by' => $this->cashier->id,
        ]);
    }

    public function test_1_unauthenticated_user_rejected(): void
    {
        $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'amount' => 500,
            'payment_method' => 'cash',
        ])->assertStatus(401);
    }

    public function test_2_user_without_required_permission_rejected(): void
    {
        Sanctum::actingAs($this->noPermUser);

        $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'amount' => 500,
            'payment_method' => 'cash',
        ])->assertStatus(403);
    }

    public function test_3_valid_single_payment_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'amount' => 1200.00,
            'payment_method' => 'cash',
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'invoice_id' => $this->unpaidInvoice->id,
                    'grand_total' => 1200.00,
                    'paid_amount' => 1200.00,
                    'remaining_balance' => 0.00,
                    'payment_status' => 'paid',
                ],
            ]);
    }

    public function test_4_valid_two_method_split_payment_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 500.00],
                ['payment_method' => 'upi', 'amount' => 700.00, 'transaction_reference' => 'UTR9988776655'],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'grand_total' => 1200.00,
                    'paid_amount' => 1200.00,
                    'remaining_balance' => 0.00,
                    'payment_status' => 'paid',
                ],
            ]);

        $this->assertEquals(2, InvoicePayment::where('invoice_id', $this->unpaidInvoice->id)->count());
    }

    public function test_5_valid_three_method_split_payment_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 200.00],
                ['payment_method' => 'upi', 'amount' => 500.00, 'transaction_reference' => 'UTR123'],
                ['payment_method' => 'card', 'amount' => 500.00, 'transaction_reference' => 'AUTH8899'],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'data' => [
                    'paid_amount' => 1200.00,
                    'remaining_balance' => 0.00,
                    'payment_status' => 'paid',
                ],
            ]);

        $this->assertEquals(3, InvoicePayment::where('invoice_id', $this->unpaidInvoice->id)->count());
    }

    public function test_6_payment_totals_are_calculated_validated_server_side(): void
    {
        Sanctum::actingAs($this->cashier);

        // Attempt to pay 400 when grand total is 1200
        $res = $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'amount' => 400.00,
            'payment_method' => 'cash',
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'data' => [
                    'paid_amount' => 400.00,
                    'remaining_balance' => 800.00,
                    'payment_status' => 'partial',
                ],
            ]);
    }

    public function test_7_payment_sum_must_equal_payable_amount(): void
    {
        Sanctum::actingAs($this->cashier);

        // Pay 800 first (leaving 400)
        $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'amount' => 800.00,
            'payment_method' => 'cash',
        ])->assertStatus(201);

        // Second payment of exact remaining 400 completing the bill
        $res = $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'amount' => 400.00,
            'payment_method' => 'upi',
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'data' => [
                    'paid_amount' => 1200.00,
                    'remaining_balance' => 0.00,
                    'payment_status' => 'paid',
                ],
            ]);
    }

    public function test_8_overpayment_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        // Invoice grand_total is 1200. Attempting to pay 1300 must be rejected
        $res = $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'amount' => 1300.00,
            'payment_method' => 'cash',
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_9_zero_payment_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'amount' => 0.00,
            'payment_method' => 'cash',
        ]);

        $res->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_10_negative_payment_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'amount' => -100.00,
            'payment_method' => 'cash',
        ]);

        $res->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_11_invalid_payment_method_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'amount' => 500.00,
            'payment_method' => 'crypto',
        ]);

        $res->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public function test_12_payment_status_changes_correctly(): void
    {
        Sanctum::actingAs($this->cashier);

        // Initial state is unpaid
        $statusVal = is_object($this->unpaidInvoice->payment_status) ? $this->unpaidInvoice->payment_status->value : $this->unpaidInvoice->payment_status;
        $this->assertEquals('unpaid', $statusVal);

        // Partial payment (500)
        $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'amount' => 500.00,
            'payment_method' => 'cash',
        ])->assertStatus(201);

        $inv = $this->unpaidInvoice->fresh();
        $invStatusVal = is_object($inv->payment_status) ? $inv->payment_status->value : $inv->payment_status;
        $this->assertEquals('partial', $invStatusVal);

        // Full payment (700 remaining)
        $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'amount' => 700.00,
            'payment_method' => 'upi',
        ])->assertStatus(201);

        $invFinal = $this->unpaidInvoice->fresh();
        $invFinalStatusVal = is_object($invFinal->payment_status) ? $invFinal->payment_status->value : $invFinal->payment_status;
        $this->assertEquals('paid', $invFinalStatusVal);
    }

    public function test_13_invoice_paid_amount_updates_correctly(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'amount' => 600.00,
            'payment_method' => 'cash',
        ])->assertStatus(201);

        $this->assertEquals(600.00, (float) $this->unpaidInvoice->fresh()->paid_amount);
    }

    public function test_14_remaining_balance_updates_correctly(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'amount' => 450.00,
            'payment_method' => 'cash',
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'data' => [
                    'remaining_balance' => 750.00, // 1200 - 450
                ],
            ]);
    }

    public function test_15_invoice_payment_records_are_created_correctly(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 600.00, 'notes' => 'Cash part'],
                ['payment_method' => 'card', 'amount' => 600.00, 'transaction_reference' => 'TXN776655', 'notes' => 'Card part'],
            ],
        ])->assertStatus(201);

        $payments = InvoicePayment::where('invoice_id', $this->unpaidInvoice->id)->get();
        $this->assertCount(2, $payments);
        $method1 = is_object($payments[0]->payment_method) ? $payments[0]->payment_method->value : $payments[0]->payment_method;
        $method2 = is_object($payments[1]->payment_method) ? $payments[1]->payment_method->value : $payments[1]->payment_method;
        $this->assertEquals('cash', $method1);
        $this->assertEquals(600.00, (float) $payments[0]->amount);
        $this->assertEquals('card', $method2);
        $this->assertEquals('TXN776655', $payments[1]->transaction_reference);
    }

    public function test_16_upi_card_reference_handling_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'amount' => 1200.00,
            'payment_method' => 'upi',
            'transaction_reference' => 'UTR1122334455',
        ])->assertStatus(201);

        $payment = InvoicePayment::where('invoice_id', $this->unpaidInvoice->id)->first();
        $method = is_object($payment->payment_method) ? $payment->payment_method->value : $payment->payment_method;
        $this->assertEquals('upi', $method);
        $this->assertEquals('UTR1122334455', $payment->transaction_reference);
    }

    public function test_17_cash_payment_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'amount' => 1200.00,
            'payment_method' => 'cash',
        ])->assertStatus(201);

        $payment = InvoicePayment::where('invoice_id', $this->unpaidInvoice->id)->first();
        $method = is_object($payment->payment_method) ? $payment->payment_method->value : $payment->payment_method;
        $this->assertEquals('cash', $method);
        $this->assertNull($payment->transaction_reference);
    }

    public function test_18_payment_requires_valid_open_pos_session(): void
    {
        $cashierNoSession = User::create([
            'name' => 'No Session Cashier',
            'username' => 'nosess_cashier2',
            'email' => 'nosess2@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $cashierNoSession->roles()->attach(Role::where('name', 'Cashier')->first()->id, ['model_type' => User::class]);
        $cashierNoSession->stores()->attach($this->store1->id);

        Sanctum::actingAs($cashierNoSession);

        $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'amount' => 500.00,
            'payment_method' => 'cash',
        ])->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_19_closed_invalid_pos_session_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        // Close the active session
        $this->openSession1->status = 'closed';
        $this->openSession1->save();

        $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'amount' => 500.00,
            'payment_method' => 'cash',
        ])->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_20_unauthorized_store_payment_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        // Create invoice belonging to Store 2 (which cashier is NOT authorized to access)
        $store2Invoice = Invoice::create([
            'invoice_number' => 'INV-STORE2-001',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store2->id,
            'grand_total' => 800.00,
            'paid_amount' => 0.00,
            'payment_status' => 'unpaid',
            'sale_type' => 'pos_counter',
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
        ]);

        $this->postJson("/api/v1/pos/sales/{$store2Invoice->id}/payments", [
            'amount' => 800.00,
            'payment_method' => 'cash',
        ])->assertStatus(403);
    }

    public function test_21_super_admin_cross_store_access_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Create open session for superAdmin in store2
        PosSession::create([
            'store_id' => $this->store2->id,
            'user_id' => $this->superAdmin->id,
            'opened_at' => now(),
            'opening_cash' => 1000.00,
            'status' => 'open',
        ]);

        $store2Invoice = Invoice::create([
            'invoice_number' => 'INV-STORE2-002',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store2->id,
            'grand_total' => 900.00,
            'paid_amount' => 0.00,
            'payment_status' => 'unpaid',
            'sale_type' => 'pos_counter',
            'status' => 'completed',
            'created_by' => $this->superAdmin->id,
        ]);

        $res = $this->postJson("/api/v1/pos/sales/{$store2Invoice->id}/payments", [
            'amount' => 900.00,
            'payment_method' => 'cash',
        ]);

        $res->assertStatus(201)->assertJson(['success' => true]);
    }

    public function test_22_already_fully_paid_invoice_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson("/api/v1/pos/sales/{$this->paidInvoice->id}/payments", [
            'amount' => 100.00,
            'payment_method' => 'cash',
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_23_cancelled_invalid_sale_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson("/api/v1/pos/sales/{$this->cancelledInvoice->id}/payments", [
            'amount' => 500.00,
            'payment_method' => 'cash',
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_24_atomic_rollback_when_one_payment_entry_fails(): void
    {
        Sanctum::actingAs($this->cashier);

        // Payment array containing 1 valid entry (600) + 1 invalid zero-amount entry (0)
        $res = $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 600.00],
                ['payment_method' => 'upi', 'amount' => 0.00],
            ],
        ]);

        $res->assertStatus(422);

        // Assert atomic rollback: paid_amount remains 0.00 and no payment record created
        $this->assertEquals(0.00, (float) $this->unpaidInvoice->fresh()->paid_amount);
        $this->assertEquals(0, InvoicePayment::where('invoice_id', $this->unpaidInvoice->id)->count());
    }

    public function test_25_concurrent_double_payment_protection(): void
    {
        Sanctum::actingAs($this->cashier);

        // First full payment completes invoice
        $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'amount' => 1200.00,
            'payment_method' => 'cash',
        ])->assertStatus(201);

        // Immediate subsequent payment attempt must be rejected
        $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'amount' => 100.00,
            'payment_method' => 'cash',
        ])->assertStatus(422);
    }

    public function test_26_payment_detail_read_endpoint_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->postJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments", [
            'amount' => 600.00,
            'payment_method' => 'cash',
        ])->assertStatus(201);

        $res = $this->getJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments");

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'invoice_id' => $this->unpaidInvoice->id,
                    'grand_total' => 1200.00,
                    'paid_amount' => 600.00,
                    'remaining_balance' => 600.00,
                    'payment_status' => 'partial',
                ],
            ]);
    }

    public function test_27_nonexistent_sale_invoice_returns_404(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->getJson('/api/v1/pos/sales/999999/payments')->assertStatus(404);
        $this->postJson('/api/v1/pos/sales/999999/payments', ['amount' => 100, 'payment_method' => 'cash'])->assertStatus(404);
    }

    public function test_28_standardized_api_response_maintained(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson("/api/v1/pos/sales/{$this->unpaidInvoice->id}/payments");

        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'invoice_id',
                    'invoice_number',
                    'grand_total',
                    'paid_amount',
                    'remaining_balance',
                    'payment_status',
                    'payments',
                ],
            ]);
    }
}
