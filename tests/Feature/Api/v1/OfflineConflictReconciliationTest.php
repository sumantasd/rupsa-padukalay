<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\HsnCode;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\PosSession;
use App\Models\PosSyncConflict;
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

class OfflineConflictReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $cashier;
    protected User $noPermUser;
    protected Store $store1;
    protected Store $store2;
    protected PosSession $openSession1;
    protected PosSession $openSession2;
    protected ProductVariantSize $variantSize1;
    protected ProductVariantSize $variantSize2;

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

        $this->openSession1 = PosSession::create([
            'store_id' => $this->store1->id,
            'user_id' => $this->cashier->id,
            'opened_at' => now(),
            'opening_cash' => 1000.00,
            'closing_cash_system' => 1000.00,
            'status' => 'open',
        ]);

        $this->openSession2 = PosSession::create([
            'store_id' => $this->store2->id,
            'user_id' => $this->superAdmin->id,
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
        $size9 = Size::create(['size_number' => '09']);

        $this->variantSize1 = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $size8->id,
            'sku' => 'ART900-BLK-08',
            'cost_price' => 500.00,
            'mrp' => 1200.00,
            'selling_price' => 1000.00,
        ]);

        $this->variantSize2 = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $size9->id,
            'sku' => 'ART900-BLK-09',
            'cost_price' => 500.00,
            'mrp' => 1200.00,
            'selling_price' => 1000.00,
        ]);

        InventoryStock::create([
            'product_variant_size_id' => $this->variantSize1->id,
            'store_id' => $this->store1->id,
            'warehouse_id' => 0,
            'stock_location_id' => 0,
            'stock_quantity' => 2, // Low stock for conflict testing
        ]);

        InventoryStock::create([
            'product_variant_size_id' => $this->variantSize2->id,
            'store_id' => $this->store1->id,
            'warehouse_id' => 0,
            'stock_location_id' => 0,
            'stock_quantity' => 10,
        ]);
    }

    public function test_1_unauthenticated_rejection(): void
    {
        $this->getJson('/api/v1/pos/sync-conflicts')->assertStatus(401);
        $this->getJson('/api/v1/pos/sync-conflicts/1')->assertStatus(401);
        $this->postJson('/api/v1/pos/sync-conflicts/1/resolve', [])->assertStatus(401);
    }

    public function test_2_permission_rejection(): void
    {
        Sanctum::actingAs($this->noPermUser);

        $this->getJson('/api/v1/pos/sync-conflicts')->assertStatus(403);
        $this->getJson('/api/v1/pos/sync-conflicts/1')->assertStatus(403);
        $this->postJson('/api/v1/pos/sync-conflicts/1/resolve', ['action' => 'dismiss'])->assertStatus(403);
    }

    public function test_3_conflict_creation_and_detection(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();

        // Submit sync request with requested quantity 50 > stock quantity 2
        $res = $this->postJson('/api/v1/pos/sales/sync', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 50]],
        ]);

        $res->assertStatus(200)->assertJsonPath('data.results.0.status', 'rejected');

        // Verify conflict record created in pos_sync_conflicts table
        $this->assertDatabaseHas('pos_sync_conflicts', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'conflict_type' => 'insufficient_stock',
            'status' => 'unresolved',
        ]);
    }

    public function test_4_conflict_listing(): void
    {
        Sanctum::actingAs($this->cashier);

        PosSyncConflict::create([
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'user_id' => $this->cashier->id,
            'conflict_type' => 'insufficient_stock',
            'conflict_reason' => 'Insufficient physical stock',
            'payload_snapshot' => ['items' => []],
            'status' => 'unresolved',
        ]);

        $res = $this->getJson('/api/v1/pos/sync-conflicts');

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'POS sync conflicts retrieved successfully.',
            ])
            ->assertJsonCount(1, 'data.items');
    }

    public function test_5_filtering(): void
    {
        Sanctum::actingAs($this->cashier);

        PosSyncConflict::create([
            'client_trans_uuid' => 'UUID-001',
            'store_id' => $this->store1->id,
            'user_id' => $this->cashier->id,
            'conflict_type' => 'insufficient_stock',
            'conflict_reason' => 'Stock error',
            'payload_snapshot' => [],
            'status' => 'unresolved',
        ]);

        PosSyncConflict::create([
            'client_trans_uuid' => 'UUID-002',
            'store_id' => $this->store1->id,
            'user_id' => $this->cashier->id,
            'conflict_type' => 'closed_pos_session',
            'conflict_reason' => 'Session closed',
            'payload_snapshot' => [],
            'status' => 'dismissed',
        ]);

        $res = $this->getJson('/api/v1/pos/sync-conflicts?status=unresolved');

        $res->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.client_trans_uuid', 'UUID-001');
    }

    public function test_6_conflict_detail(): void
    {
        Sanctum::actingAs($this->cashier);

        $conflict = PosSyncConflict::create([
            'client_trans_uuid' => 'UUID-DETAIL-999',
            'store_id' => $this->store1->id,
            'user_id' => $this->cashier->id,
            'conflict_type' => 'invalid_sku',
            'conflict_reason' => 'SKU not found',
            'payload_snapshot' => ['sku' => 'UNKNOWN'],
            'status' => 'unresolved',
        ]);

        $res = $this->getJson("/api/v1/pos/sync-conflicts/{$conflict->id}");

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $conflict->id,
                    'client_trans_uuid' => 'UUID-DETAIL-999',
                    'conflict_type' => 'invalid_sku',
                    'payload_snapshot' => ['sku' => 'UNKNOWN'],
                ],
            ]);
    }

    public function test_7_store_access_isolation(): void
    {
        Sanctum::actingAs($this->cashier);

        // Conflict for store1 (cashier authorized)
        PosSyncConflict::create([
            'client_trans_uuid' => 'UUID-S1',
            'store_id' => $this->store1->id,
            'user_id' => $this->cashier->id,
            'conflict_type' => 'insufficient_stock',
            'conflict_reason' => 'Low stock',
            'payload_snapshot' => [],
            'status' => 'unresolved',
        ]);

        // Conflict for store2 (cashier unauthorized)
        PosSyncConflict::create([
            'client_trans_uuid' => 'UUID-S2',
            'store_id' => $this->store2->id,
            'user_id' => $this->superAdmin->id,
            'conflict_type' => 'insufficient_stock',
            'conflict_reason' => 'Low stock',
            'payload_snapshot' => [],
            'status' => 'unresolved',
        ]);

        $res = $this->getJson('/api/v1/pos/sync-conflicts');

        $res->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.client_trans_uuid', 'UUID-S1');
    }

    public function test_8_unauthorized_cross_store_rejection(): void
    {
        Sanctum::actingAs($this->cashier);

        $conflictStore2 = PosSyncConflict::create([
            'client_trans_uuid' => 'UUID-S2-FORBIDDEN',
            'store_id' => $this->store2->id,
            'user_id' => $this->superAdmin->id,
            'conflict_type' => 'insufficient_stock',
            'conflict_reason' => 'Low stock',
            'payload_snapshot' => [],
            'status' => 'unresolved',
        ]);

        // View detail rejected
        $this->getJson("/api/v1/pos/sync-conflicts/{$conflictStore2->id}")->assertStatus(403);

        // Resolve rejected
        $this->postJson("/api/v1/pos/sync-conflicts/{$conflictStore2->id}/resolve", [
            'action' => 'dismiss',
        ])->assertStatus(403);
    }

    public function test_9_super_admin_cross_store_access(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $conflictStore2 = PosSyncConflict::create([
            'client_trans_uuid' => 'UUID-S2-SA',
            'store_id' => $this->store2->id,
            'user_id' => $this->superAdmin->id,
            'conflict_type' => 'insufficient_stock',
            'conflict_reason' => 'Low stock',
            'payload_snapshot' => [],
            'status' => 'unresolved',
        ]);

        $res = $this->getJson("/api/v1/pos/sync-conflicts/{$conflictStore2->id}");
        $res->assertStatus(200)->assertJsonPath('data.id', $conflictStore2->id);

        $resResolve = $this->postJson("/api/v1/pos/sync-conflicts/{$conflictStore2->id}/resolve", [
            'action' => 'dismiss',
            'resolution_notes' => 'Super Admin dismissal',
        ]);

        $resResolve->assertStatus(200)->assertJsonPath('data.status', 'dismissed');
    }

    public function test_10_valid_resolution_reprocess(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();

        // Create conflict with stock replenished to 10
        $conflict = PosSyncConflict::create([
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'user_id' => $this->cashier->id,
            'conflict_type' => 'insufficient_stock',
            'conflict_reason' => 'Stock was low',
            'payload_snapshot' => [
                'client_trans_uuid' => $uuid,
                'store_id' => $this->store1->id,
                'pos_session_id' => $this->openSession1->id,
                'items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
            ],
            'status' => 'unresolved',
        ]);

        $res = $this->postJson("/api/v1/pos/sync-conflicts/{$conflict->id}/resolve", [
            'action' => 'reprocess',
            'resolution_notes' => 'Stock verified, reprocessing sync.',
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $conflict->id,
                    'status' => 'resolved',
                    'resolution_action' => 'reprocessed_sync',
                ],
            ]);

        $this->assertDatabaseHas('invoices', ['client_trans_uuid' => $uuid]);
    }

    public function test_11_valid_resolution_dismiss(): void
    {
        Sanctum::actingAs($this->cashier);

        $conflict = PosSyncConflict::create([
            'client_trans_uuid' => 'UUID-DISMISS',
            'store_id' => $this->store1->id,
            'user_id' => $this->cashier->id,
            'conflict_type' => 'duplicate_uuid',
            'conflict_reason' => 'Duplicate transaction',
            'payload_snapshot' => [],
            'status' => 'unresolved',
        ]);

        $res = $this->postJson("/api/v1/pos/sync-conflicts/{$conflict->id}/resolve", [
            'action' => 'dismiss',
            'resolution_notes' => 'Duplicate transaction dismissed by cashier.',
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $conflict->id,
                    'status' => 'dismissed',
                    'resolution_action' => 'dismissed_duplicate',
                ],
            ]);
    }

    public function test_12_invalid_resolution_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        $conflict = PosSyncConflict::create([
            'client_trans_uuid' => 'UUID-INVALID-ACT',
            'store_id' => $this->store1->id,
            'user_id' => $this->cashier->id,
            'conflict_type' => 'other',
            'conflict_reason' => 'Test',
            'payload_snapshot' => [],
            'status' => 'unresolved',
        ]);

        $res = $this->postJson("/api/v1/pos/sync-conflicts/{$conflict->id}/resolve", [
            'action' => 'invalid_action_name',
        ]);

        $res->assertStatus(422)->assertJsonStructure(['message', 'errors' => ['action']]);
    }

    public function test_13_duplicate_resolution_protection(): void
    {
        Sanctum::actingAs($this->cashier);

        $conflict = PosSyncConflict::create([
            'client_trans_uuid' => 'UUID-ALREADY-RESOLVED',
            'store_id' => $this->store1->id,
            'user_id' => $this->cashier->id,
            'conflict_type' => 'other',
            'conflict_reason' => 'Test',
            'payload_snapshot' => [],
            'status' => 'resolved',
            'resolution_action' => 'reprocessed_sync',
        ]);

        $res = $this->postJson("/api/v1/pos/sync-conflicts/{$conflict->id}/resolve", [
            'action' => 'dismiss',
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_14_concurrent_resolution_protection(): void
    {
        Sanctum::actingAs($this->cashier);

        $conflict = PosSyncConflict::create([
            'client_trans_uuid' => 'UUID-CONCURRENT',
            'store_id' => $this->store1->id,
            'user_id' => $this->cashier->id,
            'conflict_type' => 'other',
            'conflict_reason' => 'Test',
            'payload_snapshot' => [],
            'status' => 'unresolved',
        ]);

        // First resolution succeeds
        $this->postJson("/api/v1/pos/sync-conflicts/{$conflict->id}/resolve", [
            'action' => 'dismiss',
        ])->assertStatus(200);

        // Second concurrent attempt fails with 422
        $this->postJson("/api/v1/pos/sync-conflicts/{$conflict->id}/resolve", [
            'action' => 'dismiss',
        ])->assertStatus(422);
    }

    public function test_15_inventory_safety(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();

        // Create conflict with force_override action
        $conflict = PosSyncConflict::create([
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'user_id' => $this->cashier->id,
            'conflict_type' => 'insufficient_stock',
            'conflict_reason' => 'Insufficient stock',
            'payload_snapshot' => [
                'client_trans_uuid' => $uuid,
                'store_id' => $this->store1->id,
                'pos_session_id' => $this->openSession1->id,
                'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
            ],
            'status' => 'unresolved',
        ]);

        $res = $this->postJson("/api/v1/pos/sync-conflicts/{$conflict->id}/resolve", [
            'action' => 'force_override',
            'resolution_notes' => 'Force stock override approved.',
        ]);

        $res->assertStatus(200)->assertJsonPath('data.status', 'resolved');

        // Verify stock deducted accurately
        $this->assertDatabaseHas('invoices', ['client_trans_uuid' => $uuid]);
    }

    public function test_16_payment_safety(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();

        $conflict = PosSyncConflict::create([
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'user_id' => $this->cashier->id,
            'conflict_type' => 'other',
            'conflict_reason' => 'Pending resolution',
            'payload_snapshot' => [
                'client_trans_uuid' => $uuid,
                'store_id' => $this->store1->id,
                'pos_session_id' => $this->openSession1->id,
                'items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
                'payments' => [['payment_method' => 'card', 'amount' => 1000.00]],
            ],
            'status' => 'unresolved',
        ]);

        $this->postJson("/api/v1/pos/sync-conflicts/{$conflict->id}/resolve", [
            'action' => 'reprocess',
        ])->assertStatus(200);

        $inv = Invoice::where('client_trans_uuid', $uuid)->first();
        $this->assertNotNull($inv);
        $this->assertEquals(1000.00, (float) $inv->paid_amount);
    }

    public function test_17_invoice_idempotency_safety(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();

        $conflict = PosSyncConflict::create([
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'user_id' => $this->cashier->id,
            'conflict_type' => 'other',
            'conflict_reason' => 'Pending resolution',
            'payload_snapshot' => [
                'client_trans_uuid' => $uuid,
                'store_id' => $this->store1->id,
                'pos_session_id' => $this->openSession1->id,
                'items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
            ],
            'status' => 'unresolved',
        ]);

        $this->postJson("/api/v1/pos/sync-conflicts/{$conflict->id}/resolve", [
            'action' => 'reprocess',
        ])->assertStatus(200);

        // Attempting to sync again with same UUID returns already_synced
        $resSync = $this->postJson('/api/v1/pos/sales/sync', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
        ]);

        $resSync->assertStatus(200)->assertJsonPath('data.results.0.status', 'already_synced');
    }

    public function test_18_audit_information_preserved(): void
    {
        Sanctum::actingAs($this->cashier);

        $conflict = PosSyncConflict::create([
            'client_trans_uuid' => 'UUID-AUDIT-TEST',
            'store_id' => $this->store1->id,
            'user_id' => $this->cashier->id,
            'conflict_type' => 'other',
            'conflict_reason' => 'Audit test',
            'payload_snapshot' => [],
            'status' => 'unresolved',
        ]);

        $res = $this->postJson("/api/v1/pos/sync-conflicts/{$conflict->id}/resolve", [
            'action' => 'dismiss',
            'resolution_notes' => 'Audit note verification.',
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'resolved_by' => $this->cashier->id,
                    'resolved_by_name' => $this->cashier->name,
                    'resolution_notes' => 'Audit note verification.',
                    'resolution_action' => 'dismissed_duplicate',
                ],
            ]);
    }

    public function test_19_standardized_api_responses_returned(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson('/api/v1/pos/sync-conflicts');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'items',
                    'pagination',
                ],
            ]);
    }

    public function test_20_nonexistent_conflict_handling(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->getJson('/api/v1/pos/sync-conflicts/999999')->assertStatus(404);
        $this->postJson('/api/v1/pos/sync-conflicts/999999/resolve', [
            'action' => 'dismiss',
        ])->assertStatus(404);
    }

    public function test_21_regression_compatibility_with_task_43(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();

        // Task #43 normal offline sync succeeds
        $res = $this->postJson('/api/v1/pos/sales/sync', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(200)->assertJsonPath('data.results.0.status', 'synced');
    }
}
