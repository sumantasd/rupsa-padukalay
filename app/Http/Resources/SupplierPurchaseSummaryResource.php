<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierPurchaseSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'supplier_id' => $this['supplier_id'],
            'total_purchase_orders' => (int) $this['total_purchase_orders'],
            'total_spend' => (float) $this['total_spend'],
            'total_paid_amount' => (float) $this['total_paid_amount'],
            'total_due_amount' => (float) $this['total_due_amount'],
            'total_returned_value' => (float) $this['total_returned_value'],
            'number_of_returns' => (int) $this['number_of_returns'],
            'pending_po_valuation' => (float) $this['pending_po_valuation'],
            'po_status_breakdown' => $this['po_status_breakdown'] ?? [],
            'first_order_date' => $this['first_order_date'],
            'last_order_date' => $this['last_order_date'],
        ];
    }
}
