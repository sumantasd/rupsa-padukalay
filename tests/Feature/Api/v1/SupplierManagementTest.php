<?php

namespace Tests\Feature\Api\v1;

use App\Models\Color;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Role;
use App\Models\Size;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Store $store;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $permView = Permission::firstOrCreate(['name' => 'products.view'], ['guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Products']);
        $permCreate = Permission::firstOrCreate(['name' => 'products.create'], ['guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Create Products']);
        $permEdit = Permission::firstOrCreate(['name' => 'products.edit'], ['guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Edit Products']);
        $permDelete = Permission::firstOrCreate(['name' => 'products.delete'], ['guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Delete Products']);

        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin'], ['guard_name' => 'web']);
        $superAdminRole->permissions()->syncWithoutDetaching([$permView->id, $permCreate->id, $permEdit->id, $permDelete->id]);

        $this->adminUser = User::create([
            'name' => 'Supplier Test Admin',
            'username' => 'supplier_admin_' . rand(1000, 9999),
            'email' => 'supplier_admin_' . rand(1000, 9999) . '@rupsa.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($superAdminRole->id, ['model_type' => User::class]);

        $this->store = Store::create([
            'code' => 'STR-001',
            'name' => 'RUPSA PADUKALAYA - Main Outlet',
            'phone' => '+91 9735125112',
            'address' => 'DHANTALA BAZAR, DHANTALA, NADIA - 741202, WEST BENGAL, INDIA',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'code' => 'SUP-0001',
            'name' => 'Apex Footwear Vendors',
            'company_name' => 'Apex Leather Crafters Ltd',
            'gstin' => '19AAAAA0000A1Z5',
            'phone' => '+91 9831011223',
            'email' => 'vendor@apexfootwear.com',
            'city' => 'Kolkata',
            'state' => 'West Bengal',
            'opening_balance' => 5000.00,
            'opening_balance_type' => 'payable',
            'current_balance' => 5000.00,
            'is_active' => true,
        ]);
    }

    public function test_can_create_supplier_and_auto_generate_supplier_code()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/suppliers', [
                'name' => 'Bata Footwear India',
                'company_name' => 'Bata India Limited',
                'phone' => '+91 9876543210',
                'gstin' => '19AAACB1234A1Z1',
                'city' => 'Kolkata',
                'opening_balance' => 2000.00,
                'opening_balance_type' => 'payable',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Bata Footwear India')
            ->assertJsonPath('data.code', 'SUP-0002');

        $this->assertEquals(2000.00, $response->json('data.opening_balance'));

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Bata Footwear India',
            'code' => 'SUP-0002',
        ]);
    }

    public function test_can_update_supplier_details()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/v1/suppliers/{$this->supplier->id}", [
                'name' => 'Apex Leather Crafters Updated',
                'phone' => '+91 9831099999',
                'city' => 'Bantala, Kolkata',
                'notes' => 'Gents Loafers & School Shoes Vendor',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Apex Leather Crafters Updated')
            ->assertJsonPath('data.phone', '+91 9831099999');

        $this->assertDatabaseHas('suppliers', [
            'id' => $this->supplier->id,
            'name' => 'Apex Leather Crafters Updated',
            'phone' => '+91 9831099999',
        ]);
    }

    public function test_can_record_supplier_payment_and_decrease_due()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/payments", [
                'amount' => 2000.00,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'upi',
                'transaction_reference' => 'UTR123456789',
                'notes' => 'Part payment against opening payable',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_method', 'upi');

        $this->assertEquals(2000.00, $response->json('data.amount'));

        $this->assertDatabaseHas('supplier_payments', [
            'supplier_id' => $this->supplier->id,
            'amount' => 2000.00,
            'payment_method' => 'upi',
        ]);

        $this->supplier->refresh();
        $balances = $this->supplier->calculateBalances();
        $this->assertEquals(3000.00, $balances['current_due']); // 5000 opening - 2000 payment
    }

    public function test_supplier_ledger_returns_chronological_financial_statement()
    {
        // Record payment of ₹1,500
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/suppliers/{$this->supplier->id}/payments", [
                'amount' => 1500.00,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'bank_transfer',
            ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/suppliers/{$this->supplier->id}/ledger");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.supplier_id', $this->supplier->id);

        $this->assertEquals(5000.00, $response->json('data.opening_balance'));

        $entries = $response->json('data.entries');
        $this->assertIsArray($entries);
        $this->assertGreaterThanOrEqual(2, count($entries)); // Opening Balance + Payment
    }

    public function test_supplier_deletion_blocked_when_transaction_history_exists()
    {
        // Add a purchase order to this supplier
        PurchaseOrder::create([
            'po_number' => 'PO-TEST-001',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'order_date' => now()->toDateString(),
            'status' => 'received',
            'grand_total' => 10000.00,
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/v1/suppliers/{$this->supplier->id}");

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'This supplier cannot be deleted because transaction records already exist. You may deactivate this supplier instead.');

        $this->assertDatabaseHas('suppliers', [
            'id' => $this->supplier->id,
        ]);
    }
}
