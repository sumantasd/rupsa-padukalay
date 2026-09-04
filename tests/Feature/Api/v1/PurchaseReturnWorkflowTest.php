<?php

namespace Tests\Feature\Api\v1;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\InventoryStock;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\PurchaseBill;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Role;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseReturnWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Store $store;
    protected Supplier $supplier;
    protected ProductVariantSize $pvs1;
    protected ProductVariantSize $pvs2;

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
            'username' => 'rupsa_rtv_tester',
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
            'products.view', 'products.create', 'products.edit',
            'procurement.view', 'procurement.receive', 'suppliers.view',
        ];

        foreach ($permissions as $p) {
            $perm = Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web'], [
                'display_name' => ucfirst($p),
                'module_group' => 'purchases',
            ]);
            $role->permissions()->syncWithoutDetaching([$perm->id]);
        }

        DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_id' => $this->user->id,
            'model_type' => User::class,
        ]);

        $this->supplier = Supplier::create([
            'code' => 'SUP-0002',
            'name' => 'Bata Footwear Ltd',
            'company_name' => 'Bata India',
            'phone' => '9830098300',
            'is_active' => true,
        ]);

        $brand = Brand::create(['name' => 'Bata', 'slug' => 'bata', 'code' => 'BT', 'is_active' => true]);
        $category = Category::create(['name' => 'Casual Shoes', 'slug' => 'casual-shoes', 'code' => 'CASUAL', 'is_active' => true]);
        $color = Color::create(['name' => 'White/Blue', 'code' => 'WBL']);

        $size10 = Size::create(['size_number' => '10', 'size_system' => 'IND', 'is_active' => true]);
        $size11 = Size::create(['size_number' => '11', 'size_system' => 'IND', 'is_active' => true]);

        $p = Product::create([
            'article_number' => 'RP-610',
            'name' => 'Urban Flex Casual Sneaker',
            'slug' => 'urban-flex-casual-sneaker',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $v = ProductVariant::create(['product_id' => $p->id, 'color_id' => $color->id, 'is_active' => true]);

        $this->pvs1 = ProductVariantSize::create([
            'product_variant_id' => $v->id,
            'size_id' => $size10->id,
            'sku' => 'RP-610-WHITEBLUE-UK10',
            'cost_price' => 959.40,
            'selling_price' => 1499.00,
            'mrp' => 1999.00,
            'is_active' => true,
        ]);

        $this->pvs2 = ProductVariantSize::create([
            'product_variant_id' => $v->id,
            'size_id' => $size11->id,
            'sku' => 'RP-610-WHITEBLUE-UK11',
            'cost_price' => 959.40,
            'selling_price' => 1499.00,
            'mrp' => 1999.00,
            'is_active' => true,
        ]);

        // Seed initial physical stock: 7 pairs of IND 10, 3 pairs of IND 11
        InventoryStock::create([
            'product_variant_size_id' => $this->pvs1->id,
            'store_id' => $this->store->id,
            'stock_quantity' => 7,
        ]);

        InventoryStock::create([
            'product_variant_size_id' => $this->pvs2->id,
            'store_id' => $this->store->id,
            'stock_quantity' => 3,
        ]);
    }

    /** @test */
    public function purchase_return_rejects_missing_supplier(): void
    {
        $res = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchases/returns', [
                'reason' => 'Damaged',
                'items' => [
                    ['product_variant_size_id' => $this->pvs1->id, 'quantity' => 2, 'cost_price' => 959.40],
                ],
            ]);

        $res->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /** @test */
    public function purchase_return_deducts_exact_size_wise_stock_and_creates_purchase_return_movement(): void
    {
        // Return 3 pairs of RP-610 IND 10 (Current Stock = 7)
        $res = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchases/returns', [
                'supplier_id' => $this->supplier->id,
                'store_id' => $this->store->id,
                'reason' => 'Damaged',
                'refund_mode' => 'supplier_credit',
                'items' => [
                    ['product_variant_size_id' => $this->pvs1->id, 'quantity' => 3, 'cost_price' => 959.40],
                ],
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('success', true);

        // Assert exact size-wise stock deduction: IND 10 drops from 7 -> 4
        $this->assertEquals(4, InventoryStock::where('product_variant_size_id', $this->pvs1->id)->value('stock_quantity'));

        // Assert IND 11 stock remains untouched at 3
        $this->assertEquals(3, InventoryStock::where('product_variant_size_id', $this->pvs2->id)->value('stock_quantity'));

        // Assert Stock Movement created with movement_type = purchase_return
        $this->assertDatabaseHas('stock_movements', [
            'product_variant_size_id' => $this->pvs1->id,
            'movement_type' => 'purchase_return',
            'quantity_change' => -3,
            'stock_before' => 7,
            'stock_after' => 4,
        ]);
    }

    /** @test */
    public function purchase_return_blocks_returning_more_quantity_than_available_physical_stock(): void
    {
        // Attempt returning 10 pairs when current stock is 7
        $res = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchases/returns', [
                'supplier_id' => $this->supplier->id,
                'store_id' => $this->store->id,
                'reason' => 'Defective',
                'refund_mode' => 'supplier_credit',
                'items' => [
                    ['product_variant_size_id' => $this->pvs1->id, 'quantity' => 10, 'cost_price' => 959.40],
                ],
            ]);

        $res->assertStatus(422)
            ->assertJsonPath('success', false);

        // Assert physical stock remains unchanged at 7
        $this->assertEquals(7, InventoryStock::where('product_variant_size_id', $this->pvs1->id)->value('stock_quantity'));
    }

    /** @test */
    public function purchase_return_adjusts_supplier_payable_bill_due(): void
    {
        // Create an unpaid purchase bill of ₹10,000 for supplier
        $bill = PurchaseBill::create([
            'bill_number' => 'BILL-TEST-99',
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'bill_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 10000.00,
            'grand_total' => 10000.00,
            'paid_amount' => 3000.00,
            'due_amount' => 7000.00,
            'payment_status' => 'partially_paid',
            'created_by' => $this->user->id,
        ]);

        // Process RTV of ₹2,878.20 (3 pairs @ 959.40)
        $res = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchases/returns', [
                'supplier_id' => $this->supplier->id,
                'store_id' => $this->store->id,
                'reason' => 'Wrong Size',
                'refund_mode' => 'supplier_credit',
                'items' => [
                    ['product_variant_size_id' => $this->pvs1->id, 'quantity' => 3, 'cost_price' => 959.40],
                ],
            ]);

        $res->assertStatus(201);

        // Assert supplier due updated: 7,000 - 2,878.20 = 4,121.80
        $this->assertEquals(4121.80, PurchaseBill::find($bill->id)->due_amount);
    }
}
