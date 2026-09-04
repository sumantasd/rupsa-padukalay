<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosSalePaymentSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $grandTotal = (float) $this->grand_total;
        $paidAmount = (float) $this->paid_amount;
        $remainingBalance = max(0.0, round($grandTotal - $paidAmount, 2));

        return [
            'invoice_id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'store_id' => $this->store_id,
            'pos_session_id' => $this->pos_session_id,
            'customer_id' => $this->customer_id,
            'grand_total' => $grandTotal,
            'paid_amount' => $paidAmount,
            'remaining_balance' => $remainingBalance,
            'payment_status' => is_object($this->payment_status) ? $this->payment_status->value : $this->payment_status,
            'status' => is_object($this->status) ? $this->status->value : $this->status,
            'payments' => InvoicePaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
