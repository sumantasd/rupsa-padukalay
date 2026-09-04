<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Invoice;
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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosBillingTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $cashier;
    protected User $noPermUser;
    protected Store $store1;
    protected Store $store2;
    protected Customer $customer;
    protected ProductVariantSize $variantSize1;
    protected ProductVariantSize $variantSize2;
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
        $colorBrown = Color::create(['name' => 'Brown', 'code' => 'BRN']);
        $size8 = Size::create(['size_number' => '08', 'size_system' => 'UK', 'sort_order' => 8]);

        $product = Product::create([
            'article_number' => 'ART805',
            'name' => 'Men Formal Shoe',
            'slug' => 'art805-men-formal-shoe',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
        ]);

        $variant1 = ProductVariant::create(['product_id' => $product->id, 'color_id' => $colorBlack->id]);
        $variant2 = ProductVariant::create(['product_id' => $product->id, 'color_id' => $colorBrown->id]);

        $this->variantSize1 = ProductVariantSize::create([
            'product_variant_id' => $variant1->id,
            'size_id' => $size8->id,
            'sku' => 'ART805-BLK-08',
            'barcode' => '8901234567890',
            'cost_price' => 500.00,
            'mrp' => 1299.00,
            'selling_price' => 999.00,
        ]);

        $this->variantSize2 = ProductVariantSize::create([
            'product_variant_id' => $variant2->id,
            'size_id' => $size8->id,
            'sku' => 'ART805-BRN-08',
            'barcode' => '8901234567891',
            'cost_price' => 550.00,
            'mrp' => 1399.00,
            'selling_price' => 1099.00,
        ]);

        // Physical inventory stocks
        InventoryStock::create(['product_variant_size_id' => $this->variantSize1->id, 'store_id' => $this->store1->id, 'stock_quantity' => 20]);
        InventoryStock::create(['product_variant_size_id' => $this->variantSize2->id, 'store_id' => $this->store1->id, 'stock_quantity' => 15]);

        // Open POS Session for cashier
        $this->openSession1 = PosSession::create([
            'store_id' => $this->store1->id,
            'user_id' => $this->cashier->id,
            'opened_at' => now(),
            'opening_cash' => 1000.00,
            'closing_cash_system' => 1000.00,
            'status' => 'open',
        ]);
    }

    public function test_1_unauthenticated_user_rejected(): void
    {
        $this->postJson('/api/v1/pos/sales', [])->assertStatus(401);
    }

    public function test_2_user_without_required_permission_rejected(): void
    {
        Sanctum::actingAs($this->noPermUser);
        $this->postJson('/api/v1/pos/sales', [
            'store_id' => $this->store1->id,
            'items' => [['sku' => 'ART805-BLK-08', 'quantity' => 1]],
        ])->assertStatus(403);
    }

    public function test_3_authorized_user_can_create_pos_sale(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson('/api/v1/pos/sales', [
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'items' => [
                [
                    'sku' => 'ART805-BLK-08',
                    'quantity' => 2,
                ],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'store_id' => $this->store1->id,
                    'customer_id' => $this->customer->id,
                    'subtotal' => 1998.00,
                    'grand_total' => 1998.00,
                    'payment_status' => 'paid',
                    'status' => 'completed',
                    'items' => [
                        [
                            'sku' => 'ART805-BLK-08',
                            'quantity' => 2,
                            'unit_price' => 999.00,
                        ],
                    ],
                ],
            ]);
    }

    public function test_4_sale_requires_active_pos_session(): void
    {
        $cashierNoSession = User::create([
            'name' => 'No Session Cashier',
            'username' => 'nosess_cashier',
            'email' => 'nosess@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);
        $cashierNoSession->roles()->attach(Role::where('name', 'Cashier')->first()->id, ['model_type' => User::class]);
        $cashierNoSession->stores()->attach($this->store1->id);

        Sanctum::actingAs($cashierNoSession);

        $this->postJson('/api/v1/pos/sales', [
            'store_id' => $this->store1->id,
            'items' => [['sku' => 'ART805-BLK-08', 'quantity' => 1]],
        ])->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_5_sku_lookup_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson('/api/v1/pos/sales', [
            'store_id' => $this->store1->id,
            'items' => [
                ['sku' => 'ART805-BRN-08', 'quantity' => 1],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'data' => [
                    'items' => [
                        ['sku' => 'ART805-BRN-08', 'unit_price' => 1099.00],
                    ],
                ],
            ]);
    }

    public function test_6_multiple_sale_items_work(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson('/api/v1/pos/sales', [
            'store_id' => $this->store1->id,
            'items' => [
                ['sku' => 'ART805-BLK-08', 'quantity' => 1],
                ['sku' => 'ART805-BRN-08', 'quantity' => 2],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'data' => [
                    'subtotal' => 3197.00, // (999 * 1) + (1099 * 2) = 999 + 2198 = 3197
                    'grand_total' => 3197.00,
                ],
            ]);
    }

    public function test_7_server_side_totals_are_correct(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson('/api/v1/pos/sales', [
            'store_id' => $this->store1->id,
            'discount_amount' => 100.00,
            'items' => [
                ['sku' => 'ART805-BLK-08', 'quantity' => 2, 'unit_price' => 1000.00, 'discount_amount' => 50.00],
            ],
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'data' => [
                    'subtotal' => 2000.00,
                    'discount_amount' => 150.00, // 50 line + 100 overall
                    'grand_total' => 1850.00, // 2000 - 150
                ],
            ]);
    }

    public function test_8_insufficient_stock_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        // Attempting to sell 25 units when physical stock is only 20
        $res = $this->postJson('/api/v1/pos/sales', [
            'store_id' => $this->store1->id,
            'items' => [
                ['sku' => 'ART805-BLK-08', 'quantity' => 25],
            ],
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_9_inventory_is_deducted_correctly(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->postJson('/api/v1/pos/sales', [
            'store_id' => $this->store1->id,
            'items' => [
                ['sku' => 'ART805-BLK-08', 'quantity' => 5],
            ],
        ])->assertStatus(201);

        // Physical stock check: 20 - 5 = 15
        $stock = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)
            ->where('store_id', $this->store1->id)
            ->first();
        $this->assertEquals(15, $stock->stock_quantity);
    }

    public function test_10_sale_pos_movement_ledger_is_created(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->postJson('/api/v1/pos/sales', [
            'store_id' => $this->store1->id,
            'items' => [
                ['sku' => 'ART805-BLK-08', 'quantity' => 3],
            ],
        ])->assertStatus(201);

        $movement = StockMovement::where('product_variant_size_id', $this->variantSize1->id)->first();
        $this->assertNotNull($movement);
        $this->assertEquals('sale_pos', is_object($movement->movement_type) ? $movement->movement_type->value : $movement->movement_type);
        $this->assertEquals(-3, $movement->quantity_change);
    }

    public function test_11_stock_before_stock_after_are_correct(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->postJson('/api/v1/pos/sales', [
            'store_id' => $this->store1->id,
            'items' => [
                ['sku' => 'ART805-BLK-08', 'quantity' => 4],
            ],
        ])->assertStatus(201);

        $movement = StockMovement::where('product_variant_size_id', $this->variantSize1->id)->first();
        $this->assertEquals(20, $movement->stock_before);
        $this->assertEquals(16, $movement->stock_after);
    }

    public function test_12_atomic_rollback_when_one_line_fails(): void
    {
        Sanctum::actingAs($this->cashier);

        // 1 valid item (2 units) + 1 invalid item (30 units > 15 available stock)
        $res = $this->postJson('/api/v1/pos/sales', [
            'store_id' => $this->store1->id,
            'items' => [
                ['sku' => 'ART805-BLK-08', 'quantity' => 2],
                ['sku' => 'ART805-BRN-08', 'quantity' => 30],
            ],
        ]);

        $res->assertStatus(422);

        // Atomic rollback check: stocks remain 20 and 15
        $stock1 = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first();
        $stock2 = InventoryStock::where('product_variant_size_id', $this->variantSize2->id)->first();
        $this->assertEquals(20, $stock1->stock_quantity);
        $this->assertEquals(15, $stock2->stock_quantity);
        $this->assertEquals(0, Invoice::count());
        $this->assertEquals(0, StockMovement::count());
    }

    public function test_13_unauthorized_store_sale_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        // cashier is attached ONLY to store1, NOT store2
        $this->postJson('/api/v1/pos/sales', [
            'store_id' => $this->store2->id,
            'items' => [['sku' => 'ART805-BLK-08', 'quantity' => 1]],
        ])->assertStatus(422);
    }

    public function test_14_super_admin_can_operate_across_stores(): void
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

        InventoryStock::create(['product_variant_size_id' => $this->variantSize1->id, 'store_id' => $this->store2->id, 'stock_quantity' => 10]);

        $res = $this->postJson('/api/v1/pos/sales', [
            'store_id' => $this->store2->id,
            'items' => [['sku' => 'ART805-BLK-08', 'quantity' => 2]],
        ]);

        $res->assertStatus(201)->assertJson(['success' => true]);
    }

    public function test_15_customer_linking_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson('/api/v1/pos/sales', [
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'items' => [['sku' => 'ART805-BLK-08', 'quantity' => 1]],
        ]);

        $res->assertStatus(201)
            ->assertJson([
                'data' => [
                    'customer_id' => $this->customer->id,
                    'customer_name' => 'Rajesh Kumar',
                    'customer_mobile' => '9831098310',
                ],
            ]);
    }

    public function test_16_sale_listing_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->postJson('/api/v1/pos/sales', [
            'store_id' => $this->store1->id,
            'items' => [['sku' => 'ART805-BLK-08', 'quantity' => 1]],
        ])->assertStatus(201);

        $this->getJson('/api/v1/pos/sales')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_17_sale_filtering_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->postJson('/api/v1/pos/sales', [
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'items' => [['sku' => 'ART805-BLK-08', 'quantity' => 1]],
        ])->assertStatus(201);

        $this->getJson("/api/v1/pos/sales?customer_id={$this->customer->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_18_sale_detail_works(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson('/api/v1/pos/sales', [
            'store_id' => $this->store1->id,
            'items' => [['sku' => 'ART805-BLK-08', 'quantity' => 1]],
        ])->assertStatus(201);

        $saleId = $res->json('data.id');

        $this->getJson("/api/v1/pos/sales/{$saleId}")
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['id' => $saleId]]);
    }

    public function test_19_nonexistent_sale_returns_404(): void
    {
        Sanctum::actingAs($this->cashier);

        $this->getJson('/api/v1/pos/sales/99999')->assertStatus(404);
    }

    public function test_20_closed_invalid_pos_session_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        // Close the active session
        $this->openSession1->status = 'closed';
        $this->openSession1->save();

        $this->postJson('/api/v1/pos/sales', [
            'store_id' => $this->store1->id,
            'items' => [['sku' => 'ART805-BLK-08', 'quantity' => 1]],
        ])->assertStatus(422);
    }

    public function test_21_concurrent_row_lock_sensitive_stock_protection(): void
    {
        Sanctum::actingAs($this->cashier);

        // Requesting exact stock amount (20)
        $res = $this->postJson('/api/v1/pos/sales', [
            'store_id' => $this->store1->id,
            'items' => [['sku' => 'ART805-BLK-08', 'quantity' => 20]],
        ]);

        $res->assertStatus(201);

        // Next request for 1 unit must fail due to zero remaining stock
        $this->postJson('/api/v1/pos/sales', [
            'store_id' => $this->store1->id,
            'items' => [['sku' => 'ART805-BLK-08', 'quantity' => 1]],
        ])->assertStatus(422);
    }

    public function test_22_standardized_api_response_maintained(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson('/api/v1/pos/sales');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'items',
                    'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ]);
    }
}
