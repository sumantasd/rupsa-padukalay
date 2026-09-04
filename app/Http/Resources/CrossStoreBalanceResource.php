<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrossStoreBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_variant_size_id' => (int) $this['product_variant_size_id'],
            'sku' => $this['sku'],
            'article_number' => $this['article_number'],
            'product_name' => $this['product_name'],
            'brand_name' => $this['brand_name'],
            'category_name' => $this['category_name'],
            'cost_price' => (float) $this['cost_price'],
            'selling_price' => (float) $this['selling_price'],
            'total_stock_quantity' => (int) $this['total_stock_quantity'],
            'reorder_level' => (int) $this['reorder_level'],
            'stock_status' => $this['stock_status'],
            'store_breakdown' => $this['store_breakdown'] ?? [],
        ];
    }
}
