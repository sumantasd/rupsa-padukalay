<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\HsnCode;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\Size;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinalScopeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'Super Admin'], ['guard_name' => 'web']);
        $this->store = Store::create([
            'code' => 'STR-001',
            'name' => 'Main Outlet',
            'phone' => '9876543210',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@rupsapadukalaya.in',
            'username' => 'rupsa_admin',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->admin->roles()->attach($role->id, ['model_type' => User::class]);
        $this->admin->stores()->attach($this->store->id, ['is_default' => true]);
    }

    /** @test */
    public function product_without_transactions_is_soft_deleted_and_archived()
    {
        $brand = Brand::create(['name' => 'Nike', 'slug' => 'nike']);
        $category = Category::create(['name' => 'Shoes', 'slug' => 'shoes']);
        $product = Product::create([
            'article_number' => 'ART-101',
            'name' => 'Running Shoe 101',
            'slug' => 'art-101-running-shoe',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'gender' => 'unisex',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Product deleted successfully.',
            ]);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    /** @test */
    public function product_with_transactions_is_archived_with_informative_message()
    {
        $brand = Brand::create(['name' => 'Adidas', 'slug' => 'adidas']);
        $category = Category::create(['name' => 'Sneakers', 'slug' => 'sneakers']);
        $color = Color::create(['name' => 'Black', 'code' => 'BLK']);
        $size = Size::create(['size_number' => 8]);

        $product = Product::create([
            'article_number' => 'ART-202',
            'name' => 'Sports Shoe 202',
            'slug' => 'art-202-sports-shoe',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'gender' => 'men',
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color_id' => $color->id,
            'is_active' => true,
        ]);

        $vSize = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $size->id,
            'sku' => 'ART202-BLK-08',
            'cost_price' => 500,
            'mrp' => 1000,
            'selling_price' => 900,
        ]);

        $customer = Customer::create(['name' => 'John Doe', 'mobile_number' => '9999999999']);
        $invoice = Invoice::create([
            'invoice_number' => 'INV-001',
            'client_trans_uuid' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'store_id' => $this->store->id,
            'created_by' => $this->admin->id,
            'subtotal' => 900,
            'tax_amount' => 0,
            'grand_total' => 900,
            'status' => 'completed',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_variant_size_id' => $vSize->id,
            'sku_snapshot' => 'ART202-BLK-08',
            'article_number_snapshot' => 'ART-202',
            'product_name_snapshot' => 'Sports Shoe 202',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => 8,
            'unit_price' => 900,
            'quantity' => 1,
            'subtotal' => 900,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'This product has transaction history, so it will be archived instead of permanently deleted.',
            ]);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertDatabaseHas('invoice_items', ['product_variant_size_id' => $vSize->id]);
    }

    /** @test */
    public function recycle_bin_lists_trashed_items_and_restores_them()
    {
        $brand = Brand::create(['name' => 'Puma', 'slug' => 'puma']);
        $category = Category::create(['name' => 'Boots', 'slug' => 'boots']);
        $product = Product::create([
            'article_number' => 'ART-303',
            'name' => 'Archived Boot 303',
            'slug' => 'art-303-archived-boot',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'gender' => 'unisex',
            'is_active' => true,
        ]);

        $product->delete();

        $listRes = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/recycle-bin?type=products');

        $listRes->assertStatus(200)
            ->assertJsonFragment(['code_or_sku' => 'ART-303']);

        $restoreRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/recycle-bin/products/{$product->id}/restore");

        $restoreRes->assertStatus(200);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'deleted_at' => null]);
    }

    /** @test */
    public function recycle_bin_blocks_permanent_deletion_when_transactional_dependencies_exist()
    {
        $brand = Brand::create(['name' => 'Bata', 'slug' => 'bata']);
        $category = Category::create(['name' => 'Formal', 'slug' => 'formal']);
        $color = Color::create(['name' => 'Brown', 'code' => 'BRN']);
        $size = Size::create(['size_number' => 7]);

        $product = Product::create([
            'article_number' => 'ART-404',
            'name' => 'Formal Shoe 404',
            'slug' => 'art-404-formal-shoe',
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'gender' => 'men',
            'is_active' => true,
        ]);

        $variant = ProductVariant::create(['product_id' => $product->id, 'color_id' => $color->id]);
        $vSize = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $size->id,
            'sku' => 'ART404-BRN-07',
            'cost_price' => 400,
            'mrp' => 800,
            'selling_price' => 750,
        ]);

        $supplier = Supplier::create(['code' => 'SUP-001', 'name' => 'Best Footwear Suppliers', 'phone' => '9876500000']);
        $po = PurchaseOrder::create([
            'po_number' => 'PO-20260906-0001',
            'supplier_id' => $supplier->id,
            'store_id' => $this->store->id,
            'created_by' => $this->admin->id,
            'order_date' => now()->toDateString(),
            'status' => 'draft',
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_variant_size_id' => $vSize->id,
            'quantity_ordered' => 10,
            'cost_price' => 400,
            'mrp' => 800,
            'selling_price' => 750,
            'total_cost' => 4000,
        ]);

        $product->delete();

        $forceDeleteRes = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/recycle-bin/products/{$product->id}/force-delete");

        $forceDeleteRes->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    /** @test */
    public function full_expense_management_flow_works()
    {
        $catRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/expense-categories', [
                'name' => 'Store Electricity',
                'code' => 'POWER',
                'description' => 'Power supply charges',
            ]);

        $catRes->assertStatus(201);
        $catId = $catRes->json('data.id');

        $expRes = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/expenses', [
                'expense_category_id' => $catId,
                'store_id' => $this->store->id,
                'amount' => 1500.50,
                'payment_method' => 'cash',
                'payee_name' => 'WBSEDCL',
                'description' => 'Monthly store electricity bill',
                'expense_date' => now()->toDateString(),
            ]);

        $expRes->assertStatus(201)
            ->assertJsonFragment(['amount' => 1500.5, 'payee_name' => 'WBSEDCL']);

        $expId = $expRes->json('data.id');

        $reportRes = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/expenses/reports');

        $reportRes->assertStatus(200)
            ->assertJsonFragment(['total_expenses' => 1500.5]);

        $deleteRes = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/expenses/{$expId}");

        $deleteRes->assertStatus(200);
        $this->assertSoftDeleted('expenses', ['id' => $expId]);
    }
}
