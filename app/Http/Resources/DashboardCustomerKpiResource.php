<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardCustomerKpiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'new_customers_count' => (int) $this['new_customers_count'],
            'active_purchasing_customers' => (int) $this['active_purchasing_customers'],
            'repeat_customer_rate' => (float) $this['repeat_customer_rate'],
            'average_order_value' => (float) $this['average_order_value'],
            'customer_return_rate' => (float) $this['customer_return_rate'],
        ];
    }
}
