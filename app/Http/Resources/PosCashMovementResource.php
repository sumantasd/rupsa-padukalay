<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosCashMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pos_session_id' => $this->pos_session_id,
            'pos_register_id' => $this->pos_register_id,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name,
            'movement_type' => $this->movement_type,
            'amount' => (float) $this->amount,
            'reason' => $this->reason,
            'reference_number' => $this->reference_number,
            'client_trans_uuid' => $this->client_trans_uuid,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
        ];
    }
}
