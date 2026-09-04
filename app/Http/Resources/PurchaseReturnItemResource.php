<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseReturnItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $variantSize = $this->variantSize;
        $variant = $variantSize?->variant;
        $product = $variant?->product;

        return [
            'id' => $this->id,
            'purchase_return_id' => $this->purchase_return_id,
            'product_variant_size_id' => $this->product_variant_size_id,
            'sku' => $variantSize?->sku,
            'article_number' => $product?->article_number,
            'product_name' => $product?->name,
            'color_name' => $variant?->color?->name,
            'size_number' => $variantSize?->size?->size_number,
            'quantity' => (int) $this->quantity,
            'cost_price' => (float) $this->cost_price,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
