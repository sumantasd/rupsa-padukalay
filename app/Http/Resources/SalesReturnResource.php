<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $refundModeValue = is_object($this->refund_mode) && property_exists($this->refund_mode, 'value')
            ? $this->refund_mode->value
            : (string) $this->refund_mode;

        return [
            'id' => $this->id,
            'return_number' => $this->return_number,
            'client_return_uuid' => $this->client_return_uuid,
            'original_invoice_id' => $this->original_invoice_id,
            'original_invoice_number' => $this->originalInvoice?->invoice_number,
            'store_id' => $this->store_id,
            'store_name' => $this->store?->name,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer?->name ?? 'Walk-in Customer',
            'total_refund_amount' => (float) $this->total_refund_amount,
            'refund_mode' => $refundModeValue,
            'reason' => $this->reason,
            'processed_by' => $this->processed_by,
            'processed_by_name' => $this->processor?->name,
            'items' => SalesReturnItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }
}
