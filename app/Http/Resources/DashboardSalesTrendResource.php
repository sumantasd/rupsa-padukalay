<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardSalesTrendResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'period' => $this['period'],
            'gross_revenue' => (float) $this['gross_revenue'],
            'net_revenue' => (float) $this['net_revenue'],
            'completed_orders' => (int) $this['completed_orders'],
            'returns_count' => (int) $this['returns_count'],
            'refund_amount' => (float) $this['refund_amount'],
        ];
    }
}
