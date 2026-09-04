<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionEvaluationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'original_subtotal' => (float) ($this['original_subtotal'] ?? 0.0),
            'total_discount_amount' => (float) ($this['total_discount_amount'] ?? 0.0),
            'final_subtotal' => (float) ($this['final_subtotal'] ?? 0.0),
            'applied_promotions' => $this['applied_promotions'] ?? [],
            'items' => $this['items'] ?? [],
        ];
    }
}
