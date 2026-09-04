<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfitLossResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'revenue' => [
                'gross_sales' => (float) ($this['revenue']['gross_sales'] ?? 0.0),
                'discounts' => (float) ($this['revenue']['discounts'] ?? 0.0),
                'taxable_sales' => (float) ($this['revenue']['taxable_sales'] ?? 0.0),
                'gst_collected' => (float) ($this['revenue']['gst_collected'] ?? 0.0),
                'gross_billed_revenue' => (float) ($this['revenue']['gross_billed_revenue'] ?? 0.0),
                'returns_refunds' => (float) ($this['revenue']['returns_refunds'] ?? 0.0),
                'returns' => (float) ($this['revenue']['returns'] ?? $this['revenue']['returns_refunds'] ?? 0.0),
                'net_billed_revenue' => (float) ($this['revenue']['net_billed_revenue'] ?? 0.0),
                'net_sales' => (float) ($this['revenue']['net_sales'] ?? $this['revenue']['net_billed_revenue'] ?? 0.0),
                'net_taxable_revenue' => (float) ($this['revenue']['net_taxable_revenue'] ?? 0.0),
            ],
            'cost_of_goods_sold' => [
                'invoice_cogs' => (float) ($this['cost_of_goods_sold']['invoice_cogs'] ?? 0.0),
                'returned_cogs' => (float) ($this['cost_of_goods_sold']['returned_cogs'] ?? 0.0),
                'net_cogs' => (float) ($this['cost_of_goods_sold']['net_cogs'] ?? 0.0),
            ],
            'profitability' => [
                'gross_profit' => (float) ($this['profitability']['gross_profit'] ?? 0.0),
                'gross_margin_percentage' => (float) ($this['profitability']['gross_margin_percentage'] ?? 0.0),
                'operating_expenses' => (float) ($this['profitability']['operating_expenses'] ?? 0.0),
                'net_profit' => (float) ($this['profitability']['net_profit'] ?? 0.0),
            ],
            'expense_categories_breakdown' => $this['expense_categories_breakdown'] ?? [],
            'period_comparison' => $this['period_comparison'] ?? [],
        ];
    }
}
