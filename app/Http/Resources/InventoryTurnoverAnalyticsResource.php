<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryTurnoverAnalyticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'store_id' => $this['store_id'] ?? null,
            'total_inventory_units' => (int) $this['total_inventory_units'],
            'total_inventory_valuation' => (float) $this['total_inventory_valuation'],
            'inventory_turnover_ratio' => (float) $this['inventory_turnover_ratio'],
            'stockout_frequency' => (int) $this['stockout_frequency'],
            'low_stock_count' => (int) $this['low_stock_count'],
            'overstock_count' => (int) $this['overstock_count'],
        ];
    }
}
