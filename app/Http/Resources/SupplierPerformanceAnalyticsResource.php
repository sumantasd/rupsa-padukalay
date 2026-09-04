<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierPerformanceAnalyticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'supplier_id' => $this['supplier_id'],
            'fulfillment_rate' => (float) $this['fulfillment_rate'],
            'delivery_completion_rate' => (float) $this['delivery_completion_rate'],
            'return_rate' => (float) $this['return_rate'],
            'average_lead_time_days' => (float) $this['average_lead_time_days'],
            'supplier_score' => (float) $this['supplier_score'],
            'top_supplied_products' => $this['top_supplied_products'] ?? [],
            'monthly_spend_trends' => $this['monthly_spend_trends'] ?? [],
        ];
    }
}
