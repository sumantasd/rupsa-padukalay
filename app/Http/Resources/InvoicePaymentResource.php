<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoicePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'payment_method' => is_object($this->payment_method) ? $this->payment_method->value : $this->payment_method,
            'amount' => (float) $this->amount,
            'transaction_reference' => $this->transaction_reference,
            'notes' => $this->notes,
            'payment_time' => $this->payment_time ? (is_string($this->payment_time) ? $this->payment_time : $this->payment_time->toIso8601String()) : null,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
