<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoyaltyTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'store_id' => $this->store_id,
            'store_name' => $this->store?->name,
            'transaction_type' => $this->transaction_type,
            'points' => (int) $this->points,
            'points_balance_before' => (int) $this->points_balance_before,
            'points_balance_after' => (int) $this->points_balance_after,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'client_trans_uuid' => $this->client_trans_uuid,
            'performed_by' => $this->performed_by,
            'performed_by_name' => $this->performer?->name,
            'notes' => $this->notes,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
