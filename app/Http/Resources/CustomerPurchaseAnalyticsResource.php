<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerPurchaseAnalyticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'customer_id' => $this['customer_id'],
            'clv' => (float) $this['clv'],
            'aov' => (float) $this['aov'],
            'repurchase_rate' => (float) $this['repurchase_rate'],
            'purchase_frequency_days' => (float) $this['purchase_frequency_days'],
            'favorite_product' => $this['favorite_product'],
            'favorite_category' => $this['favorite_category'],
            'favorite_brand' => $this['favorite_brand'],
            'top_purchased_skus' => $this['top_purchased_skus'] ?? [],
            'last_purchase_info' => $this['last_purchase_info'],
            'monthly_trends' => $this['monthly_trends'] ?? [],
        ];
    }
}
