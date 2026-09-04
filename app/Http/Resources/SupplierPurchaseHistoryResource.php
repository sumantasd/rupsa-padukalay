<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierPurchaseHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statusVal = is_object($this->status) && property_exists($this->status, 'value')
            ? $this->status->value
            : (string) $this->status;

        return [
            'id' => $this->id,
            'po_number' => $this->po_number,
            'supplier_invoice_number' => $this->supplier_invoice_number,
            'store_id' => $this->store_id,
            'store_name' => $this->store?->name,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->warehouse?->name,
            'order_date' => $this->order_date ? $this->order_date->toDateString() : null,
            'received_date' => $this->received_date ? $this->received_date->toDateString() : null,
            'status' => $statusVal,
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'discount_amount' => (float) $this->discount_amount,
            'grand_total' => (float) $this->grand_total,
            'paid_amount' => (float) $this->paid_amount,
            'due_amount' => (float) $this->due_amount,
            'items_count' => $this->items->count(),
            'total_ordered_quantity' => (int) $this->items->sum('quantity_ordered'),
            'total_received_quantity' => (int) $this->items->sum('quantity_received'),
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
