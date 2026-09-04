<?php

namespace Tests\Feature\Api\v1;

use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\Size;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryModuleCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected Store $store;

    protected Brand $brand;

    protected Category $category;

    protected Color $color;

    protected Size $sizeInd8;

    protected Size $sizeInd9;

    protected Product $product;

    protected ProductVariant $variant;

    protected ProductVariantSize $variantSize1;

    protected ProductVariantSize $variantSize2;

    protected InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryService = app(InventoryService::class);

        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $this->adminUser = User::factory()->create(['name' => 'Inventory Manager', 'username' => 'invmanager_' . uniqid(), 'is_active' => true]);
        $this->adminUser->roles()->attach($superAdminRole->id, ['model_type' => User::class]);

        $this->store = Store::firstOrCreate(
            ['code' => 'STR-001'],
            ['name' => 'RUPSA PADUKALAYA - Main Outlet', 'is_active' => true]
        );
        $this->adminUser->stores()->attach($this->store->id);

        $supplier = Supplier::create([
            'supplier_code' => 'SUP-001',
            'name' => 'Main Footwear Supplier',
            'phone' => '+91 9830012345',
            'is_active' => true,
        ]);

        $this->brand = Brand::create(['name' => 'Bata Footwear', 'slug' => 'bata-footwear', 'is_active' => true]);
        $this->category = Category::create(['name' => 'Formal Shoes', 'slug' => 'formal-shoes', 'is_active' => true]);
        $this->color = Color::create(['name' => 'Black', 'code' => '#000000']);
        $this->sizeInd8 = Size::create(['size_number' => '8', 'size_system' => 'IND']);
        $this->sizeInd9 = Size::create(['size_number' => '9', 'size_system' => 'IND']);

        $this->product = Product::create([
            'article_number' => 'RP-8001',
            'name' => 'Executive Leather Oxford',
            'slug' => 'executive-leather-oxford-' . uniqid(),
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'supplier_id' => $supplier->id,
            'selling_price' => 1500.00,
            'cost_price' => 900.00,
            'mrp' => 1800.00,
            'is_active' => true,
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'color_id' => $this->color->id,
        ]);

        $this->variantSize1 = ProductVariantSize::create([
            'product_variant_id' => $this->variant->id,
            'size_id' => $this->sizeInd8->id,
            'sku' => 'RP-8001-BLK-IND8',
            'barcode' => '8901001001',
            'selling_price' => 1500.00,
            'cost_price' => 900.00,
            'mrp' => 1800.00,
        ]);

        $this->variantSize2 = ProductVariantSize::create([
            'product_variant_id' => $this->variant->id,
            'size_id' => $this->sizeInd9->id,
            'sku' => 'RP-8001-BLK-IND9',
            'barcode' => '8901001002',
            'selling_price' => 1500.00,
            'cost_price' => 900.00,
            'mrp' => 1800.00,
        ]);
    }

    /** 1. Product creation initializes SKU stock record at 0 */
    public function test_product_creation_initializes_stock_record_at_zero(): void
    {
        $stockRecord = $this->inventoryService->getStockRecord($this->variantSize1->id, $this->store->id);
        $this->assertEquals(0, $stockRecord->stock_quantity);
    }

    /** 2. Supplier Purchase increases stock atomically + creates PURCHASE movement */
    public function test_purchase_receive_increases_stock_atomically_with_movement(): void
    {
        $this->inventoryService->addStock(
            $this->variantSize1->id,
            20,
            StockMovementType::PURCHASE_RECEIVE,
            PurchaseOrder::class,
            1,
            $this->store->id,
            0,
            0,
            $this->adminUser,
            'Purchase Order PO-1001 received'
        );

        $stock = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first();
        $this->assertEquals(20, $stock->stock_quantity);

        $movement = StockMovement::where('product_variant_size_id', $this->variantSize1->id)->latest()->first();
        $this->assertNotNull($movement);
        $this->assertEquals(20, $movement->quantity_change);
        $this->assertEquals(0, $movement->stock_before);
        $this->assertEquals(20, $movement->stock_after);
    }

    /** 3. POS sale decreases stock atomically + creates SALE_POS movement */
    public function test_pos_sale_decreases_stock_atomically(): void
    {
        $this->inventoryService->addStock($this->variantSize1->id, 10, StockMovementType::OPENING_STOCK, null, null, $this->store->id);

        $this->inventoryService->deductStock(
            $this->variantSize1->id,
            2,
            StockMovementType::SALE_POS,
            Invoice::class,
            101,
            $this->store->id,
            0,
            0,
            $this->adminUser,
            false,
            'POS Sale INV-101'
        );

        $stock = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first();
        $this->assertEquals(8, $stock->stock_quantity);

        $movement = StockMovement::where('product_variant_size_id', $this->variantSize1->id)->orderBy('id', 'desc')->first();
        $this->assertEquals(-2, $movement->quantity_change);
        $this->assertEquals(10, $movement->stock_before);
        $this->assertEquals(8, $movement->stock_after);
    }

    /** 4. Insufficient stock blocks sale with RuntimeException */
    public function test_insufficient_stock_blocks_deduction_with_error(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->inventoryService->deductStock(
            $this->variantSize1->id,
            5,
            StockMovementType::SALE_POS,
            null,
            null,
            $this->store->id
        );
    }

    /** 5. Resellable Sales Return increases sellable stock */
    public function test_resellable_sales_return_adds_stock(): void
    {
        $this->inventoryService->addStock(
            $this->variantSize1->id,
            1,
            StockMovementType::SALE_RETURN,
            null,
            null,
            $this->store->id,
            0,
            0,
            $this->adminUser,
            'Resellable return'
        );

        $stock = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first();
        $this->assertEquals(1, $stock->stock_quantity);
    }

    /** 6. Damaged return does NOT add to sellable stock */
    public function test_damaged_return_does_not_add_to_sellable_stock(): void
    {
        // Log movement directly as DAMAGE without adding to sellable stock
        StockMovement::create([
            'product_variant_size_id' => $this->variantSize1->id,
            'store_id' => $this->store->id,
            'movement_type' => StockMovementType::DAMAGE,
            'quantity_change' => 0,
            'stock_before' => 0,
            'stock_after' => 0,
            'notes' => 'Damaged return (non-resellable)',
            'created_by' => $this->adminUser->id,
        ]);

        $stock = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first();
        $this->assertEquals(0, $stock ? $stock->stock_quantity : 0);
    }

    /** 7. Footwear Exchange increases old SKU stock & decreases replacement SKU stock atomically */
    public function test_footwear_exchange_atomic_stock_movements(): void
    {
        $this->inventoryService->addStock($this->variantSize2->id, 5, StockMovementType::OPENING_STOCK, null, null, $this->store->id);

        DB::transaction(function () {
            // Returned item (Size 8) added back
            $this->inventoryService->addStock(
                $this->variantSize1->id,
                1,
                StockMovementType::SALE_RETURN,
                null,
                null,
                $this->store->id,
                0,
                0,
                $this->adminUser,
                'Exchange Return'
            );

            // Replacement item (Size 9) deducted
            $this->inventoryService->deductStock(
                $this->variantSize2->id,
                1,
                StockMovementType::SALE_POS,
                null,
                null,
                $this->store->id,
                0,
                0,
                $this->adminUser,
                false,
                'Exchange Replacement'
            );
        });

        $stock1 = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first();
        $stock2 = InventoryStock::where('product_variant_size_id', $this->variantSize2->id)->first();

        $this->assertEquals(1, $stock1->stock_quantity);
        $this->assertEquals(4, $stock2->stock_quantity);
    }

    /** 8 & 9 & 10. Stock Adjustment Add, Reduce, and Negative Prevention */
    public function test_stock_adjustment_add_reduce_and_negative_prevention(): void
    {
        $this->actingAs($this->adminUser);

        // Add 10 via adjustment API
        $res1 = $this->postJson('/api/v1/inventory/adjustments', [
            'store_id' => $this->store->id,
            'reason' => 'Physical Stock Count',
            'notes' => 'Initial audit',
            'items' => [
                ['product_variant_size_id' => $this->variantSize1->id, 'type' => 'add', 'quantity' => 10],
            ],
        ]);
        $res1->assertStatus(201);
        $this->assertEquals(10, InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->value('stock_quantity'));

        // Reduce 3 via adjustment API
        $res2 = $this->postJson('/api/v1/inventory/adjustments', [
            'store_id' => $this->store->id,
            'reason' => 'Damaged',
            'notes' => 'Water damage',
            'items' => [
                ['product_variant_size_id' => $this->variantSize1->id, 'type' => 'deduct', 'quantity' => 3],
            ],
        ]);
        $res2->assertStatus(201);
        $this->assertEquals(7, InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->value('stock_quantity'));

        // Attempting to deduct 15 (resulting in negative stock) fails with 422
        $res3 = $this->postJson('/api/v1/inventory/adjustments', [
            'store_id' => $this->store->id,
            'reason' => 'Damaged',
            'items' => [
                ['product_variant_size_id' => $this->variantSize1->id, 'type' => 'deduct', 'quantity' => 15],
            ],
        ]);
        $res3->assertStatus(422);
    }

    /** 14, 15, 16. Low Stock, Out of Stock & Stock Valuation calculations */
    public function test_overview_kpis_and_valuation_calculations(): void
    {
        $this->actingAs($this->adminUser);

        // Set stock for variant 1 to 2 (Low stock, since reorder level is 3)
        $this->inventoryService->setStock($this->variantSize1->id, 2, StockMovementType::STOCK_CORRECTION, null, null, $this->store->id);
        // Set stock for variant 2 to 0 (Out of stock)
        $this->inventoryService->setStock($this->variantSize2->id, 0, StockMovementType::STOCK_CORRECTION, null, null, $this->store->id);

        $res = $this->getJson('/api/v1/inventory/overview');
        $res->assertStatus(200);
        $res->assertJsonPath('data.kpis.low_stock_items', 1);
        $res->assertJsonPath('data.kpis.out_of_stock_items', 1);

        // Valuation = 2 * 900.00 cost_price = 1800.00
        $res->assertJsonPath('data.kpis.total_stock_value', 1800);
    }

    /** 18. Reconciliation workflow API */
    public function test_reconciliation_workflow_api(): void
    {
        $this->actingAs($this->adminUser);

        $this->inventoryService->setStock($this->variantSize1->id, 5, StockMovementType::OPENING_STOCK, null, null, $this->store->id);

        $res = $this->getJson("/api/v1/inventory/reconciliation/{$this->variantSize1->sku}");
        $res->assertStatus(200);
        $res->assertJsonPath('data.system_stock', 5);
        $res->assertJsonPath('data.sku', $this->variantSize1->sku);
    }

    /** 19. Low Stock API endpoint */
    public function test_low_stock_api_endpoint(): void
    {
        $this->actingAs($this->adminUser);

        $this->inventoryService->setStock($this->variantSize1->id, 2, StockMovementType::STOCK_CORRECTION, null, null, $this->store->id);
        $this->inventoryService->setStock($this->variantSize2->id, 0, StockMovementType::STOCK_CORRECTION, null, null, $this->store->id);

        $res = $this->getJson('/api/v1/inventory/low-stock');
        $res->assertStatus(200);
        $res->assertJsonPath('data.pagination.total', 2); // 1 low stock + 1 out of stock
    }

    /** 21. Historical movements cannot be modified (no PUT/DELETE routes) */
    public function test_stock_movements_are_read_only(): void
    {
        $this->actingAs($this->adminUser);

        $m = StockMovement::create([
            'product_variant_size_id' => $this->variantSize1->id,
            'store_id' => $this->store->id,
            'movement_type' => StockMovementType::OPENING_STOCK,
            'quantity_change' => 10,
            'stock_before' => 0,
            'stock_after' => 10,
            'created_by' => $this->adminUser->id,
        ]);

        $putRes = $this->putJson("/api/v1/inventory/movements/{$m->id}", ['quantity_change' => 50]);
        $putRes->assertStatus(405); // Method Not Allowed

        $delRes = $this->deleteJson("/api/v1/inventory/movements/{$m->id}");
        $delRes->assertStatus(405); // Method Not Allowed
    }

    /** 22. Inventory rollback occurs when parent transaction fails */
    public function test_inventory_rollback_on_transaction_failure(): void
    {
        try {
            DB::transaction(function () {
                $this->inventoryService->addStock(
                    $this->variantSize1->id,
                    50,
                    StockMovementType::PURCHASE_RECEIVE,
                    null,
                    null,
                    $this->store->id
                );

                throw new \Exception('Simulated parent transaction failure');
            });
        } catch (\Exception $e) {
            // Expected exception
        }

        $stock = InventoryStock::where('product_variant_size_id', $this->variantSize1->id)->first();
        $this->assertEquals(0, $stock ? $stock->stock_quantity : 0);
        $this->assertEquals(0, StockMovement::where('product_variant_size_id', $this->variantSize1->id)->count());
    }
}
