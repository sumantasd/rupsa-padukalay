<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $variantSize = $this->variantSize;
        $variant = $variantSize?->variant;
        $product = $variant?->product;

        return [
            'id' => $this->id,
            'stock_transfer_id' => $this->stock_transfer_id,
            'product_variant_size_id' => $this->product_variant_size_id,
            'sku' => $variantSize?->sku,
            'article_number' => $product?->article_number,
            'product_name' => $product?->name,
            'color_name' => $variant?->color?->name,
            'size_number' => $variantSize?->size?->size_number,
            'quantity_sent' => (int) $this->quantity_sent,
            'quantity_received' => $this->quantity_received !== null ? (int) $this->quantity_received : null,
        ];
    }
}
