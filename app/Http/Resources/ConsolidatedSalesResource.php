<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsolidatedSalesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'summary' => [
                'total_stores' => (int) ($this['summary']['total_stores'] ?? 0),
                'gross_sales' => (float) ($this['summary']['gross_sales'] ?? 0.0),
                'discounts' => (float) ($this['summary']['discounts'] ?? 0.0),
                'taxable_sales' => (float) ($this['summary']['taxable_sales'] ?? 0.0),
                'gst_tax' => (float) ($this['summary']['gst_tax'] ?? 0.0),
                'gross_billed_revenue' => (float) ($this['summary']['gross_billed_revenue'] ?? 0.0),
                'returns_refunds' => (float) ($this['summary']['returns_refunds'] ?? 0.0),
                'net_billed_revenue' => (float) ($this['summary']['net_billed_revenue'] ?? 0.0),
                'net_taxable_revenue' => (float) ($this['summary']['net_taxable_revenue'] ?? 0.0),
                'cogs' => (float) ($this['summary']['cogs'] ?? 0.0),
                'gross_profit' => (float) ($this['summary']['gross_profit'] ?? 0.0),
                'gross_margin_percentage' => (float) ($this['summary']['gross_margin_percentage'] ?? 0.0),
                'operating_expenses' => (float) ($this['summary']['operating_expenses'] ?? 0.0),
                'net_store_profit' => (float) ($this['summary']['net_store_profit'] ?? 0.0),
                'total_collections' => (float) ($this['summary']['total_collections'] ?? 0.0),
                'total_outstanding' => (float) ($this['summary']['total_outstanding'] ?? 0.0),
            ],
            'stores' => $this['stores'] ?? [],
        ];
    }
}
