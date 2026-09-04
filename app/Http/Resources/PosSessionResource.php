<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $statusValue = is_object($this->status) && property_exists($this->status, 'value')
            ? $this->status->value
            : (string) $this->status;

        return [
            'id' => $this->id,
            'client_session_uuid' => $this->client_session_uuid,
            'store_id' => $this->store_id,
            'store_name' => $this->store?->name,
            'user_id' => $this->user_id,
            'cashier_name' => $this->user?->name,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'opening_cash' => (float) $this->opening_cash,
            'closing_cash_system' => (float) $this->closing_cash_system,
            'closing_cash_actual' => (float) $this->closing_cash_actual,
            'cash_difference' => (float) $this->cash_difference,
            'status' => $statusValue,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
