<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'color_id' => $this->color_id,
            'color_name' => $this->color?->name,
            'color_code' => $this->color?->code,
            'hex_code' => $this->color?->hex_code,
            'color' => $this->whenLoaded('color', function () {
                return [
                    'id' => $this->color->id,
                    'name' => $this->color->name,
                    'code' => $this->color->code,
                    'hex_code' => $this->color->hex_code,
                ];
            }),
            'is_active' => (bool) $this->is_active,
            'sizes' => ProductVariantSizeResource::collection($this->whenLoaded('sizes')),
        ];
    }
}
