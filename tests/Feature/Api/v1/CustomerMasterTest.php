<?php

namespace Tests\Feature\Api\v1;

use App\Models\Customer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerMasterTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $noPermUser;

    protected function setUp(): void
    {
        parent::setUp();

        $permView = Permission::create(['name' => 'products.view', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Products']);
        $permEdit = Permission::create(['name' => 'products.edit', 'guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Edit Products']);

        $superAdminRole = Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        $superAdminRole->permissions()->attach([$permView->id, $permEdit->id]);

        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'username' => 'superadmin',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $this->superAdmin->roles()->attach($superAdminRole->id, ['model_type' => User::class]);

        $this->noPermUser = User::create([
            'name' => 'No Perm User',
            'username' => 'noperm_user',
            'email' => 'noperm@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
    }

    public function test_1_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/customers')->assertStatus(401);
    }

    public function test_2_user_without_permission_is_denied(): void
    {
        Sanctum::actingAs($this->noPermUser);
        $this->getJson('/api/v1/customers')->assertStatus(403);
    }

    public function test_3_customer_creation_listing_viewing_and_updating(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Create Customer
        $res = $this->postJson('/api/v1/customers', [
            'name' => 'Rahul Sharma',
            'mobile_number' => '9876543210',
            'email' => 'rahul@example.com',
            'city' => 'Kolkata',
            'pincode' => '700001',
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Rahul Sharma',
                    'mobile_number' => '9876543210',
                    'city' => 'Kolkata',
                ],
            ]);

        $customerId = $res->json('data.id');

        // List Customers
        $this->getJson('/api/v1/customers?search=Rahul')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');

        // View Single Customer
        $this->getJson("/api/v1/customers/{$customerId}")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['id' => $customerId]]);

        // Update Customer
        $this->putJson("/api/v1/customers/{$customerId}", [
            'name' => 'Rahul K Sharma',
            'city' => 'Howrah',
        ])->assertStatus(200)->assertJson(['data' => ['name' => 'Rahul K Sharma', 'city' => 'Howrah']]);
    }

    public function test_4_duplicate_mobile_number_is_rejected(): void
    {
        Sanctum::actingAs($this->superAdmin);

        Customer::create(['mobile_number' => '9876543210', 'name' => 'Customer A']);

        $this->postJson('/api/v1/customers', [
            'name' => 'Customer B',
            'mobile_number' => '9876543210',
        ])->assertStatus(422)->assertJsonValidationErrors(['mobile_number']);
    }

    public function test_5_customer_soft_deletion(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $customer = Customer::create(['mobile_number' => '9123456789', 'name' => 'Customer To Delete']);

        $this->deleteJson("/api/v1/customers/{$customer->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    public function test_6_nonexistent_customer_returns_404(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->getJson('/api/v1/customers/99999')
            ->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Customer not found.']);
    }
}
