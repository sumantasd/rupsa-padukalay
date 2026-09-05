<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantSizeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_variant_id' => $this->product_variant_id,
            'size_id' => $this->size_id,
            'size_number' => $this->size?->size_number,
            'size_system' => $this->size?->size_system,
            'size' => $this->whenLoaded('size', function () {
                return [
                    'id' => $this->size->id,
                    'size_number' => $this->size->size_number,
                    'size_system' => $this->size->size_system,
                ];
            }),
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'cost_price' => (float) $this->cost_price,
            'mrp' => (float) $this->mrp,
            'selling_price' => (float) $this->selling_price,
            'stock_quantity' => (int) ($this->relationLoaded('inventoryStocks') ? ($this->inventoryStocks->where('store_id', (int) request('store_id', 1))->sum('stock_quantity')) : 0),
            'is_active' => (bool) $this->is_active,
        ];
    }
}
