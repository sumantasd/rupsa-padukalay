<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAdjustmentItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $variantSize = $this->variantSize;
        $variant = $variantSize?->variant;
        $product = $variant?->product;

        return [
            'id' => $this->id,
            'stock_adjustment_id' => $this->stock_adjustment_id,
            'product_variant_size_id' => $this->product_variant_size_id,
            'sku' => $variantSize?->sku,
            'article_number' => $product?->article_number,
            'product_name' => $product?->name,
            'color_name' => $variant?->color?->name,
            'size_number' => $variantSize?->size?->size_number,
            'old_quantity' => (int) $this->old_quantity,
            'new_quantity' => (int) $this->new_quantity,
            'quantity_adjusted' => (int) $this->quantity_adjusted,
        ];
    }
}
