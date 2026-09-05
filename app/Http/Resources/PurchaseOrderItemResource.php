<?php

namespace App\Http\Resources;

use App\Services\ImageUrlService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $variantSize = $this->variantSize;
        $variant = $variantSize?->variant;
        $product = $variant?->product;

        $sizeNum = $variantSize?->size?->size_number ?? 'N/A';
        $sizeDisplay = is_numeric($sizeNum) || str_starts_with(strtoupper($sizeNum), 'IND')
            ? (str_starts_with(strtoupper($sizeNum), 'IND') ? $sizeNum : 'IND ' . $sizeNum)
            : $sizeNum;

        $qtyOrdered = (int) $this->quantity_ordered;
        $qtyReceived = (int) $this->quantity_received;

        $primaryImage = $product?->images ? ($product->images->where('is_primary', true)->first() ?? $product->images->first()) : null;
        $imageUrl = ImageUrlService::format($primaryImage?->image_path);

        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'product_variant_size_id' => $this->product_variant_size_id,
            'sku' => $variantSize?->sku,
            'article_number' => $product?->article_number ?? 'N/A',
            'product_name' => $product?->name ?? 'Footwear Product',
            'brand_name' => $product?->brand?->name ?? 'Generic Brand',
            'category_name' => $product?->category?->name ?? 'Footwear',
            'color_name' => $variant?->color?->name ?? 'Standard Color',
            'size_number' => $sizeNum,
            'size_display' => $sizeDisplay,
            'quantity_ordered' => $qtyOrdered,
            'quantity_received' => $qtyReceived,
            'quantity_remaining' => max(0, $qtyOrdered - $qtyReceived),
            'cost_price' => (float) $this->cost_price,
            'mrp' => (float) $this->mrp,
            'selling_price' => (float) $this->selling_price,
            'total_cost' => (float) $this->total_cost,
            'image_url' => $imageUrl,
            'primary_image_url' => $imageUrl,
            'product_image' => $imageUrl,
        ];
    }
}
