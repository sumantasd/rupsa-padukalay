<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosSyncConflictResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_trans_uuid' => $this->client_trans_uuid,
            'store_id' => $this->store_id,
            'store_name' => $this->store?->name,
            'pos_session_id' => $this->pos_session_id,
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name,
            'invoice_id' => $this->invoice_id,
            'invoice_number' => $this->invoice?->invoice_number,
            'conflict_type' => $this->conflict_type,
            'conflict_reason' => $this->conflict_reason,
            'payload_snapshot' => $this->payload_snapshot,
            'status' => $this->status,
            'resolution_action' => $this->resolution_action,
            'resolution_notes' => $this->resolution_notes,
            'resolved_by' => $this->resolved_by,
            'resolved_by_name' => $this->resolver?->name,
            'resolved_at' => $this->resolved_at ? $this->resolved_at->toIso8601String() : null,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }
}
