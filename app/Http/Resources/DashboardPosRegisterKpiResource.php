<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardPosRegisterKpiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'active_pos_sessions' => (int) $this['active_pos_sessions'],
            'open_registers' => (int) $this['open_registers'],
            'expected_drawer_cash' => (float) $this['expected_drawer_cash'],
            'total_cash_in' => (float) $this['total_cash_in'],
            'total_cash_out' => (float) $this['total_cash_out'],
            'total_drawer_drop' => (float) $this['total_drawer_drop'],
            'total_reconciliation_difference' => (float) $this['total_reconciliation_difference'],
        ];
    }
}
