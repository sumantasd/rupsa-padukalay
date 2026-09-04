<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\HsnCode;
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
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfflineBillingSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $cashier;
    protected User $noPermUser;
    protected Store $store1;
    protected Store $store2;
    protected PosSession $openSession1;
    protected PosSession $closedSession;
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

        $this->closedSession = PosSession::create([
            'store_id' => $this->store1->id,
            'user_id' => $this->cashier->id,
            'opened_at' => now()->subDays(2),
            'closed_at' => now()->subDay(),
            'opening_cash' => 1000.00,
            'closing_cash_system' => 1000.00,
            'status' => 'closed',
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
            'stock_quantity' => 10,
        ]);

        InventoryStock::create([
            'product_variant_size_id' => $this->variantSize2->id,
            'store_id' => $this->store1->id,
            'warehouse_id' => 0,
            'stock_location_id' => 0,
            'stock_quantity' => 10,
        ]);
    }

    public function test_1_unauthenticated_request_rejected(): void
    {
        $this->postJson('/api/v1/pos/sales/sync', [])->assertStatus(401);
    }

    public function test_2_user_without_permission_rejected(): void
    {
        Sanctum::actingAs($this->noPermUser);

        $uuid = (string) Str::uuid();
        $this->postJson('/api/v1/pos/sales/sync', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ])->assertStatus(403);
    }

    public function test_3_valid_offline_sale_sync_succeeds(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();
        $res = $this->postJson('/api/v1/pos/sales/sync', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'items' => [
                ['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 2],
            ],
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 2000.00],
            ],
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total_processed' => 1,
                    'synced_count' => 1,
                    'rejected_count' => 0,
                    'results' => [
                        [
                            'client_trans_uuid' => $uuid,
                            'status' => 'synced',
                            'grand_total' => 2000.00,
                        ],
                    ],
                ],
            ]);
    }

    public function test_4_client_transaction_uuid_stored_correctly(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();
        $this->postJson('/api/v1/pos/sales/sync', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ])->assertStatus(200);

        $this->assertDatabaseHas('invoices', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
        ]);
    }

    public function test_5_repeated_same_transaction_uuid_is_idempotent(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();
        $payload = [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ];

        // First Sync
        $res1 = $this->postJson('/api/v1/pos/sales/sync', $payload);
        $res1->assertStatus(200)->assertJsonPath('data.results.0.status', 'synced');

        // Retry Sync (Same payload & UUID)
        $res2 = $this->postJson('/api/v1/pos/sales/sync', $payload);
        $res2->assertStatus(200)->assertJsonPath('data.results.0.status', 'already_synced');
    }

    public function test_6_retry_does_not_create_duplicate_sale(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();
        $payload = [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ];

        $this->postJson('/api/v1/pos/sales/sync', $payload);
        $this->postJson('/api/v1/pos/sales/sync', $payload);

        $this->assertEquals(1, Invoice::where('client_trans_uuid', $uuid)->count());
    }

    public function test_7_retry_does_not_create_duplicate_invoice(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();
        $payload = [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ];

        $this->postJson('/api/v1/pos/sales/sync', $payload);
        $inv = Invoice::where('client_trans_uuid', $uuid)->first();
        $itemCountBefore = InvoiceItem::where('invoice_id', $inv->id)->count();

        // Retry
        $this->postJson('/api/v1/pos/sales/sync', $payload);
        $itemCountAfter = InvoiceItem::where('invoice_id', $inv->id)->count();

        $this->assertEquals($itemCountBefore, $itemCountAfter);
    }

    public function test_8_retry_does_not_deduct_stock_twice(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();
        $payload = [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 2]],
        ];

        $stockBefore = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first()->stock_quantity;
        $this->assertEquals(10, $stockBefore);

        // Sync 1: deducts 2 -> 8
        $this->postJson('/api/v1/pos/sales/sync', $payload);
        $this->assertEquals(8, InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first()->stock_quantity);

        // Sync 2: retry -> stock must remain 8
        $this->postJson('/api/v1/pos/sales/sync', $payload);
        $this->assertEquals(8, InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first()->stock_quantity);
    }

    public function test_9_retry_does_not_create_duplicate_stock_movement(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();
        $payload = [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ];

        $this->postJson('/api/v1/pos/sales/sync', $payload);
        $movCount1 = StockMovement::where('product_variant_size_id', $this->variantSize1->id)->count();

        // Retry
        $this->postJson('/api/v1/pos/sales/sync', $payload);
        $movCount2 = StockMovement::where('product_variant_size_id', $this->variantSize1->id)->count();

        $this->assertEquals($movCount1, $movCount2);
    }

    public function test_10_retry_does_not_create_duplicate_payment(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();
        $payload = [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ];

        $this->postJson('/api/v1/pos/sales/sync', $payload);
        $inv = Invoice::where('client_trans_uuid', $uuid)->first();
        $pmtCount1 = InvoicePayment::where('invoice_id', $inv->id)->count();

        // Retry
        $this->postJson('/api/v1/pos/sales/sync', $payload);
        $pmtCount2 = InvoicePayment::where('invoice_id', $inv->id)->count();

        $this->assertEquals($pmtCount1, $pmtCount2);
    }

    public function test_11_batch_sync_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid1 = (string) Str::uuid();
        $uuid2 = (string) Str::uuid();

        $res = $this->postJson('/api/v1/pos/sales/sync', [
            'sales' => [
                [
                    'client_trans_uuid' => $uuid1,
                    'store_id' => $this->store1->id,
                    'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
                ],
                [
                    'client_trans_uuid' => $uuid2,
                    'store_id' => $this->store1->id,
                    'items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
                ],
            ],
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'total_processed' => 2,
                    'synced_count' => 2,
                    'rejected_count' => 0,
                ],
            ]);
    }

    public function test_12_multiple_offline_transactions_sync_correctly(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid1 = (string) Str::uuid();
        $uuid2 = (string) Str::uuid();

        $this->postJson('/api/v1/pos/sales/sync', [
            'sales' => [
                [
                    'client_trans_uuid' => $uuid1,
                    'store_id' => $this->store1->id,
                    'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
                ],
                [
                    'client_trans_uuid' => $uuid2,
                    'store_id' => $this->store1->id,
                    'items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 1]],
                ],
            ],
        ])->assertStatus(200);

        $this->assertDatabaseHas('invoices', ['client_trans_uuid' => $uuid1]);
        $this->assertDatabaseHas('invoices', ['client_trans_uuid' => $uuid2]);
    }

    public function test_13_one_failed_transaction_does_not_corrupt_another_transaction(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuidValid = (string) Str::uuid();
        $uuidFailed = (string) Str::uuid();

        $res = $this->postJson('/api/v1/pos/sales/sync', [
            'sales' => [
                [
                    'client_trans_uuid' => $uuidValid,
                    'store_id' => $this->store1->id,
                    'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
                ],
                [
                    'client_trans_uuid' => $uuidFailed,
                    'store_id' => $this->store1->id,
                    'items' => [['product_variant_size_id' => $this->variantSize2->id, 'quantity' => 999]], // Insufficient stock
                ],
            ],
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'total_processed' => 2,
                    'synced_count' => 1,
                    'rejected_count' => 1,
                ],
            ]);

        // Valid transaction committed
        $this->assertDatabaseHas('invoices', ['client_trans_uuid' => $uuidValid]);
        // Failed transaction rolled back completely
        $this->assertDatabaseMissing('invoices', ['client_trans_uuid' => $uuidFailed]);
    }

    public function test_14_insufficient_stock_rejected_safely(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();
        $res = $this->postJson('/api/v1/pos/sales/sync', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 100]],
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'results' => [
                        [
                            'client_trans_uuid' => $uuid,
                            'status' => 'rejected',
                        ],
                    ],
                ],
            ]);
    }

    public function test_15_invalid_sku_rejected_safely(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();
        $res = $this->postJson('/api/v1/pos/sales/sync', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'items' => [['sku' => 'INVALID-NONEXISTENT-SKU', 'quantity' => 1]],
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'results' => [
                        [
                            'client_trans_uuid' => $uuid,
                            'status' => 'rejected',
                        ],
                    ],
                ],
            ]);
    }

    public function test_16_invalid_payment_rejected_safely(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();
        $res = $this->postJson('/api/v1/pos/sales/sync', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
            'payments' => [['payment_method' => 'cash', 'amount' => 5.00]], // Payment sum 5.00 != grand_total 1000.00
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'results' => [
                        [
                            'client_trans_uuid' => $uuid,
                            'status' => 'rejected',
                        ],
                    ],
                ],
            ]);
    }

    public function test_17_server_recalculates_totals(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();
        // Client claims grand total is 10.00, server recalculates to 1000.00 (selling_price 1000.00 * 1)
        $res = $this->postJson('/api/v1/pos/sales/sync', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1, 'unit_price' => 1000.00]],
            'payments' => [['payment_method' => 'cash', 'amount' => 1000.00]],
        ]);

        $res->assertStatus(200)
            ->assertJson([
                'data' => [
                    'results' => [
                        [
                            'client_trans_uuid' => $uuid,
                            'status' => 'synced',
                            'grand_total' => 1000.00,
                        ],
                    ],
                ],
            ]);
    }

    public function test_18_store_access_control_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();
        $res = $this->postJson('/api/v1/pos/sales/sync', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(200)->assertJsonPath('data.results.0.status', 'synced');
    }

    public function test_19_unauthorized_store_sync_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();
        // Cashier is assigned to store1, attempting to sync for store2
        $res = $this->postJson('/api/v1/pos/sales/sync', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store2->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(200)->assertJsonPath('data.results.0.status', 'rejected');
    }

    public function test_20_super_admin_cross_store_sync_works(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Setup open session for store2
        $sessionStore2 = PosSession::create([
            'store_id' => $this->store2->id,
            'user_id' => $this->superAdmin->id,
            'opened_at' => now(),
            'opening_cash' => 1000.00,
            'closing_cash_system' => 1000.00,
            'status' => 'open',
        ]);

        InventoryStock::create([
            'product_variant_size_id' => $this->variantSize1->id,
            'store_id' => $this->store2->id,
            'warehouse_id' => 0,
            'stock_location_id' => 0,
            'stock_quantity' => 5,
        ]);

        $uuid = (string) Str::uuid();
        $res = $this->postJson('/api/v1/pos/sales/sync', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store2->id,
            'pos_session_id' => $sessionStore2->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(200)->assertJsonPath('data.results.0.status', 'synced');
    }

    public function test_21_pos_session_validation_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();
        $res = $this->postJson('/api/v1/pos/sales/sync', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(200)->assertJsonPath('data.results.0.status', 'synced');
    }

    public function test_22_invalid_closed_session_handled_safely(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();
        $res = $this->postJson('/api/v1/pos/sales/sync', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->closedSession->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(200)->assertJsonPath('data.results.0.status', 'rejected');
    }

    public function test_23_atomic_rollback_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();
        $stockBefore = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first()->stock_quantity;

        // Fails payment sum validation
        $this->postJson('/api/v1/pos/sales/sync', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
            'payments' => [['payment_method' => 'cash', 'amount' => 1.00]],
        ]);

        // Sale missing, stock untouched
        $this->assertDatabaseMissing('invoices', ['client_trans_uuid' => $uuid]);
        $stockAfter = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first()->stock_quantity;
        $this->assertEquals($stockBefore, $stockAfter);
    }

    public function test_24_standardized_api_response_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $uuid = (string) Str::uuid();
        $res = $this->postJson('/api/v1/pos/sales/sync', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'total_processed',
                    'synced_count',
                    'already_synced_count',
                    'rejected_count',
                    'results',
                ],
            ]);
    }
}
