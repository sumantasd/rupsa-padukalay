<?php

namespace Tests\Feature\Api\v1;

use App\Enums\PurchaseStatus;
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
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Role;
use App\Models\Size;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected Store $store;
    protected Category $category;
    protected Brand $brand;
    protected Color $color;
    protected Size $size;

    protected function setUp(): void
    {
        parent::setUp();

        $permView = Permission::firstOrCreate(['name' => 'products.view'], ['guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'View Products']);
        $permEdit = Permission::firstOrCreate(['name' => 'products.edit'], ['guard_name' => 'web', 'module_group' => 'Products', 'display_name' => 'Edit Products']);

        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin'], ['guard_name' => 'web']);
        $superAdminRole->permissions()->syncWithoutDetaching([$permView->id, $permEdit->id]);

        $this->adminUser = User::create([
            'name' => 'Product Master Admin',
            'username' => 'prd_del_admin_' . rand(1000, 9999),
            'email' => 'prd_del_admin_' . rand(1000, 9999) . '@rupsa.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($superAdminRole->id, ['model_type' => User::class]);

        $this->store = Store::create([
            'code' => 'STR-001',
            'name' => 'RUPSA PADUKALAYA - Main Outlet',
            'phone' => '+91 9735125112',
            'address' => 'DHANTALA BAZAR, DHANTALA, NADIA - 741202, WEST BENGAL, INDIA',
            'is_active' => true,
        ]);

        $this->category = Category::create(['name' => 'Formal Shoes', 'slug' => 'formal-shoes']);
        $this->brand = Brand::create(['name' => 'Bata India', 'slug' => 'bata-india']);
        $this->color = Color::create(['name' => 'Black', 'code' => '#000000']);
        $this->size = Size::create(['system' => 'IND', 'size_number' => '8', 'display_name' => 'IND 8']);
    }

    public function test_product_without_transactions_can_be_deleted()
    {
        $product = Product::create([
            'article_number' => 'RP-DEL-01',
            'name' => 'Unused Test Shoe',
            'slug' => 'unused-test-shoe',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color_id' => $this->color->id,
        ]);

        $variantSize = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $this->size->id,
            'sku' => 'RP-DEL-01-BLK-8',
            'selling_price' => 999.00,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Product deleted successfully.');

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertSoftDeleted('product_variant_sizes', ['id' => $variantSize->id]);
    }

    public function test_product_with_invoice_items_cannot_be_deleted()
    {
        $product = Product::create([
            'article_number' => 'RP-DEL-02',
            'name' => 'Sold Test Shoe',
            'slug' => 'sold-test-shoe',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color_id' => $this->color->id,
        ]);

        $variantSize = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $this->size->id,
            'sku' => 'RP-DEL-02-BLK-8',
            'selling_price' => 999.00,
        ]);

        $customer = Customer::create(['name' => 'Test Customer', 'mobile_number' => '9830098300']);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-DEL-TEST',
            'client_trans_uuid' => 'uuid-del-02',
            'store_id' => $this->store->id,
            'customer_id' => $customer->id,
            'grand_total' => 999.00,
            'paid_amount' => 999.00,
            'status' => 'completed',
            'created_by' => $this->adminUser->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_variant_size_id' => $variantSize->id,
            'sku_snapshot' => 'RP-DEL-02-BLK-8',
            'article_number_snapshot' => 'RP-DEL-02',
            'product_name_snapshot' => 'Sold Test Shoe',
            'color_name_snapshot' => 'Black',
            'size_number_snapshot' => 'IND 8',
            'quantity' => 1,
            'unit_price' => 999.00,
            'subtotal' => 999.00,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'This product has transaction history, so it will be archived instead of permanently deleted.');

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_product_with_purchase_items_cannot_be_deleted()
    {
        $product = Product::create([
            'article_number' => 'RP-DEL-03',
            'name' => 'Purchased Test Shoe',
            'slug' => 'purchased-test-shoe',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color_id' => $this->color->id,
        ]);

        $variantSize = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $this->size->id,
            'sku' => 'RP-DEL-03-BLK-8',
            'selling_price' => 999.00,
        ]);

        $supplier = Supplier::create([
            'name' => 'Supplier Co',
            'supplier_code' => 'SUP-DEL-1',
            'phone' => '+91 9876543210',
            'is_active' => true,
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-DEL-TEST',
            'supplier_id' => $supplier->id,
            'store_id' => $this->store->id,
            'status' => PurchaseStatus::ORDERED,
            'order_date' => now(),
            'created_by' => $this->adminUser->id,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_variant_size_id' => $variantSize->id,
            'quantity_ordered' => 10,
            'cost_price' => 500.00,
            'mrp' => 600.00,
            'selling_price' => 700.00,
            'total_cost' => 5000.00,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'This product has transaction history, so it will be archived instead of permanently deleted.');

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_product_with_stock_movements_cannot_be_deleted()
    {
        $product = Product::create([
            'article_number' => 'RP-DEL-04',
            'name' => 'Stock Movement Test Shoe',
            'slug' => 'stock-movement-test-shoe',
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color_id' => $this->color->id,
        ]);

        $variantSize = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $this->size->id,
            'sku' => 'RP-DEL-04-BLK-8',
            'selling_price' => 999.00,
        ]);

        StockMovement::create([
            'store_id' => $this->store->id,
            'product_variant_size_id' => $variantSize->id,
            'movement_type' => StockMovementType::ADJUSTMENT_ADD,
            'quantity_change' => 5,
            'reference_type' => 'Manual',
            'reference_id' => 1,
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/v1/products/{$product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'This product has transaction history, so it will be archived instead of permanently deleted.');

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_non_existent_product_returns_404()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/v1/products/999999");

        $response->assertStatus(404);
    }
}
