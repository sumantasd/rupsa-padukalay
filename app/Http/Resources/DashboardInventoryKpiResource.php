<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardInventoryKpiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'total_inventory_units' => (int) $this['total_inventory_units'],
            'inventory_valuation_cost' => (float) $this['inventory_valuation_cost'],
            'inventory_valuation_selling' => (float) $this['inventory_valuation_selling'],
            'low_stock_count' => (int) $this['low_stock_count'],
            'out_of_stock_count' => (int) $this['out_of_stock_count'],
            'overstock_count' => (int) $this['overstock_count'],
            'inventory_turnover_ratio' => (float) $this['inventory_turnover_ratio'],
        ];
    }
}
