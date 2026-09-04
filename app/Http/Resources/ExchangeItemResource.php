<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExchangeItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $unitPrice = (float) ($this->unit_price ?? $this->refund_unit_price ?? 0.0);
        $mrp = (float) ($this->variantSize?->mrp ?? $unitPrice);

        return [
            'id' => $this->id,
            'product_variant_size_id' => $this->product_variant_size_id,
            'sku' => $this->sku_snapshot ?? $this->variantSize?->sku ?? 'N/A',
            'article_number' => $this->article_number_snapshot ?? $this->variantSize?->variant?->product?->article_number ?? 'N/A',
            'product_name' => $this->product_name_snapshot ?? $this->variantSize?->variant?->product?->name ?? 'Footwear Item',
            'brand_name' => $this->variantSize?->variant?->product?->brand?->name ?? '',
            'color' => $this->color_name_snapshot ?? $this->variantSize?->variant?->color?->name ?? 'Std',
            'color_name' => $this->color_name_snapshot ?? $this->variantSize?->variant?->color?->name ?? 'Std',
            'size' => $this->size_number_snapshot ?? $this->variantSize?->size?->size_number ?? 'N/A',
            'size_number' => $this->size_number_snapshot ?? $this->variantSize?->size?->size_number ?? 'N/A',
            'quantity' => (int) $this->quantity,
            'unit_price' => $unitPrice,
            'selling_price' => $unitPrice,
            'mrp' => $mrp,
            'restock_condition' => isset($this->restock_condition) ? (is_object($this->restock_condition) ? $this->restock_condition->value : (string) $this->restock_condition) : null,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
