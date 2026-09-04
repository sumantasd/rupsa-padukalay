<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardProductPerformanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'top_products' => $this['top_products'] ?? [],
            'top_skus' => $this['top_skus'] ?? [],
            'top_categories' => $this['top_categories'] ?? [],
            'top_brands' => $this['top_brands'] ?? [],
        ];
    }
}
