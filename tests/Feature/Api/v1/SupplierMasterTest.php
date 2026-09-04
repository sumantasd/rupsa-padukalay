<?php

namespace Tests\Feature\Api\v1;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierMasterTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $noPermUser;

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
        $this->getJson('/api/v1/suppliers')->assertStatus(401);
    }

    public function test_2_user_without_permission_is_denied(): void
    {
        Sanctum::actingAs($this->noPermUser);
        $this->getJson('/api/v1/suppliers')->assertStatus(403);
    }

    public function test_3_supplier_creation_listing_viewing_and_updating(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Create Supplier
        $res = $this->postJson('/api/v1/suppliers', [
            'name' => 'Apex Footwear Ltd',
            'company_name' => 'Apex Footwear Ltd',
            'gstin' => '19AAAAA0000A1Z5',
            'phone' => '9830098300',
            'email' => 'contact@apex.com',
            'city' => 'Kolkata',
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Apex Footwear Ltd',
                    'phone' => '9830098300',
                    'gstin' => '19AAAAA0000A1Z5',
                ],
            ]);

        $supplierId = $res->json('data.id');

        // List Suppliers
        $this->getJson('/api/v1/suppliers?search=Apex')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');

        // View Single Supplier
        $this->getJson("/api/v1/suppliers/{$supplierId}")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['id' => $supplierId]]);

        // Update Supplier
        $this->putJson("/api/v1/suppliers/{$supplierId}", [
            'name' => 'Apex Footwear Corp',
            'city' => 'Howrah',
        ])->assertStatus(200)->assertJson(['data' => ['name' => 'Apex Footwear Corp', 'city' => 'Howrah']]);
    }

    public function test_4_validation_rejects_missing_required_fields(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->postJson('/api/v1/suppliers', [
            'company_name' => 'No Name Corp',
        ])->assertStatus(422)->assertJsonValidationErrors(['name', 'phone']);
    }

    public function test_5_supplier_soft_deletion(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $supplier = Supplier::create([
            'name' => 'Supplier To Delete',
            'phone' => '9000000000',
        ]);

        $this->deleteJson("/api/v1/suppliers/{$supplier->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    public function test_6_nonexistent_supplier_returns_404(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $this->getJson('/api/v1/suppliers/99999')
            ->assertStatus(404)
            ->assertJson(['success' => false, 'message' => 'Supplier not found.']);
    }
}
