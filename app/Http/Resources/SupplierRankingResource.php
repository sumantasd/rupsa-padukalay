<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierRankingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'rank' => (int) $this['rank'],
            'supplier_id' => $this['supplier_id'],
            'supplier_name' => $this['supplier_name'],
            'company_name' => $this['company_name'],
            'total_spend' => (float) $this['total_spend'],
            'fulfillment_rate' => (float) $this['fulfillment_rate'],
            'return_rate' => (float) $this['return_rate'],
            'average_lead_time_days' => (float) $this['average_lead_time_days'],
            'supplier_score' => (float) $this['supplier_score'],
        ];
    }
}
