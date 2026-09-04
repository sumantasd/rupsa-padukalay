<?php

namespace App\Services;

use App\Models\CmsSetting;
use App\Models\InventoryStock;
use App\Models\LowStockNotification;
use App\Models\ProductVariantSize;
use Illuminate\Support\Facades\Log;

class LowStockService
{
    /**
     * Get default low stock system settings.
     */
    public function getDefaultSettings(): array
    {
        return [
            'enable_low_stock_alerts' => true,
            'default_low_stock_threshold' => 5,
            'default_out_of_stock_threshold' => 0,
            'enable_dashboard_notifications' => true,
            'enable_sound_notifications' => false,
        ];
    }

    /**
     * Get current configurable low stock settings.
     */
    public function getSettings(): array
    {
        $raw = CmsSetting::getSetting('inventory_low_stock_settings');
        $saved = [];
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $saved = $decoded;
            }
        }
        return array_replace_recursive($this->getDefaultSettings(), $saved);
    }

    /**
     * Save updated low stock settings.
     */
    public function saveSettings(array $payload): array
    {
        $current = $this->getSettings();
        $merged = array_replace_recursive($current, $payload);
        CmsSetting::setSetting('inventory_low_stock_settings', json_encode($merged));
        return $merged;
    }

    /**
     * Resolve effective threshold for a SKU (Custom SKU override vs Global Default).
     */
    public function getEffectiveThreshold(ProductVariantSize $variantSize): int
    {
        if ($variantSize->low_stock_threshold !== null && (int) $variantSize->low_stock_threshold > 0) {
            return (int) $variantSize->low_stock_threshold;
        }

        $settings = $this->getSettings();
        return (int) ($settings['default_low_stock_threshold'] ?? 5);
    }

    /**
     * Resolve effective reorder quantity for a SKU.
     */
    public function getEffectiveReorderQty(ProductVariantSize $variantSize, int $effectiveThreshold): int
    {
        if ($variantSize->reorder_quantity !== null && (int) $variantSize->reorder_quantity > 0) {
            return (int) $variantSize->reorder_quantity;
        }

        return max(10, $effectiveThreshold * 2);
    }

    /**
     * Centralized low stock alert & notification lifecycle trigger.
     * Evaluates current stock, prevents duplicate alerts, creates or updates notifications.
     */
    public function checkAndUpdateAlerts(int $productVariantSizeId, int $storeId = 1): ?LowStockNotification
    {
        $settings = $this->getSettings();
        if (empty($settings['enable_low_stock_alerts'])) {
            return null;
        }

        $variantSize = ProductVariantSize::with(['variant.product', 'variant.color', 'size'])->find($productVariantSizeId);
        if (! $variantSize) {
            return null;
        }

        $stockRecord = InventoryStock::where('product_variant_size_id', $productVariantSizeId)
            ->where('store_id', $storeId > 0 ? $storeId : 1)
            ->first();

        $currentStock = $stockRecord ? (int) $stockRecord->stock_quantity : 0;
        $threshold = $this->getEffectiveThreshold($variantSize);
        $reorderQty = $this->getEffectiveReorderQty($variantSize, $threshold);

        $product = $variantSize->variant?->product;
        $productName = $product?->name ?? 'Footwear Item';
        $articleNumber = $product?->article_number ?? 'N/A';
        $colorName = $variantSize->variant?->color?->name ?? 'Std';
        $sizeNum = $variantSize->size?->size_number ?? 'N/A';
        $sizeDisplay = is_numeric($sizeNum) || str_starts_with(strtoupper($sizeNum), 'IND') ? (str_starts_with(strtoupper($sizeNum), 'IND') ? $sizeNum : 'IND ' . $sizeNum) : $sizeNum;

        $targetStoreId = $storeId > 0 ? $storeId : 1;

        // STATE 1: NORMAL STOCK (stock > threshold)
        if ($currentStock > $threshold) {
            // Resolve/mark as read any active unread notifications for this SKU
            LowStockNotification::where('product_variant_size_id', $productVariantSizeId)
                ->where('is_read', false)
                ->update(['is_read' => true, 'read_at' => now()]);

            return null;
        }

        // STATE 2: OUT OF STOCK (stock <= 0)
        if ($currentStock <= 0) {
            // Mark any unread low_stock alert as read
            LowStockNotification::where('product_variant_size_id', $productVariantSizeId)
                ->where('notification_type', 'low_stock')
                ->where('is_read', false)
                ->update(['is_read' => true, 'read_at' => now()]);

            // Check if unread out_of_stock notification already exists
            $existing = LowStockNotification::where('product_variant_size_id', $productVariantSizeId)
                ->where('notification_type', 'out_of_stock')
                ->where('is_read', false)
                ->first();

            if ($existing) {
                $existing->current_quantity = $currentStock;
                $existing->threshold_quantity = $threshold;
                $existing->reorder_quantity = $reorderQty;
                $existing->save();
                return $existing;
            }

            return LowStockNotification::create([
                'store_id' => $targetStoreId,
                'product_id' => $product?->id,
                'product_variant_id' => $variantSize->product_variant_id,
                'product_variant_size_id' => $productVariantSizeId,
                'notification_type' => 'out_of_stock',
                'current_quantity' => $currentStock,
                'threshold_quantity' => $threshold,
                'reorder_quantity' => $reorderQty,
                'title' => 'Out of Stock Alert',
                'message' => "{$productName} — {$colorName} — {$sizeDisplay} (Article: {$articleNumber}) is completely OUT OF STOCK (0 units available).",
                'is_read' => false,
            ]);
        }

        // STATE 3: LOW STOCK (0 < stock <= threshold)
        // Mark any unread out_of_stock alert as read
        LowStockNotification::where('product_variant_size_id', $productVariantSizeId)
            ->where('notification_type', 'out_of_stock')
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        // Check if unread low_stock notification already exists
        $existingLow = LowStockNotification::where('product_variant_size_id', $productVariantSizeId)
            ->where('notification_type', 'low_stock')
            ->where('is_read', false)
            ->first();

        if ($existingLow) {
            $existingLow->current_quantity = $currentStock;
            $existingLow->threshold_quantity = $threshold;
            $existingLow->reorder_quantity = $reorderQty;
            $existingLow->save();
            return $existingLow;
        }

        return LowStockNotification::create([
            'store_id' => $targetStoreId,
            'product_id' => $product?->id,
            'product_variant_id' => $variantSize->product_variant_id,
            'product_variant_size_id' => $productVariantSizeId,
            'notification_type' => 'low_stock',
            'current_quantity' => $currentStock,
            'threshold_quantity' => $threshold,
            'reorder_quantity' => $reorderQty,
            'title' => 'Low Stock Alert',
            'message' => "{$productName} — {$colorName} — {$sizeDisplay} (Article: {$articleNumber}) has only {$currentStock} units left (Threshold: {$threshold}).",
            'is_read' => false,
        ]);
    }
}
