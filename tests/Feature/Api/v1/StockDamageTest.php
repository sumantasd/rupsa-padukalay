<?php

namespace Tests\Feature\Api\v1;

use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\InventoryStock;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\Role;
use App\Models\Size;
use App\Models\StockDamageItem;
use App\Models\StockDamageTransaction;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockDamageTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;
    protected Product $product;
    protected ProductVariant $variant;
    protected Size $size6;
    protected Size $size7;
    protected ProductVariantSize $pvs6;
    protected ProductVariantSize $pvs7;

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
            'username' => 'damage_tester',
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
            'products.view', 'products.edit',
            'inventory.view', 'inventory.adjust',
            'inventory.damage.view', 'inventory.damage.create', 'inventory.damage.edit', 'inventory.damage.delete',
        ];

        foreach ($permissions as $p) {
            $perm = Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web'], [
                'display_name' => ucfirst($p),
                'module_group' => 'inventory',
            ]);
            $role->permissions()->syncWithoutDetaching([$perm->id]);
        }

        DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_id' => $this->user->id,
            'model_type' => User::class,
        ]);

        $cat = Category::create(['name' => 'Sneakers', 'slug' => 'sneakers']);
        $brand = \App\Models\Brand::create(['name' => 'Apex', 'slug' => 'apex']);

        $this->product = Product::create([
            'name' => 'Urban Flex Casual Sneaker',
            'slug' => 'urban-flex-casual-sneaker',
            'article_number' => 'RP-610',
            'brand' => 'Apex',
            'brand_id' => $brand->id,
            'category' => 'Sneakers',
            'category_id' => $cat->id,
            'is_active' => true,
        ]);

        $color = \App\Models\Color::create(['name' => 'White/Blue', 'code' => '#FFFFFF']);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'color_id' => $color->id,
            'color_name' => 'White/Blue',
            'sku' => 'RP-610-WB',
        ]);

        $this->size6 = Size::create(['name' => 'IND 6', 'code' => 'IND-6', 'size_number' => '6']);
        $this->size7 = Size::create(['name' => 'IND 7', 'code' => 'IND-7', 'size_number' => '7']);

        $this->pvs6 = ProductVariantSize::create([
            'product_variant_id' => $this->variant->id,
            'size_id' => $this->size6->id,
            'sku' => 'RP-610-WB-6',
            'retail_price' => 1500.00,
        ]);

        $this->pvs7 = ProductVariantSize::create([
            'product_variant_id' => $this->variant->id,
            'size_id' => $this->size7->id,
            'sku' => 'RP-610-WB-7',
            'retail_price' => 1500.00,
        ]);

        // Create initial inventory: IND 6 = 10 pairs, IND 7 = 8 pairs
        InventoryStock::create([
            'product_variant_size_id' => $this->pvs6->id,
            'store_id' => $this->store->id,
            'warehouse_id' => 0,
            'stock_location_id' => 0,
            'stock_quantity' => 10,
        ]);

        InventoryStock::create([
            'product_variant_size_id' => $this->pvs7->id,
            'store_id' => $this->store->id,
            'warehouse_id' => 0,
            'stock_location_id' => 0,
            'stock_quantity' => 8,
        ]);
    }

    /** @test */
    public function search_existing_article_number_returns_product(): void
    {
        $res = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/damage/product-search?q=RP-610&store_id=' . $this->store->id);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.name', 'Urban Flex Casual Sneaker')
            ->assertJsonPath('data.0.article_number', 'RP-610');
    }

    /** @test */
    public function search_by_sku_returns_correct_product(): void
    {
        $res = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/damage/product-search?q=RP-610-WB-6&store_id=' . $this->store->id);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.article_number', 'RP-610');
    }

    /** @test */
    public function search_by_barcode_returns_correct_product(): void
    {
        $this->pvs6->update(['barcode' => '8901234567890']);

        $res = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/damage/product-search?q=8901234567890&store_id=' . $this->store->id);

        $res->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.article_number', 'RP-610');
    }

    /** @test */
    public function search_returns_size_wise_inventory_for_selected_store(): void
    {
        $res = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/damage/product-search?q=RP-610&store_id=' . $this->store->id);

        $res->assertStatus(200);
        $sizes = $res->json('data.0.sizes');

        $this->assertCount(2, $sizes);
        $this->assertEquals(10, $sizes[0]['available_stock']);
        $this->assertEquals(8, $sizes[1]['available_stock']);
    }

    /** @test */
    public function product_with_zero_stock_is_handled_correctly(): void
    {
        // Zero out stock for pvs6
        InventoryStock::where('product_variant_size_id', $this->pvs6->id)
            ->where('store_id', $this->store->id)
            ->update(['stock_quantity' => 0]);

        $res = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/damage/product-search?q=RP-610&store_id=' . $this->store->id);

        $res->assertStatus(200);
        $sizes = $res->json('data.0.sizes');
        $this->assertEquals(0, $sizes[0]['available_stock']);
    }

    /** @test */
    public function search_does_not_return_inventory_belonging_to_another_store(): void
    {
        $store2 = Store::create([
            'code' => 'STR-002',
            'name' => 'Secondary Outlet',
            'phone' => '9876543211',
            'is_active' => true,
        ]);

        // Add 50 pairs to Store 2
        InventoryStock::create([
            'product_variant_size_id' => $this->pvs6->id,
            'store_id' => $store2->id,
            'warehouse_id' => 0,
            'stock_location_id' => 0,
            'stock_quantity' => 50,
        ]);

        // Query for Store 1 (should return Store 1 stock = 10, NOT Store 2 stock = 50)
        $resStore1 = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/damage/product-search?q=RP-610&store_id=' . $this->store->id);

        $resStore1->assertStatus(200);
        $this->assertEquals(10, $resStore1->json('data.0.sizes.0.available_stock'));

        // Query for Store 2 (should return Store 2 stock = 50)
        $resStore2 = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/damage/product-search?q=RP-610&store_id=' . $store2->id);

        $resStore2->assertStatus(200);
        $this->assertEquals(50, $resStore2->json('data.0.sizes.0.available_stock'));
    }

    /** @test */
    public function single_size_stock_damage_deducts_stock_and_creates_records(): void
    {
        // Damage 2 pairs of IND 7 (Available: 8)
        $res = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/damage', [
                'store_id' => $this->store->id,
                'reason' => 'damaged',
                'remarks' => 'Water damage in warehouse',
                'items' => [
                    [
                        'product_variant_size_id' => $this->pvs7->id,
                        'quantity' => 2,
                    ],
                ],
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_quantity', 2);

        // Assert Stock Deducted: 8 - 2 = 6
        $stock = InventoryStock::where('product_variant_size_id', $this->pvs7->id)
            ->where('store_id', $this->store->id)
            ->first();
        $this->assertEquals(6, $stock->stock_quantity);

        // Assert Damage Transaction Created
        $this->assertDatabaseHas('stock_damage_transactions', [
            'store_id' => $this->store->id,
            'reason' => 'damaged',
            'total_quantity' => 2,
        ]);

        // Assert Stock Movement Created with STOCK_OUT_DAMAGE
        $this->assertDatabaseHas('stock_movements', [
            'product_variant_size_id' => $this->pvs7->id,
            'store_id' => $this->store->id,
            'movement_type' => 'stock_out_damage',
            'quantity_change' => -2,
        ]);
    }

    /** @test */
    public function multi_size_stock_damage_updates_all_sizes_atomically(): void
    {
        // Damage 3 pairs of IND 6 (Available: 10) and 2 pairs of IND 7 (Available: 8)
        $res = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/damage', [
                'store_id' => $this->store->id,
                'reason' => 'torn',
                'items' => [
                    ['product_variant_size_id' => $this->pvs6->id, 'quantity' => 3],
                    ['product_variant_size_id' => $this->pvs7->id, 'quantity' => 2],
                ],
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.total_quantity', 5);

        // IND 6 stock: 10 - 3 = 7
        $stock6 = InventoryStock::where('product_variant_size_id', $this->pvs6->id)->first();
        $this->assertEquals(7, $stock6->stock_quantity);

        // IND 7 stock: 8 - 2 = 6
        $stock7 = InventoryStock::where('product_variant_size_id', $this->pvs7->id)->first();
        $this->assertEquals(6, $stock7->stock_quantity);
    }

    /** @test */
    public function damage_quantity_exceeding_available_stock_is_rejected(): void
    {
        // Attempting to damage 15 pairs when IND 7 has only 8
        $res = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/damage', [
                'store_id' => $this->store->id,
                'reason' => 'damaged',
                'items' => [
                    ['product_variant_size_id' => $this->pvs7->id, 'quantity' => 15],
                ],
            ]);

        $res->assertStatus(422)
            ->assertJsonPath('success', false);

        // Assert Stock unchanged at 8
        $stock = InventoryStock::where('product_variant_size_id', $this->pvs7->id)->first();
        $this->assertEquals(8, $stock->stock_quantity);
    }

    /** @test */
    public function damage_reason_other_requires_mandatory_remarks(): void
    {
        $res = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/damage', [
                'store_id' => $this->store->id,
                'reason' => 'other',
                'remarks' => '',
                'items' => [
                    ['product_variant_size_id' => $this->pvs7->id, 'quantity' => 1],
                ],
            ]);

        $res->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function damage_history_and_detail_endpoints_return_full_items(): void
    {
        // Create 1 damage tx
        $createRes = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/damage', [
                'store_id' => $this->store->id,
                'reason' => 'broken',
                'remarks' => 'Sole detached during display',
                'items' => [
                    ['product_variant_size_id' => $this->pvs6->id, 'quantity' => 1],
                ],
            ])->assertStatus(201);

        $txId = $createRes->json('data.id');

        // Test History List
        $histRes = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/damage?store_id=' . $this->store->id);

        $histRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.id', $txId);

        // Test Detail Endpoint
        $detRes = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/inventory/damage/' . $txId);

        $detRes->assertStatus(200)
            ->assertJsonPath('data.damage_number', $createRes->json('data.damage_number'))
            ->assertJsonPath('data.items.0.available_stock_before', 10)
            ->assertJsonPath('data.items.0.available_stock_after', 9);
    }

    /** @test */
    public function audit_log_created_for_stock_damage(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/inventory/damage', [
                'store_id' => $this->store->id,
                'reason' => 'lost',
                'items' => [
                    ['product_variant_size_id' => $this->pvs6->id, 'quantity' => 2],
                ],
            ])->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'store_id' => $this->store->id,
            'module' => 'INVENTORY',
            'event_type' => 'STOCK_OUT_DAMAGE',
        ]);
    }
}
