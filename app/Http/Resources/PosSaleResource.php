<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosSaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statusValue = is_object($this->status) && property_exists($this->status, 'value')
            ? $this->status->value
            : (string) $this->status;

        $saleTypeValue = is_object($this->sale_type) && property_exists($this->sale_type, 'value')
            ? $this->sale_type->value
            : (string) $this->sale_type;

        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'client_trans_uuid' => $this->client_trans_uuid,
            'store_id' => $this->store_id,
            'store_name' => $this->store?->name,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer?->name ?? 'Walk-in Customer',
            'customer_mobile' => $this->customer?->mobile_number,
            'customer' => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'mobile_number' => $this->customer->mobile_number,
                'email' => $this->customer->email,
            ] : null,
            'cashier_id' => $this->created_by,
            'cashier_name' => $this->creator?->name,
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'is_gst_enabled' => (bool) $this->is_gst_enabled,
            'taxable_amount' => (float) $this->taxable_amount,
            'total_cgst' => (float) $this->total_cgst,
            'total_sgst' => (float) $this->total_sgst,
            'total_igst' => (float) $this->total_igst,
            'total_tax' => (float) $this->total_tax,
            'grand_total' => (float) $this->grand_total,
            'paid_amount' => (float) $this->paid_amount,
            'change_returned' => (float) $this->change_returned,
            'payment_status' => $this->payment_status,
            'sale_type' => $saleTypeValue,
            'status' => $statusValue,
            'items' => PosSaleItemResource::collection($this->whenLoaded('items')),
            'payments' => $this->whenLoaded('payments', function () {
                return $this->payments->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'payment_method' => $p->payment_method,
                        'amount' => (float) $p->amount,
                        'transaction_reference' => $p->transaction_reference,
                        'notes' => $p->notes,
                        'payment_time' => $p->payment_time?->toIso8601String(),
                    ];
                });
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
