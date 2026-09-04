<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statusValue = is_object($this->status) && property_exists($this->status, 'value')
            ? $this->status->value
            : (string) $this->status;

        return [
            'id' => $this->id,
            'po_number' => $this->po_number,
            'supplier_invoice_number' => $this->supplier_invoice_number,
            'supplier_id' => $this->supplier_id,
            'supplier_code' => $this->supplier?->code,
            'supplier_name' => $this->supplier?->name,
            'supplier_company' => $this->supplier?->company_name,
            'supplier_phone' => $this->supplier?->phone,
            'supplier_gstin' => $this->supplier?->gstin,
            'supplier_address' => $this->supplier?->address,
            'supplier_city' => $this->supplier?->city,
            'supplier_state' => $this->supplier?->state,
            'store_id' => $this->store_id,
            'store_name' => $this->store?->name,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->warehouse?->name,
            'order_date' => $this->order_date?->format('Y-m-d'),
            'received_date' => $this->received_date?->format('Y-m-d'),
            'status' => $statusValue,
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'discount_amount' => (float) $this->discount_amount,
            'grand_total' => (float) $this->grand_total,
            'paid_amount' => (float) $this->paid_amount,
            'due_amount' => (float) $this->due_amount,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'creator_name' => $this->creator?->name,
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
