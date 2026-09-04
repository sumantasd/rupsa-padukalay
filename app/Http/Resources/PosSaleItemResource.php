<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosSaleItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'product_variant_size_id' => $this->product_variant_size_id,
            'sku' => $this->sku_snapshot,
            'article_number' => $this->article_number_snapshot,
            'product_name' => $this->product_name_snapshot,
            'brand_name' => $this->variantSize?->variant?->product?->brand?->name ?? 'Footwear',
            'color_name' => $this->color_name_snapshot,
            'size_number' => $this->size_number_snapshot,
            'hsn_code' => $this->hsn_code_snapshot,
            'cost_price' => (float) $this->cost_price,
            'mrp' => (float) $this->mrp,
            'unit_price' => (float) $this->unit_price,
            'quantity' => (int) $this->quantity,
            'already_returned_quantity' => (int) $this->already_returned_quantity,
            'already_exchanged_quantity' => (int) $this->already_exchanged_quantity,
            'remaining_exchangeable_quantity' => (int) $this->remaining_exchangeable_quantity,
            'discount_amount' => (float) $this->discount_amount,
            'tax_rate_percentage' => (float) $this->tax_rate_percentage,
            'taxable_value' => (float) $this->taxable_value,
            'cgst_amount' => (float) $this->cgst_amount,
            'sgst_amount' => (float) $this->sgst_amount,
            'igst_amount' => (float) $this->igst_amount,
            'total_tax_amount' => (float) $this->total_tax_amount,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
