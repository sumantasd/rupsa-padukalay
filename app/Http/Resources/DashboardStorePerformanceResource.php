<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardStorePerformanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'rank' => (int) $this['rank'],
            'store_id' => (int) $this['store_id'],
            'store_code' => $this['store_code'],
            'store_name' => $this['store_name'],
            'gross_sales' => (float) $this['gross_sales'],
            'net_sales' => (float) $this['net_sales'],
            'cogs' => (float) $this['cogs'],
            'gross_profit' => (float) $this['gross_profit'],
            'gross_margin_percentage' => (float) $this['gross_margin_percentage'],
            'completed_orders' => (int) $this['completed_orders'],
            'returns_count' => (int) $this['returns_count'],
            'total_collections' => (float) $this['total_collections'],
            'total_expenses' => (float) $this['total_expenses'],
            'net_store_profit' => (float) $this['net_store_profit'],
            'inventory_valuation' => (float) $this['inventory_valuation'],
        ];
    }
}
