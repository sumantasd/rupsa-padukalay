<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PosRegisterDrawerSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'session_id' => $this['session_id'],
            'pos_register_id' => $this['pos_register_id'],
            'store_id' => $this['store_id'],
            'cashier_id' => $this['cashier_id'],
            'status' => $this['status'],
            'opened_at' => $this['opened_at'],
            'closed_at' => $this['closed_at'],
            'opening_cash' => (float) $this['opening_cash'],
            'cash_sales' => (float) $this['cash_sales'],
            'cash_in' => (float) $this['cash_in'],
            'cash_out' => (float) $this['cash_out'],
            'drawer_drops' => (float) $this['drawer_drops'],
            'cash_refunds' => (float) $this['cash_refunds'],
            'cash_expenses' => (float) $this['cash_expenses'],
            'expected_drawer_balance' => (float) $this['expected_drawer_balance'],
            'closing_cash_actual' => $this['closing_cash_actual'] !== null ? (float) $this['closing_cash_actual'] : null,
            'cash_difference' => $this['cash_difference'] !== null ? (float) $this['cash_difference'] : null,
            'variance_status' => $this['variance_status'] ?? null,
        ];
    }
}
