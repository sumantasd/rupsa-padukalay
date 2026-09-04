<?php

namespace Tests\Feature\Api\v1;

use App\Enums\StockMovementType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Color;
use App\Models\InventoryStock;
use App\Models\LowStockNotification;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantSize;
use App\Models\Role;
use App\Models\Size;
use App\Models\Store;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\LowStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LowStockNotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Store $store;
    protected ProductVariantSize $variantSize;
    protected InventoryService $inventoryService;
    protected LowStockService $lowStockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::create([
            'code' => 'STR-001',
            'name' => 'RUPSA PADUKALAYA - Main Outlet',
            'is_active' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'Super Admin'], ['guard_name' => 'web']);
        $permissions = ['products.view', 'products.create', 'products.edit', 'system.settings', 'pos.sell'];
        $permIds = [];
        foreach ($permissions as $p) {
            $permObj = Permission::firstOrCreate(['name' => $p], ['guard_name' => 'web', 'module_group' => 'inventory', 'display_name' => ucfirst($p)]);
            $permIds[] = $permObj->id;
        }
        $role->permissions()->syncWithoutDetaching($permIds);

        $this->admin = User::factory()->create([
            'username' => 'admin_' . uniqid(),
            'is_active' => true,
        ]);
        $this->admin->stores()->attach($this->store->id);
        $this->admin->roles()->attach($role->id, ['model_type' => User::class]);

        $brand = Brand::create(['name' => 'Campus', 'slug' => 'campus', 'is_active' => true]);
        $category = Category::create(['name' => 'Sports Shoes', 'slug' => 'sports-shoes', 'is_active' => true]);
        $color = Color::create(['name' => 'Black', 'code' => 'BLK', 'hex_code' => '#000000', 'is_active' => true]);
        $size = Size::create(['size_number' => '8', 'is_active' => true]);

        $product = Product::create([
            'store_id' => $this->store->id,
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'article_number' => 'CMP-SP-101',
            'name' => "Campus Men's Sports Shoe",
            'slug' => 'campus-mens-sports-shoe',
            'cost_price' => 500.00,
            'mrp' => 1200.00,
            'selling_price' => 999.00,
            'is_active' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'color_id' => $color->id,
            'is_active' => true,
        ]);

        $this->variantSize = ProductVariantSize::create([
            'product_variant_id' => $variant->id,
            'size_id' => $size->id,
            'sku' => 'CMP-SP-101-BLK-8',
            'cost_price' => 500.00,
            'mrp' => 1200.00,
            'selling_price' => 999.00,
            'is_active' => true,
        ]);

        $this->inventoryService = app(InventoryService::class);
        $this->lowStockService = app(LowStockService::class);
    }

    public function test_get_and_update_inventory_low_stock_settings_api(): void
    {
        $res = $this->actingAs($this->admin)->getJson('/api/v1/settings/inventory');
        $res->assertStatus(200)
            ->assertJsonPath('data.default_low_stock_threshold', 5)
            ->assertJsonPath('data.enable_low_stock_alerts', true);

        $updateRes = $this->actingAs($this->admin)->postJson('/api/v1/settings/inventory', [
            'default_low_stock_threshold' => 8,
            'default_out_of_stock_threshold' => 0,
            'enable_low_stock_alerts' => true,
            'enable_dashboard_notifications' => true,
            'enable_sound_notifications' => true,
        ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.default_low_stock_threshold', 8)
            ->assertJsonPath('data.enable_sound_notifications', true);

        $this->assertEquals(8, $this->lowStockService->getEffectiveThreshold($this->variantSize));
    }

    public function test_per_sku_threshold_override(): void
    {
        $this->lowStockService->saveSettings(['default_low_stock_threshold' => 5]);

        // Default falls back to 5
        $this->assertEquals(5, $this->lowStockService->getEffectiveThreshold($this->variantSize));

        // Set custom SKU threshold
        $this->variantSize->update(['low_stock_threshold' => 12, 'reorder_quantity' => 25]);
        $this->variantSize->refresh();

        $this->assertEquals(12, $this->lowStockService->getEffectiveThreshold($this->variantSize));
        $this->assertEquals(25, $this->lowStockService->getEffectiveReorderQty($this->variantSize, 12));
    }

    public function test_crossing_threshold_creates_single_low_stock_notification(): void
    {
        $this->lowStockService->saveSettings(['default_low_stock_threshold' => 5]);

        // Set initial stock 8 (Normal)
        $this->inventoryService->setStock($this->variantSize->id, 8, StockMovementType::OPENING_STOCK, null, null, $this->store->id, 0, 0, $this->admin);

        $this->assertEquals(0, LowStockNotification::where('is_read', false)->count());

        // Deduct 3 units (8 -> 5 = Threshold crossed)
        $this->inventoryService->deductStock($this->variantSize->id, 3, StockMovementType::SALE_POS, 'Invoice', 1, $this->store->id, 0, 0, $this->admin);

        $this->assertEquals(1, LowStockNotification::where('is_read', false)->count());

        $notification = LowStockNotification::where('is_read', false)->first();
        $this->assertEquals('low_stock', $notification->notification_type);
        $this->assertEquals(5, $notification->current_quantity);
        $this->assertStringContainsString('Campus Men\'s Sports Shoe', $notification->message);
        $this->assertStringContainsString('IND 8', $notification->message);
    }

    public function test_duplicate_notification_prevention(): void
    {
        $this->lowStockService->saveSettings(['default_low_stock_threshold' => 5]);

        // 8 -> 5 (Generates 1 alert)
        $this->inventoryService->setStock($this->variantSize->id, 8, StockMovementType::OPENING_STOCK, null, null, $this->store->id, 0, 0, $this->admin);
        $this->inventoryService->deductStock($this->variantSize->id, 3, StockMovementType::SALE_POS, 'Invoice', 1, $this->store->id, 0, 0, $this->admin);

        $this->assertEquals(1, LowStockNotification::where('is_read', false)->count());

        // 5 -> 4 -> 3 (No duplicate rows, only updates quantity)
        $this->inventoryService->deductStock($this->variantSize->id, 1, StockMovementType::SALE_POS, 'Invoice', 2, $this->store->id, 0, 0, $this->admin);
        $this->inventoryService->deductStock($this->variantSize->id, 1, StockMovementType::SALE_POS, 'Invoice', 3, $this->store->id, 0, 0, $this->admin);

        $this->assertEquals(1, LowStockNotification::where('is_read', false)->count());
        $this->assertEquals(3, LowStockNotification::where('is_read', false)->first()->current_quantity);
    }

    public function test_out_of_stock_notification_trigger(): void
    {
        $this->lowStockService->saveSettings(['default_low_stock_threshold' => 5]);

        $this->inventoryService->setStock($this->variantSize->id, 3, StockMovementType::OPENING_STOCK, null, null, $this->store->id, 0, 0, $this->admin);
        $this->assertEquals(1, LowStockNotification::where('notification_type', 'low_stock')->where('is_read', false)->count());

        // Deduct remaining 3 units (3 -> 0 = OUT OF STOCK)
        $this->inventoryService->deductStock($this->variantSize->id, 3, StockMovementType::SALE_POS, 'Invoice', 4, $this->store->id, 0, 0, $this->admin);

        // Previous low_stock alert marked read, 1 new out_of_stock alert created
        $this->assertEquals(0, LowStockNotification::where('notification_type', 'low_stock')->where('is_read', false)->count());
        $this->assertEquals(1, LowStockNotification::where('notification_type', 'out_of_stock')->where('is_read', false)->count());

        $outNotif = LowStockNotification::where('notification_type', 'out_of_stock')->where('is_read', false)->first();
        $this->assertEquals(0, $outNotif->current_quantity);
        $this->assertStringContainsString('completely OUT OF STOCK', $outNotif->message);
    }

    public function test_replenishment_resolves_alerts_and_retrigger_lifecycle(): void
    {
        $this->lowStockService->saveSettings(['default_low_stock_threshold' => 5]);

        // Stock 3 -> Low stock alert
        $this->inventoryService->setStock($this->variantSize->id, 3, StockMovementType::OPENING_STOCK, null, null, $this->store->id, 0, 0, $this->admin);
        $this->assertEquals(1, LowStockNotification::where('is_read', false)->count());

        // Replenish stock via Purchase: 3 -> 15 (Normal)
        $this->inventoryService->addStock($this->variantSize->id, 12, StockMovementType::PURCHASE, 'PurchaseOrder', 100, $this->store->id, 0, 0, $this->admin);

        // Active alert automatically resolved/marked as read
        $this->assertEquals(0, LowStockNotification::where('is_read', false)->count());

        // Stock drops back to 4 -> New alert triggered
        $this->inventoryService->deductStock($this->variantSize->id, 11, StockMovementType::SALE_POS, 'Invoice', 5, $this->store->id, 0, 0, $this->admin);

        $this->assertEquals(1, LowStockNotification::where('is_read', false)->count());
    }

    public function test_sales_return_and_exchange_stock_movement_alert_triggers(): void
    {
        $this->lowStockService->saveSettings(['default_low_stock_threshold' => 5]);

        // Stock 0 (Out of stock alert)
        $this->inventoryService->setStock($this->variantSize->id, 0, StockMovementType::OPENING_STOCK, null, null, $this->store->id, 0, 0, $this->admin);
        $this->assertEquals(1, LowStockNotification::where('notification_type', 'out_of_stock')->where('is_read', false)->count());

        // Resellable Sales return restores stock to 10 -> Resolves alert
        $this->inventoryService->addStock($this->variantSize->id, 10, StockMovementType::SALE_RETURN, 'SalesReturn', 50, $this->store->id, 0, 0, $this->admin);
        $this->assertEquals(0, LowStockNotification::where('is_read', false)->count());

        // Exchange deducts 6 -> Stock becomes 4 (Low stock alert)
        $this->inventoryService->deductStock($this->variantSize->id, 6, StockMovementType::EXCHANGE, 'Exchange', 10, $this->store->id, 0, 0, $this->admin);
        $this->assertEquals(1, LowStockNotification::where('notification_type', 'low_stock')->where('is_read', false)->count());
    }

    public function test_notification_list_and_unread_count_apis(): void
    {
        $this->lowStockService->saveSettings(['default_low_stock_threshold' => 5]);
        $this->inventoryService->setStock($this->variantSize->id, 3, StockMovementType::OPENING_STOCK, null, null, $this->store->id, 0, 0, $this->admin);

        $countRes = $this->actingAs($this->admin)->getJson('/api/v1/notifications/unread-count');
        $countRes->assertStatus(200)->assertJsonPath('data.unread_count', 1);

        $listRes = $this->actingAs($this->admin)->getJson('/api/v1/notifications/low-stock');
        $listRes->assertStatus(200)
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.article_number', 'CMP-SP-101')
            ->assertJsonPath('data.items.0.size_display', 'IND 8');
    }

    public function test_mark_single_and_all_as_read_apis(): void
    {
        $this->lowStockService->saveSettings(['default_low_stock_threshold' => 5]);
        $this->inventoryService->setStock($this->variantSize->id, 3, StockMovementType::OPENING_STOCK, null, null, $this->store->id, 0, 0, $this->admin);

        $notification = LowStockNotification::where('is_read', false)->first();

        $markRes = $this->actingAs($this->admin)->postJson("/api/v1/notifications/{$notification->id}/read");
        $markRes->assertStatus(200)
            ->assertJsonPath('data.is_read', true)
            ->assertJsonPath('data.unread_count', 0);

        $this->assertTrue(LowStockNotification::find($notification->id)->is_read);

        // Mark all as read endpoint
        $markAllRes = $this->actingAs($this->admin)->postJson('/api/v1/notifications/read-all');
        $markAllRes->assertStatus(200)->assertJsonPath('data.unread_count', 0);
    }
}
