<?php

namespace Tests\Feature\Api\v1;

use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\Role;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductStockPreservationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Store $store;
    protected Category $category1;
    protected Category $category2;
    protected Brand $brand1;
    protected Brand $brand2;
    protected Color $colorBlack;
    protected Color $colorRed;
    protected Size $size7;
    protected Size $size8;
    protected Size $size9;
    protected InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $permEdit = Permission::firstOrCreate(['name' => 'products.edit'], ['guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Edit Products']);
        $permView = Permission::firstOrCreate(['name' => 'products.view'], ['guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Products']);

        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin'], ['guard_name' => 'web']);
        $superAdminRole->permissions()->syncWithoutDetaching([$permEdit->id, $permView->id]);

        $this->adminUser = User::create([
            'name' => 'Stock Guardian Admin',
            'username' => 'stk_admin_' . rand(1000, 9999),
            'email' => 'stk_admin_' . rand(1000, 9999) . '@rupsa.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($superAdminRole->id, ['model_type' => User::class]);

        $this->store = Store::create([
            'code' => 'STR-001',
            'name' => 'RUPSA PADUKALAYA - Main Outlet',
            'phone' => '+91 9735125112',
            'address' => 'DHANTALA BAZAR, DHANTALA, NADIA',
            'is_active' => true,
        ]);

        $this->category1 = Category::create(['name' => 'Formal Shoes', 'slug' => 'formal-shoes-test']);
        $this->category2 = Category::create(['name' => 'Casual Shoes', 'slug' => 'casual-shoes-test']);

        $this->brand1 = Brand::create(['name' => 'Bata', 'slug' => 'bata-test']);
        $this->brand2 = Brand::create(['name' => 'Apex', 'slug' => 'apex-test']);

        $this->colorBlack = Color::create(['name' => 'Black', 'code' => 'BLK']);
        $this->colorRed = Color::create(['name' => 'Red', 'code' => 'RED']);

        $this->size7 = Size::create(['system' => 'IND', 'size_number' => '7', 'display_name' => 'IND 7']);
        $this->size8 = Size::create(['system' => 'IND', 'size_number' => '8', 'display_name' => 'IND 8']);
        $this->size9 = Size::create(['system' => 'IND', 'size_number' => '9', 'display_name' => 'IND 9']);

        $this->inventoryService = app(InventoryService::class);
    }

    /** Helper to create a basic test product with stock */
    protected function createProductWithStock(string $article, string $name, int $stockQty = 25): array
    {
        $product = Product::create([
            'article_number' => $article,
            'name' => $name,
            'slug' => strtolower($article) . '-' . rand(100, 999),
            'category_id' => $this->category1->id,
            'brand_id' => $this->brand1->id,
            'gender' => 'men',
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color_id' => $this->colorBlack->id,
            'is_active' => true,
        ]);

        $vSize = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $this->size8->id,
            'sku' => "{$article}-BLK-08",
            'cost_price' => 500.00,
            'mrp' => 1299.00,
            'selling_price' => 999.00,
            'is_active' => true,
        ]);

        if ($stockQty > 0) {
            $this->inventoryService->addStock(
                $vSize->id,
                $stockQty,
                StockMovementType::ADJUSTMENT_ADD,
                null,
                null,
                $this->store->id,
                0,
                0,
                $this->adminUser,
                'Initial Test Stock'
            );
        }

        return [$product, $variant, $vSize];
    }

    /** TEST 0: API show endpoint returns correct stock quantity for each size */
    public function test_api_show_endpoint_returns_correct_stock_quantity_for_each_size(): void
    {
        [$product, $variant, $vSize] = $this->createProductWithStock('RP-SHOW-01', 'API Stock Test Shoe', 33);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.total_stock', 33)
            ->assertJsonPath('data.variants.0.sizes.0.stock_quantity', 33)
            ->assertJsonPath('data.variants.0.sizes.0.current_stock', 33);
    }

    /** TEST 1: Create product -> Create stock = 25 -> Edit product name -> Assert stock remains 25 */
    public function test_1_edit_product_name_preserves_stock(): void
    {
        [$product, $variant, $vSize] = $this->createProductWithStock('RP-STK-01', 'Original Name', 25);

        $payload = [
            'article_number' => 'RP-STK-01',
            'name' => 'Updated Product Name',
            'brand_id' => $this->brand1->id,
            'category_id' => $this->category1->id,
            'gender' => 'men',
            'mrp' => 1299.00,
            'selling_price' => 999.00,
            'color_blocks' => json_encode([
                [
                    'color_id' => $this->colorBlack->id,
                    'size_rows' => [
                        [
                            'size_id' => $this->size8->id,
                            'size_number' => '8',
                            'sku' => 'RP-STK-01-BLK-08',
                            'opening_stock' => 0, // Should be ignored for existing SKU!
                        ]
                    ]
                ]
            ])
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/bulk-update", $payload);

        $response->assertStatus(200);

        $currentStock = $this->inventoryService->getStockRecord($vSize->id, $this->store->id, 0, 0)->stock_quantity;
        $this->assertEquals(25, $currentStock);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Product Name',
        ]);
    }

    /** TEST 2: Existing stock: Size 7 = 10, Size 8 = 15, Size 9 = 20 -> Edit metadata -> Assert all sizes intact */
    public function test_2_multi_size_stock_preserved_on_metadata_edit(): void
    {
        $product = Product::create([
            'article_number' => 'RP-STK-02',
            'name' => 'Multi Size Shoe',
            'slug' => 'rp-stk-02',
            'category_id' => $this->category1->id,
            'brand_id' => $this->brand1->id,
            'gender' => 'men',
            'is_active' => true,
        ]);

        $variant = ProductVariant::create(['product_id' => $product->id, 'color_id' => $this->colorBlack->id]);

        $vs7 = ProductVariantSize::create(['product_variant_id' => $variant->id, 'size_id' => $this->size7->id, 'sku' => 'RP-STK-02-BLK-07']);
        $vs8 = ProductVariantSize::create(['product_variant_id' => $variant->id, 'size_id' => $this->size8->id, 'sku' => 'RP-STK-02-BLK-08']);
        $vs9 = ProductVariantSize::create(['product_variant_id' => $variant->id, 'size_id' => $this->size9->id, 'sku' => 'RP-STK-02-BLK-09']);

        $this->inventoryService->addStock($vs7->id, 10, StockMovementType::ADJUSTMENT_ADD, null, null, $this->store->id, 0, 0, $this->adminUser);
        $this->inventoryService->addStock($vs8->id, 15, StockMovementType::ADJUSTMENT_ADD, null, null, $this->store->id, 0, 0, $this->adminUser);
        $this->inventoryService->addStock($vs9->id, 20, StockMovementType::ADJUSTMENT_ADD, null, null, $this->store->id, 0, 0, $this->adminUser);

        $payload = [
            'article_number' => 'RP-STK-02',
            'name' => 'Renamed Multi Size Shoe',
            'brand_id' => $this->brand1->id,
            'category_id' => $this->category1->id,
            'gender' => 'men',
            'color_blocks' => json_encode([
                [
                    'color_id' => $this->colorBlack->id,
                    'size_rows' => [
                        ['size_id' => $this->size7->id, 'size_number' => '7', 'sku' => 'RP-STK-02-BLK-07', 'opening_stock' => 0],
                        ['size_id' => $this->size8->id, 'size_number' => '8', 'sku' => 'RP-STK-02-BLK-08', 'opening_stock' => 0],
                        ['size_id' => $this->size9->id, 'size_number' => '9', 'sku' => 'RP-STK-02-BLK-09', 'opening_stock' => 0],
                    ]
                ]
            ])
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/bulk-update", $payload);

        $response->assertStatus(200);

        $this->assertEquals(10, $this->inventoryService->getStockRecord($vs7->id, $this->store->id, 0, 0)->stock_quantity);
        $this->assertEquals(15, $this->inventoryService->getStockRecord($vs8->id, $this->store->id, 0, 0)->stock_quantity);
        $this->assertEquals(20, $this->inventoryService->getStockRecord($vs9->id, $this->store->id, 0, 0)->stock_quantity);
    }

    /** TEST 3: Edit price -> Assert stock unchanged */
    public function test_3_edit_price_preserves_stock(): void
    {
        [$product, $variant, $vSize] = $this->createProductWithStock('RP-STK-03', 'Priced Shoe', 50);

        $payload = [
            'article_number' => 'RP-STK-03',
            'name' => 'Priced Shoe',
            'brand_id' => $this->brand1->id,
            'category_id' => $this->category1->id,
            'mrp' => 1999.00,
            'selling_price' => 1499.00,
            'color_blocks' => json_encode([
                [
                    'color_id' => $this->colorBlack->id,
                    'size_rows' => [
                        [
                            'size_id' => $this->size8->id,
                            'size_number' => '8',
                            'sku' => 'RP-STK-03-BLK-08',
                            'mrp' => 1999.00,
                            'selling_price' => 1499.00,
                            'opening_stock' => 0,
                        ]
                    ]
                ]
            ])
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/bulk-update", $payload);

        $response->assertStatus(200);

        $this->assertEquals(50, $this->inventoryService->getStockRecord($vSize->id, $this->store->id, 0, 0)->stock_quantity);
        $vSize->refresh();
        $this->assertEquals(1499.00, $vSize->selling_price);
    }

    /** TEST 4: Edit color -> Assert stock unchanged */
    public function test_4_edit_color_and_adding_color_preserves_existing_stock(): void
    {
        [$product, $variant, $vSize] = $this->createProductWithStock('RP-STK-04', 'Colorway Shoe', 30);

        $payload = [
            'article_number' => 'RP-STK-04',
            'name' => 'Colorway Shoe',
            'brand_id' => $this->brand1->id,
            'category_id' => $this->category1->id,
            'color_blocks' => json_encode([
                [
                    'color_id' => $this->colorBlack->id,
                    'size_rows' => [
                        ['size_id' => $this->size8->id, 'size_number' => '8', 'sku' => 'RP-STK-04-BLK-08', 'opening_stock' => 0]
                    ]
                ],
                [
                    'color_id' => $this->colorRed->id,
                    'size_rows' => [
                        ['size_id' => $this->size8->id, 'size_number' => '8', 'sku' => 'RP-STK-04-RED-08', 'opening_stock' => 0]
                    ]
                ]
            ])
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/bulk-update", $payload);

        $response->assertStatus(200);

        // Black variant stock must remain 30
        $this->assertEquals(30, $this->inventoryService->getStockRecord($vSize->id, $this->store->id, 0, 0)->stock_quantity);
    }

    /** TEST 5: Edit category/brand/gender -> Assert stock unchanged */
    public function test_5_edit_category_brand_gender_preserves_stock(): void
    {
        [$product, $variant, $vSize] = $this->createProductWithStock('RP-STK-05', 'Category Change Shoe', 40);

        $payload = [
            'article_number' => 'RP-STK-05',
            'name' => 'Category Change Shoe',
            'brand_id' => $this->brand2->id,
            'category_id' => $this->category2->id,
            'gender' => 'unisex',
            'color_blocks' => json_encode([
                [
                    'color_id' => $this->colorBlack->id,
                    'size_rows' => [
                        ['size_id' => $this->size8->id, 'size_number' => '8', 'sku' => 'RP-STK-05-BLK-08', 'opening_stock' => 0]
                    ]
                ]
            ])
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/bulk-update", $payload);

        $response->assertStatus(200);

        $this->assertEquals(40, $this->inventoryService->getStockRecord($vSize->id, $this->store->id, 0, 0)->stock_quantity);
        $product->refresh();
        $this->assertEquals($this->brand2->id, $product->brand_id);
        $this->assertEquals($this->category2->id, $product->category_id);
        $this->assertEquals('unisex', is_object($product->gender) ? $product->gender->value : $product->gender);
    }

    /** TEST 6: Add a NEW SKU during edit -> Assert existing SKU stock unchanged, new SKU starts safely at 0 or initial opening stock */
    public function test_6_adding_new_sku_during_edit_preserves_existing_stock(): void
    {
        [$product, $variant, $vSize8] = $this->createProductWithStock('RP-STK-06', 'Expand Size Shoe', 18);

        $payload = [
            'article_number' => 'RP-STK-06',
            'name' => 'Expand Size Shoe',
            'brand_id' => $this->brand1->id,
            'category_id' => $this->category1->id,
            'color_blocks' => json_encode([
                [
                    'color_id' => $this->colorBlack->id,
                    'size_rows' => [
                        ['size_id' => $this->size8->id, 'size_number' => '8', 'sku' => 'RP-STK-06-BLK-08', 'opening_stock' => 0],
                        ['size_id' => $this->size9->id, 'size_number' => '9', 'sku' => 'RP-STK-06-BLK-09', 'opening_stock' => 5], // New SKU with initial 5
                    ]
                ]
            ])
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/bulk-update", $payload);

        $response->assertStatus(200);

        // Existing Size 8 stock MUST remain 18
        $this->assertEquals(18, $this->inventoryService->getStockRecord($vSize8->id, $this->store->id, 0, 0)->stock_quantity);

        // New Size 9 SKU stock MUST be 5
        $vSize9 = ProductVariantSize::where('sku', 'RP-STK-06-BLK-09')->firstOrFail();
        $this->assertEquals(5, $this->inventoryService->getStockRecord($vSize9->id, $this->store->id, 0, 0)->stock_quantity);
    }

    /** TEST 7: Existing product with sales/purchases/stock movements -> Edit product -> Assert all historical stock movements remain intact */
    public function test_7_edit_product_with_transactional_history_preserves_movements(): void
    {
        [$product, $variant, $vSize] = $this->createProductWithStock('RP-STK-07', 'Historical Shoe', 25);

        // Simulate historical sale
        $customer = Customer::create(['name' => 'Test Buyer', 'mobile_number' => '9988776655']);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-HIST',
            'client_trans_uuid' => 'uuid-hist-01',
            'store_id' => $this->store->id,
            'customer_id' => $customer->id,
            'grand_total' => 999.00,
            'paid_amount' => 999.00,
            'status' => 'completed',
            'created_by' => $this->adminUser->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_variant_size_id' => $vSize->id,
            'sku_snapshot' => 'RP-STK-07-BLK-08',
            'article_number_snapshot' => 'RP-STK-07',
            'product_name_snapshot' => 'Historical Shoe',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => '8',
            'quantity' => 1,
            'unit_price' => 999.00,
            'subtotal' => 999.00,
        ]);

        $movementCountBefore = StockMovement::where('product_variant_size_id', $vSize->id)->count();
        $this->assertGreaterThan(0, $movementCountBefore);

        // Edit product details
        $payload = [
            'article_number' => 'RP-STK-07',
            'name' => 'Historical Shoe Updated Title',
            'brand_id' => $this->brand1->id,
            'category_id' => $this->category1->id,
            'color_blocks' => json_encode([
                [
                    'color_id' => $this->colorBlack->id,
                    'size_rows' => [
                        ['size_id' => $this->size8->id, 'size_number' => '8', 'sku' => 'RP-STK-07-BLK-08', 'opening_stock' => 0]
                    ]
                ]
            ])
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/v1/products/{$product->id}/bulk-update", $payload);

        $response->assertStatus(200);

        // Stock movements count and existing stock quantity MUST be unchanged
        $movementCountAfter = StockMovement::where('product_variant_size_id', $vSize->id)->count();
        $this->assertEquals($movementCountBefore, $movementCountAfter);
        $this->assertEquals(25, $this->inventoryService->getStockRecord($vSize->id, $this->store->id, 0, 0)->stock_quantity);
    }

    /** TEST 8: Edit product multiple times -> Assert stock never gets progressively reset */
    public function test_8_multiple_edits_never_progressively_reset_stock(): void
    {
        [$product, $variant, $vSize] = $this->createProductWithStock('RP-STK-08', 'Multi Edit Shoe', 35);

        for ($i = 1; $i <= 5; $i++) {
            $payload = [
                'article_number' => 'RP-STK-08',
                'name' => "Multi Edit Shoe Iteration {$i}",
                'brand_id' => $this->brand1->id,
                'category_id' => $this->category1->id,
                'selling_price' => 999.00 + ($i * 10),
                'color_blocks' => json_encode([
                    [
                        'color_id' => $this->colorBlack->id,
                        'size_rows' => [
                            ['size_id' => $this->size8->id, 'size_number' => '8', 'sku' => 'RP-STK-08-BLK-08', 'opening_stock' => 0]
                        ]
                    ]
                ])
            ];

            $response = $this->actingAs($this->adminUser, 'sanctum')
                ->postJson("/api/v1/products/{$product->id}/bulk-update", $payload);

            $response->assertStatus(200);

            $currentStock = $this->inventoryService->getStockRecord($vSize->id, $this->store->id, 0, 0)->stock_quantity;
            $this->assertEquals(35, $currentStock, "Stock was modified on iteration {$i}");
        }
    }
}
