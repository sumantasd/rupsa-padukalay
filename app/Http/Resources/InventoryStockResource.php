<?php

namespace App\Http\Resources;

use App\Services\LowStockService;
use App\Services\ImageUrlService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryStockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $variantSize = $this->variantSize;
        $variant = $variantSize?->variant;
        $product = $variant?->product;
        $supplier = $product?->supplier ?? \App\Models\Supplier::first();

        $primaryImage = $product?->images ? ($product->images->where('is_primary', true)->first() ?? $product->images->first()) : null;
        $imageUrl = ImageUrlService::format($primaryImage?->image_path);

        $lowStockService = app(LowStockService::class);
        $threshold = $variantSize ? $lowStockService->getEffectiveThreshold($variantSize) : (int) ($this->reorder_level ?? 5);
        $reorderQty = $variantSize ? $lowStockService->getEffectiveReorderQty($variantSize, $threshold) : max(10, $threshold * 2);

        $quantity = (int) $this->stock_quantity;

        if ($quantity <= 0) {
            $stockStatus = 'out_of_stock';
        } elseif ($quantity <= $threshold) {
            $stockStatus = 'low_stock';
        } else {
            $stockStatus = 'in_stock';
        }

        $sellingPrice = (float) ($variantSize?->selling_price ?? $product?->selling_price ?? 0.0);
        $costPrice = (float) ($variantSize?->cost_price ?? $product?->cost_price ?? ($sellingPrice > 0 ? round($sellingPrice * 0.6, 2) : 0.0));
        $mrp = (float) ($variantSize?->mrp ?? $product?->mrp ?? $sellingPrice);

        $stockValue = round(max(0, $quantity) * $costPrice, 2);
        $retailValue = round(max(0, $quantity) * $sellingPrice, 2);
        $potentialMargin = round($retailValue - $stockValue, 2);

        $sizeNum = $variantSize?->size?->size_number ?? 'N/A';
        $sizeDisplay = is_numeric($sizeNum) || str_starts_with(strtoupper($sizeNum), 'IND') ? (str_starts_with(strtoupper($sizeNum), 'IND') ? $sizeNum : 'IND ' . $sizeNum) : $sizeNum;

        $suggestedReorder = max($reorderQty, ($threshold > 0 ? $threshold * 2 : 10) - max(0, $quantity));

        return [
            'id' => $this->id,
            'product_id' => $product?->id,
            'product_variant_size_id' => $this->product_variant_size_id,
            'sku' => $variantSize?->sku ?? 'N/A',
            'barcode' => $variantSize?->barcode,
            'article_number' => $product?->article_number ?? 'N/A',
            'product_name' => $product?->name ?? 'Footwear Product',
            'primary_image_url' => $imageUrl,
            'product_image' => $imageUrl,
            'brand_id' => $product?->brand_id,
            'brand_name' => $product?->brand?->name ?? 'Generic Brand',
            'category_id' => $product?->category_id,
            'category_name' => $product?->category?->name ?? 'Footwear',
            'color_id' => $variant?->color_id,
            'color_name' => $variant?->color?->name ?? 'Standard',
            'color_code' => $variant?->color?->code ?? '#000000',
            'size_id' => $variantSize?->size_id,
            'size_number' => $sizeNum,
            'size_display' => $sizeDisplay,
            'mrp' => $mrp,
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
            'stock_value' => $stockValue,
            'retail_value' => $retailValue,
            'potential_margin' => $potentialMargin,
            'store_id' => $this->store_id,
            'store_code' => $this->store?->code ?? 'STR-001',
            'store_name' => $this->store?->name ?? 'RUPSA PADUKALAYA - Main Outlet',
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->warehouse?->name,
            'stock_location_id' => $this->stock_location_id,
            'stock_location_name' => $this->stockLocation?->name,
            'current_stock' => $quantity,
            'stock_quantity' => $quantity,
            'reserved_stock' => 0,
            'available_stock' => max(0, $quantity),
            'low_stock_threshold' => $threshold,
            'reorder_level' => $threshold,
            'reorder_quantity' => $reorderQty,
            'suggested_reorder_qty' => $suggestedReorder,
            'supplier_id' => $supplier?->id,
            'supplier_name' => $supplier?->name ?? 'Main Footwear Wholesale Supplier',
            'supplier_phone' => $supplier?->phone ?? '+91 9830012345',
            'stock_status' => $stockStatus,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String() ?? $this->created_at?->toIso8601String(),
        ];
    }
}
