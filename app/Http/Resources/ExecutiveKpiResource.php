<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExecutiveKpiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'gross_sales' => (float) $this['gross_sales'],
            'discounts' => (float) $this['discounts'],
            'taxable_sales' => (float) $this['taxable_sales'],
            'gst_tax' => (float) $this['gst_tax'],
            'gross_billed_sales_revenue' => (float) $this['gross_billed_sales_revenue'],
            'returns_refunds' => (float) $this['returns_refunds'],
            'net_billed_sales_revenue' => (float) $this['net_billed_sales_revenue'],
            'net_taxable_revenue' => (float) $this['net_taxable_revenue'],
            'total_collections' => (float) $this['total_collections'],
            'outstanding_amount' => (float) $this['outstanding_amount'],
            'cogs' => (float) $this['cogs'],
            'gross_profit' => (float) $this['gross_profit'],
            'gross_margin_percentage' => (float) $this['gross_margin_percentage'],
            'total_expenses' => (float) $this['total_expenses'],
            'net_store_profit' => (float) $this['net_store_profit'],
            'completed_sales_count' => (int) $this['completed_sales_count'],
            'returned_transactions_count' => (int) $this['returned_transactions_count'],
            'active_customers_count' => (int) $this['active_customers_count'],
            'period_comparison' => $this['period_comparison'] ?? [],
        ];
    }
}
