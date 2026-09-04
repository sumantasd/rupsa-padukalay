<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosRegisterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $activeSession = $this->sessions->firstWhere('status.value', 'open') ?? $this->sessions->firstWhere('status', 'open');

        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'store_name' => $this->store?->name,
            'code' => $this->code,
            'name' => $this->name,
            'is_active' => (bool) $this->is_active,
            'assigned_user_id' => $this->assigned_user_id,
            'assigned_user_name' => $this->assignedUser?->name,
            'has_active_session' => $activeSession !== null,
            'active_session_id' => $activeSession?->id,
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }
}
