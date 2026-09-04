<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerPurchaseSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'customer_id' => $this['customer_id'],
            'total_orders' => (int) $this['total_orders'],
            'total_gross_purchase_value' => (float) $this['total_gross_purchase_value'],
            'total_discounts' => (float) $this['total_discounts'],
            'total_tax' => (float) $this['total_tax'],
            'total_net_purchase_value' => (float) $this['total_net_purchase_value'],
            'total_paid_amount' => (float) $this['total_paid_amount'],
            'outstanding_amount' => (float) $this['outstanding_amount'],
            'total_returned_amount' => (float) $this['total_returned_amount'],
            'number_of_returns' => (int) $this['number_of_returns'],
            'total_items_purchased' => (int) ($this['total_items_purchased'] ?? 0),
            'first_purchase_date' => $this['first_purchase_date'],
            'last_purchase_date' => $this['last_purchase_date'],

        ];
    }
}
