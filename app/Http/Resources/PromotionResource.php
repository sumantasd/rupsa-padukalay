<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PromotionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'promotion_type' => $this->promotion_type,
            'discount_scope' => $this->discount_scope,
            'discount_value' => (float) $this->discount_value,
            'buy_quantity' => (int) $this->buy_quantity,
            'get_quantity' => (int) $this->get_quantity,
            'get_discount_percentage' => (float) $this->get_discount_percentage,
            'min_cart_amount' => (float) $this->min_cart_amount,
            'max_discount_amount' => $this->max_discount_amount ? (float) $this->max_discount_amount : null,
            'store_id' => $this->store_id,
            'store_name' => $this->store?->name,
            'start_date' => $this->start_date ? $this->start_date->toIso8601String() : null,
            'end_date' => $this->end_date ? $this->end_date->toIso8601String() : null,
            'priority' => (int) $this->priority,
            'allow_stacking' => (bool) $this->allow_stacking,
            'usage_limit' => $this->usage_limit ? (int) $this->usage_limit : null,
            'usage_count' => (int) $this->usage_count,
            'usage_limit_per_customer' => $this->usage_limit_per_customer ? (int) $this->usage_limit_per_customer : null,
            'is_active' => (bool) $this->is_active,
            'targets' => $this->targets->map(fn ($t) => [
                'target_type' => $t->target_type,
                'target_id' => $t->target_id,
            ]),
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }
}
