<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReturnItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_id' => $this->return_id,
            'invoice_item_id' => $this->invoice_item_id,
            'product_variant_size_id' => $this->product_variant_size_id,
            'sku' => $this->invoiceItem?->sku_snapshot ?? $this->variantSize?->sku,
            'article_number' => $this->invoiceItem?->article_number_snapshot ?? $this->variantSize?->variant?->product?->article_number,
            'product_name' => $this->invoiceItem?->product_name_snapshot ?? $this->variantSize?->variant?->product?->name,
            'color' => $this->invoiceItem?->color_name_snapshot ?? $this->variantSize?->variant?->color?->name,
            'size' => $this->invoiceItem?->size_number_snapshot ?? $this->variantSize?->size?->size_number,
            'quantity' => (int) $this->quantity,
            'refund_unit_price' => (float) $this->refund_unit_price,
            'restock_condition' => is_object($this->restock_condition) ? $this->restock_condition->value : (string) $this->restock_condition,
            'subtotal' => (float) $this->subtotal,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
