<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\HsnCode;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\Promotion;
use App\Models\PromotionTarget;
use App\Models\PromotionUsage;
use App\Models\Role;
use App\Models\Size;
use App\Models\Store;
use App\Models\User;
use App\Services\PromotionEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductPromotionEngineTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $cashier;
    protected User $noPermUser;
    protected Store $store1;
    protected Store $store2;
    protected PosSession $openSession1;
    protected Customer $customer;
    protected Product $product;
    protected Category $category;
    protected Brand $brand;
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

        $this->customer = Customer::create([
            'mobile_number' => '9876543210',
            'name' => 'Alice Customer',
            'email' => 'alice@example.com',
        ]);

        $this->openSession1 = PosSession::create([
            'store_id' => $this->store1->id,
            'user_id' => $this->cashier->id,
            'opened_at' => now(),
            'opening_cash' => 1000.00,
            'closing_cash_system' => 1000.00,
            'status' => 'open',
        ]);

        // Setup Footwear Masters
        $hsn = HsnCode::create(['code' => '6403', 'description' => 'Footwear', 'default_gst_rate' => 12.00]);
        $this->brand = Brand::create(['name' => 'Nike', 'slug' => 'nike']);
        $this->category = Category::create(['name' => 'Casual', 'slug' => 'casual', 'hsn_code_id' => $hsn->id]);
        $this->product = Product::create([
            'article_number' => 'ART900',
            'name' => 'Men Casual Sneaker',
            'slug' => 'men-casual-sneaker',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'hsn_code_id' => $hsn->id,
        ]);
        $color = Color::create(['name' => 'Black', 'code' => 'BLK']);
        $variant = ProductVariant::create(['product_id' => $this->product->id, 'color_id' => $color->id]);
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

        InventoryStock::create(['product_variant_size_id' => $this->variantSize1->id, 'store_id' => $this->store1->id, 'stock_quantity' => 50]);
        InventoryStock::create(['product_variant_size_id' => $this->variantSize2->id, 'store_id' => $this->store1->id, 'stock_quantity' => 50]);
    }

    public function test_1_unauthenticated_access_rejected(): void
    {
        $this->getJson('/api/v1/promotions')->assertStatus(401);
        $this->postJson('/api/v1/promotions', [])->assertStatus(401);
        $this->postJson('/api/v1/promotions/evaluate', [])->assertStatus(401);
    }

    public function test_2_rbac_permission_rejection(): void
    {
        Sanctum::actingAs($this->noPermUser);

        $this->getJson('/api/v1/promotions')->assertStatus(403);
        $this->postJson('/api/v1/promotions', ['name' => 'Test Promo'])->assertStatus(403);
    }

    public function test_3_product_level_percentage_promotion(): void
    {
        Sanctum::actingAs($this->cashier);

        $promo = Promotion::create([
            'name' => '10% Off ART900',
            'promotion_type' => 'percentage',
            'discount_scope' => 'product',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);
        PromotionTarget::create(['promotion_id' => $promo->id, 'target_type' => 'product', 'target_id' => $this->product->id]);

        $engine = app(PromotionEngineService::class);
        $res = $engine->evaluateCart([
            ['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1],
        ]);

        $this->assertEquals(1000.00, $res['original_subtotal']);
        $this->assertEquals(100.00, $res['total_discount_amount']);
        $this->assertEquals(900.00, $res['final_subtotal']);
    }

    public function test_4_product_level_fixed_amount_promotion(): void
    {
        Sanctum::actingAs($this->cashier);

        $promo = Promotion::create([
            'name' => '₹150 Off ART900',
            'promotion_type' => 'fixed_amount',
            'discount_scope' => 'product',
            'discount_value' => 150.00,
            'is_active' => true,
        ]);
        PromotionTarget::create(['promotion_id' => $promo->id, 'target_type' => 'product', 'target_id' => $this->product->id]);

        $engine = app(PromotionEngineService::class);
        $res = $engine->evaluateCart([
            ['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1],
        ]);

        $this->assertEquals(150.00, $res['total_discount_amount']);
        $this->assertEquals(850.00, $res['final_subtotal']);
    }

    public function test_5_category_level_promotion(): void
    {
        Sanctum::actingAs($this->cashier);

        $promo = Promotion::create([
            'name' => '15% Off Casual Category',
            'promotion_type' => 'percentage',
            'discount_scope' => 'category',
            'discount_value' => 15.00,
            'is_active' => true,
        ]);
        PromotionTarget::create(['promotion_id' => $promo->id, 'target_type' => 'category', 'target_id' => $this->category->id]);

        $engine = app(PromotionEngineService::class);
        $res = $engine->evaluateCart([
            ['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 2],
        ]);

        $this->assertEquals(2000.00, $res['original_subtotal']);
        $this->assertEquals(300.00, $res['total_discount_amount']);
        $this->assertEquals(1700.00, $res['final_subtotal']);
    }

    public function test_6_brand_level_promotion(): void
    {
        Sanctum::actingAs($this->cashier);

        $promo = Promotion::create([
            'name' => '20% Off Nike Brand',
            'promotion_type' => 'percentage',
            'discount_scope' => 'brand',
            'discount_value' => 20.00,
            'is_active' => true,
        ]);
        PromotionTarget::create(['promotion_id' => $promo->id, 'target_type' => 'brand', 'target_id' => $this->brand->id]);

        $engine = app(PromotionEngineService::class);
        $res = $engine->evaluateCart([
            ['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1],
        ]);

        $this->assertEquals(200.00, $res['total_discount_amount']);
        $this->assertEquals(800.00, $res['final_subtotal']);
    }

    public function test_7_coupon_code_validation_application(): void
    {
        Sanctum::actingAs($this->cashier);

        Promotion::create([
            'name' => 'Summer Festive Coupon',
            'code' => 'SUMMER50',
            'promotion_type' => 'fixed_amount',
            'discount_scope' => 'cart',
            'discount_value' => 50.00,
            'is_active' => true,
        ]);

        $res = $this->postJson('/api/v1/promotions/evaluate', [
            'coupon_code' => 'SUMMER50',
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(200);
        $this->assertEquals(50.00, $res->json('data.total_discount_amount'));
        $this->assertEquals(950.00, $res->json('data.final_subtotal'));
    }

    public function test_8_invalid_expired_coupon_code_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        Promotion::create([
            'name' => 'Expired Coupon',
            'code' => 'EXPIRED100',
            'promotion_type' => 'fixed_amount',
            'discount_scope' => 'cart',
            'discount_value' => 100.00,
            'end_date' => now()->subDay(),
            'is_active' => true,
        ]);

        $res = $this->postJson('/api/v1/promotions/evaluate', [
            'coupon_code' => 'EXPIRED100',
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_9_bogo_buy_x_get_y_promotion(): void
    {
        Sanctum::actingAs($this->cashier);

        Promotion::create([
            'name' => 'Buy 1 Get 1 Free',
            'promotion_type' => 'bogo',
            'discount_scope' => 'cart',
            'buy_quantity' => 1,
            'get_quantity' => 1,
            'get_discount_percentage' => 100.00,
            'is_active' => true,
        ]);

        $engine = app(PromotionEngineService::class);
        $res = $engine->evaluateCart([
            ['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 2],
        ]);

        $this->assertEquals(2000.00, $res['original_subtotal']);
        $this->assertEquals(1000.00, $res['total_discount_amount']); // 1 free item
        $this->assertEquals(1000.00, $res['final_subtotal']);
    }

    public function test_10_minimum_cart_amount_requirement(): void
    {
        Sanctum::actingAs($this->cashier);

        Promotion::create([
            'name' => '₹200 Off on ₹1500+',
            'promotion_type' => 'fixed_amount',
            'discount_scope' => 'cart',
            'discount_value' => 200.00,
            'min_cart_amount' => 1500.00,
            'is_active' => true,
        ]);

        $engine = app(PromotionEngineService::class);

        // Subtotal = 1000 (< 1500)
        $resLow = $engine->evaluateCart([['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]]);
        $this->assertEquals(0.00, $resLow['total_discount_amount']);

        // Subtotal = 2000 (>= 1500)
        $resHigh = $engine->evaluateCart([['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 2]]);
        $this->assertEquals(200.00, $resHigh['total_discount_amount']);
    }

    public function test_11_maximum_discount_limit_cap(): void
    {
        Sanctum::actingAs($this->cashier);

        Promotion::create([
            'name' => '50% Off Max ₹200 Cap',
            'promotion_type' => 'percentage',
            'discount_scope' => 'cart',
            'discount_value' => 50.00,
            'max_discount_amount' => 200.00,
            'is_active' => true,
        ]);

        $engine = app(PromotionEngineService::class);
        $res = $engine->evaluateCart([['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]]); // Subtotal 1000

        $this->assertEquals(200.00, $res['total_discount_amount']); // Capped at 200
        $this->assertEquals(800.00, $res['final_subtotal']);
    }

    public function test_12_promotion_start_end_date_validation(): void
    {
        Sanctum::actingAs($this->cashier);

        // Future Promo
        Promotion::create([
            'name' => 'Future Festival Promo',
            'promotion_type' => 'fixed_amount',
            'discount_scope' => 'cart',
            'discount_value' => 200.00,
            'start_date' => now()->addDays(5),
            'is_active' => true,
        ]);

        $engine = app(PromotionEngineService::class);
        $res = $engine->evaluateCart([['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]]);

        $this->assertEquals(0.00, $res['total_discount_amount']);
    }

    public function test_13_inactive_promotion_ignored(): void
    {
        Sanctum::actingAs($this->cashier);

        Promotion::create([
            'name' => 'Disabled Promo',
            'promotion_type' => 'fixed_amount',
            'discount_scope' => 'cart',
            'discount_value' => 300.00,
            'is_active' => false,
        ]);

        $engine = app(PromotionEngineService::class);
        $res = $engine->evaluateCart([['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]]);

        $this->assertEquals(0.00, $res['total_discount_amount']);
    }

    public function test_14_store_specific_promotion_scope(): void
    {
        Sanctum::actingAs($this->cashier);

        // Store 2 promo
        Promotion::create([
            'name' => 'Branch Store 2 Special',
            'promotion_type' => 'fixed_amount',
            'discount_scope' => 'cart',
            'discount_value' => 250.00,
            'store_id' => $this->store2->id,
            'is_active' => true,
        ]);

        $engine = app(PromotionEngineService::class);
        // Evaluating for store1
        $res = $engine->evaluateCart([['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]], null, $this->store1->id);

        $this->assertEquals(0.00, $res['total_discount_amount']);
    }

    public function test_15_unauthorized_store_promotion_management_rejected(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->postJson('/api/v1/promotions', [
            'name' => 'Unauthorized Promo',
            'promotion_type' => 'percentage',
            'discount_scope' => 'cart',
            'discount_value' => 10,
            'store_id' => $this->store2->id,
        ]);

        $res->assertStatus(403);
    }

    public function test_16_super_admin_cross_store_promotion_management(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $res = $this->postJson('/api/v1/promotions', [
            'name' => 'Super Admin Store 2 Promo',
            'promotion_type' => 'percentage',
            'discount_scope' => 'cart',
            'discount_value' => 10,
            'store_id' => $this->store2->id,
        ]);

        $res->assertStatus(201)->assertJsonPath('data.name', 'Super Admin Store 2 Promo');
    }

    public function test_17_promotion_priority_conflict_resolution(): void
    {
        Sanctum::actingAs($this->cashier);

        Promotion::create([
            'name' => 'Low Priority 5% Off',
            'promotion_type' => 'percentage',
            'discount_scope' => 'cart',
            'discount_value' => 5.00,
            'priority' => 1,
            'allow_stacking' => false,
            'is_active' => true,
        ]);

        Promotion::create([
            'name' => 'High Priority 20% Off',
            'promotion_type' => 'percentage',
            'discount_scope' => 'cart',
            'discount_value' => 20.00,
            'priority' => 10,
            'allow_stacking' => false,
            'is_active' => true,
        ]);

        $engine = app(PromotionEngineService::class);
        $res = $engine->evaluateCart([['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]]);

        $this->assertEquals(200.00, $res['total_discount_amount']);
        $this->assertEquals('High Priority 20% Off', $res['applied_promotions'][0]['name']);
    }

    public function test_18_non_stackable_promotion_prevents_stacking(): void
    {
        Sanctum::actingAs($this->cashier);

        $p1 = Promotion::create([
            'name' => 'Product 10% Non-Stackable',
            'promotion_type' => 'percentage',
            'discount_scope' => 'product',
            'discount_value' => 10.00,
            'priority' => 10,
            'allow_stacking' => false,
            'is_active' => true,
        ]);
        PromotionTarget::create(['promotion_id' => $p1->id, 'target_type' => 'product', 'target_id' => $this->product->id]);

        $p2 = Promotion::create([
            'name' => 'Category 15% Lower Priority',
            'promotion_type' => 'percentage',
            'discount_scope' => 'category',
            'discount_value' => 15.00,
            'priority' => 5,
            'allow_stacking' => true,
            'is_active' => true,
        ]);
        PromotionTarget::create(['promotion_id' => $p2->id, 'target_type' => 'category', 'target_id' => $this->category->id]);

        $engine = app(PromotionEngineService::class);
        $res = $engine->evaluateCart([['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]]);

        $this->assertEquals(100.00, $res['total_discount_amount']); // Only 10% non-stackable applied
    }

    public function test_19_stackable_promotions_combine(): void
    {
        Sanctum::actingAs($this->cashier);

        $p1 = Promotion::create([
            'name' => 'Product 10% Stackable',
            'promotion_type' => 'percentage',
            'discount_scope' => 'product',
            'discount_value' => 10.00,
            'priority' => 10,
            'allow_stacking' => true,
            'is_active' => true,
        ]);
        PromotionTarget::create(['promotion_id' => $p1->id, 'target_type' => 'product', 'target_id' => $this->product->id]);

        Promotion::create([
            'name' => 'Cart ₹50 Stackable',
            'promotion_type' => 'fixed_amount',
            'discount_scope' => 'cart',
            'discount_value' => 50.00,
            'priority' => 5,
            'allow_stacking' => true,
            'is_active' => true,
        ]);

        $engine = app(PromotionEngineService::class);
        $res = $engine->evaluateCart([['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]]);

        $this->assertEquals(150.00, $res['total_discount_amount']); // 100 + 50
    }

    public function test_20_server_side_discount_calculation(): void
    {
        Sanctum::actingAs($this->cashier);

        Promotion::create([
            'name' => 'Fixed ₹100 Off',
            'code' => 'FIX100',
            'promotion_type' => 'fixed_amount',
            'discount_scope' => 'cart',
            'discount_value' => 100.00,
            'is_active' => true,
        ]);

        $res = $this->postJson('/api/v1/promotions/evaluate', [
            'coupon_code' => 'FIX100',
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(200);
        $this->assertEquals(100.00, $res->json('data.total_discount_amount'));
        $this->assertEquals(900.00, $res->json('data.final_subtotal'));
    }

    public function test_21_pos_sale_checkout_integration(): void
    {
        Sanctum::actingAs($this->cashier);

        Promotion::create([
            'name' => 'POS Checkout Coupon',
            'code' => 'POSCOUPON',
            'promotion_type' => 'fixed_amount',
            'discount_scope' => 'cart',
            'discount_value' => 200.00,
            'is_active' => true,
        ]);

        $uuid = (string) Str::uuid();

        $res = $this->postJson('/api/v1/pos/sales', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'customer_id' => $this->customer->id,
            'coupon_code' => 'POSCOUPON',
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
            'payment_method' => 'cash',
        ]);

        $res->assertStatus(201);
        $this->assertEquals(200.00, $res->json('data.discount_amount'));
        $this->assertEquals(800.00, $res->json('data.grand_total'));

        $this->assertDatabaseHas('promotion_usages', [
            'customer_id' => $this->customer->id,
            'discount_amount_applied' => 200.00,
            'client_trans_uuid' => $uuid,
        ]);
    }

    public function test_22_split_payment_compatibility(): void
    {
        Sanctum::actingAs($this->cashier);

        Promotion::create([
            'name' => 'Split Payment Promo',
            'code' => 'SPLITPROMO',
            'promotion_type' => 'fixed_amount',
            'discount_scope' => 'cart',
            'discount_value' => 100.00,
            'is_active' => true,
        ]);

        $uuid = (string) Str::uuid();

        // 1000 - 100 promo = 900 total (paid 500 cash + 400 card)
        $res = $this->postJson('/api/v1/pos/sales', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'customer_id' => $this->customer->id,
            'coupon_code' => 'SPLITPROMO',
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
            'payments' => [
                ['payment_method' => 'cash', 'amount' => 500.00],
                ['payment_method' => 'card', 'amount' => 400.00],
            ],
        ]);

        $res->assertStatus(201);
        $this->assertEquals(900.00, $res->json('data.grand_total'));
    }

    public function test_23_invoice_receipt_formatting_compatibility(): void
    {
        Sanctum::actingAs($this->cashier);

        $inv = Invoice::create([
            'invoice_number' => 'INV-PRM-REC',
            'client_trans_uuid' => (string) Str::uuid(),
            'store_id' => $this->store1->id,
            'customer_id' => $this->customer->id,
            'subtotal' => 1000.00,
            'discount_amount' => 150.00,
            'grand_total' => 850.00,
            'paid_amount' => 850.00,
            'payment_status' => 'paid',
            'status' => 'completed',
            'created_by' => $this->cashier->id,
        ]);

        $res = $this->getJson("/api/v1/pos/sales/{$inv->id}/invoice");

        $res->assertStatus(200);
        $this->assertEquals('INV-PRM-REC', $res->json('data.header.invoice_number'));
        $this->assertEquals(150.00, $res->json('data.financial_totals.discount_amount'));
        $this->assertEquals(850.00, $res->json('data.financial_totals.grand_total'));
    }

    public function test_24_sales_return_exchange_compatibility(): void
    {
        Sanctum::actingAs($this->cashier);

        $promo = Promotion::create([
            'name' => 'Return Promo',
            'promotion_type' => 'fixed_amount',
            'discount_scope' => 'cart',
            'discount_value' => 100.00,
            'is_active' => true,
        ]);

        PromotionUsage::create([
            'promotion_id' => $promo->id,
            'customer_id' => $this->customer->id,
            'discount_amount_applied' => 100.00,
            'client_trans_uuid' => (string) Str::uuid(),
        ]);

        $this->assertDatabaseHas('promotion_usages', ['discount_amount_applied' => 100.00]);
    }

    public function test_25_offline_sync_compatibility_idempotency(): void
    {
        Sanctum::actingAs($this->cashier);

        Promotion::create([
            'name' => 'Offline Coupon',
            'code' => 'OFFLINE100',
            'promotion_type' => 'fixed_amount',
            'discount_scope' => 'cart',
            'discount_value' => 100.00,
            'is_active' => true,
        ]);

        $uuid = (string) Str::uuid();

        $res = $this->postJson('/api/v1/pos/sales/sync', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'customer_id' => $this->customer->id,
            'coupon_code' => 'OFFLINE100',
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(200)->assertJsonPath('data.results.0.status', 'synced');

        // Retry sync
        $resRetry = $this->postJson('/api/v1/pos/sales/sync', [
            'client_trans_uuid' => $uuid,
            'store_id' => $this->store1->id,
            'pos_session_id' => $this->openSession1->id,
            'customer_id' => $this->customer->id,
            'coupon_code' => 'OFFLINE100',
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ]);

        $resRetry->assertStatus(200)->assertJsonPath('data.results.0.status', 'already_synced');

        // Usage recorded once
        $count = PromotionUsage::where('client_trans_uuid', $uuid)->count();
        $this->assertEquals(1, $count);
    }

    public function test_26_usage_limit_per_customer_enforced(): void
    {
        Sanctum::actingAs($this->cashier);

        $promo = Promotion::create([
            'name' => 'One-time Per Customer',
            'code' => 'ONETIME',
            'promotion_type' => 'fixed_amount',
            'discount_scope' => 'cart',
            'discount_value' => 50.00,
            'usage_limit_per_customer' => 1,
            'is_active' => true,
        ]);

        PromotionUsage::create([
            'promotion_id' => $promo->id,
            'customer_id' => $this->customer->id,
            'discount_amount_applied' => 50.00,
        ]);

        $res = $this->postJson('/api/v1/promotions/evaluate', [
            'customer_id' => $this->customer->id,
            'coupon_code' => 'ONETIME',
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_27_overall_promotion_usage_limit_enforced(): void
    {
        Sanctum::actingAs($this->cashier);

        Promotion::create([
            'name' => 'Exhausted Promo',
            'code' => 'EXHAUSTED',
            'promotion_type' => 'fixed_amount',
            'discount_scope' => 'cart',
            'discount_value' => 50.00,
            'usage_limit' => 1,
            'usage_count' => 1,
            'is_active' => true,
        ]);

        $res = $this->postJson('/api/v1/promotions/evaluate', [
            'coupon_code' => 'EXHAUSTED',
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(422)->assertJson(['success' => false]);
    }

    public function test_28_readonly_promotion_evaluation_endpoint(): void
    {
        Sanctum::actingAs($this->cashier);

        Promotion::create([
            'name' => 'Eval Promo',
            'promotion_type' => 'percentage',
            'discount_scope' => 'cart',
            'discount_value' => 10.00,
            'is_active' => true,
        ]);

        $res = $this->postJson('/api/v1/promotions/evaluate', [
            'items' => [['product_variant_size_id' => $this->variantSize1->id, 'quantity' => 1]],
        ]);

        $res->assertStatus(200);
        $this->assertEquals(1000.00, $res->json('data.original_subtotal'));
        $this->assertEquals(100.00, $res->json('data.total_discount_amount'));
        $this->assertEquals(900.00, $res->json('data.final_subtotal'));

        // Confirm 0 DB usage records created by evaluate
        $this->assertEquals(0, PromotionUsage::count());
    }

    public function test_29_standardized_api_responses(): void
    {
        Sanctum::actingAs($this->cashier);

        $res = $this->getJson('/api/v1/promotions');

        $res->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);
    }

    public function test_30_promotion_crud_endpoints(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // 1. Create
        $resCreate = $this->postJson('/api/v1/promotions', [
            'name' => 'Festival Discount',
            'code' => 'FESTIVAL',
            'promotion_type' => 'percentage',
            'discount_scope' => 'cart',
            'discount_value' => 15.00,
            'is_active' => true,
        ]);

        $resCreate->assertStatus(201);
        $promoId = $resCreate->json('data.id');

        // 2. Read
        $this->getJson("/api/v1/promotions/{$promoId}")->assertStatus(200)->assertJsonPath('data.name', 'Festival Discount');

        // 3. Update
        $this->putJson("/api/v1/promotions/{$promoId}", ['name' => 'Festival Discount Updated'])->assertStatus(200)->assertJsonPath('data.name', 'Festival Discount Updated');

        // 4. Delete
        $this->deleteJson("/api/v1/promotions/{$promoId}")->assertStatus(200);

        $this->assertDatabaseMissing('promotions', ['id' => $promoId]);
    }
}
