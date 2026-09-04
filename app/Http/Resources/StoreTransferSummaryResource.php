<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreTransferSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total_transfers' => (int) $this['total_transfers'],
            'total_volume_sent' => (int) $this['total_volume_sent'],
            'total_volume_received' => (int) $this['total_volume_received'],
            'total_transfer_valuation' => (float) $this['total_transfer_valuation'],
            'completion_rate' => (float) $this['completion_rate'],
            'cancellation_rate' => (float) $this['cancellation_rate'],
            'avg_transit_lead_time_hours' => (float) $this['avg_transit_lead_time_hours'],
            'status_breakdown' => $this['status_breakdown'] ?? [],
        ];
    }
}
