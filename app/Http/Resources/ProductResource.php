<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $primaryImage = $this->images?->where('is_primary', true)->first() ?? $this->images?->first();
        $firstSize = $this->variants?->flatMap->sizes->first();
        $totalStock = $this->variants?->flatMap->sizes->flatMap->inventoryStocks->sum('stock_quantity') ?? 0;

        return [
            'id' => $this->id,
            'article_number' => $this->article_number,
            'name' => $this->name,
            'slug' => $this->slug,
            'brand' => new BrandResource($this->whenLoaded('brand')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'hsn_code' => $this->whenLoaded('hsnCode'),
            'size_chart_id' => $this->size_chart_id,
            'size_chart' => $this->whenLoaded('sizeChart'),
            'gender' => $this->gender?->value ?? $this->gender,
            'upper_material' => $this->upper_material,
            'sole_material' => $this->sole_material,
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'is_visible_on_web' => (bool) $this->is_visible_on_web,
            'primary_image_url' => $primaryImage ? $primaryImage->image_path : null,
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'mrp' => $firstSize ? (float) $firstSize->mrp : null,
            'selling_price' => $firstSize ? (float) $firstSize->selling_price : null,
            'total_stock' => (int) $totalStock,
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
