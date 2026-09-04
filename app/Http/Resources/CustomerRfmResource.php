<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerRfmResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'customer_id' => $this['customer_id'],
            'recency_days' => $this['recency_days'] !== null ? (int) $this['recency_days'] : null,
            'frequency' => (int) $this['frequency'],
            'monetary_value' => (float) $this['monetary_value'],
            'recency_score' => (int) $this['recency_score'],
            'frequency_score' => (int) $this['frequency_score'],
            'monetary_score' => (int) $this['monetary_score'],
            'rfm_score' => (string) $this['rfm_score'],
            'segment' => (string) $this['segment'],
        ];
    }
}
