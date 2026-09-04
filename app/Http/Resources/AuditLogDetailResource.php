<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'audit_uuid' => $this->audit_uuid,
            'module' => $this->module,
            'event_type' => $this->event_type,
            'status' => $this->status,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username,
            ] : null,
            'store' => $this->store ? [
                'id' => $this->store->id,
                'code' => $this->store->code,
                'name' => $this->store->name,
            ] : null,
            'pos_session_id' => $this->pos_session_id,
            'pos_register_id' => $this->pos_register_id,
            'auditable_type' => $this->auditable_type,
            'auditable_id' => $this->auditable_id,
            'client_trans_uuid' => $this->client_trans_uuid,
            'before_state' => $this->before_state,
            'after_state' => $this->after_state,
            'changed_fields' => $this->changed_fields,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'reason_notes' => $this->reason_notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
