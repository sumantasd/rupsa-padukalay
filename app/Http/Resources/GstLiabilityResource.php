<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GstLiabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'tax_liability_summary' => [
                'total_invoices_count' => (int) ($this['tax_liability_summary']['total_invoices_count'] ?? 0),
                'gross_taxable_value' => (float) ($this['tax_liability_summary']['gross_taxable_value'] ?? 0.0),
                'gross_cgst_amount' => (float) ($this['tax_liability_summary']['gross_cgst_amount'] ?? 0.0),
                'gross_sgst_amount' => (float) ($this['tax_liability_summary']['gross_sgst_amount'] ?? 0.0),
                'gross_igst_amount' => (float) ($this['tax_liability_summary']['gross_igst_amount'] ?? 0.0),
                'gross_total_gst' => (float) ($this['tax_liability_summary']['gross_total_gst'] ?? 0.0),
                'sales_returns_tax_credit' => (float) ($this['tax_liability_summary']['sales_returns_tax_credit'] ?? 0.0),
                'net_taxable_value' => (float) ($this['tax_liability_summary']['net_taxable_value'] ?? 0.0),
                'net_gst_payable' => (float) ($this['tax_liability_summary']['net_gst_payable'] ?? 0.0),
            ],
            'store_tax_breakdown' => $this['store_tax_breakdown'] ?? [],
            'hsn_tax_summary' => $this['hsn_tax_summary'] ?? [],
            'gstr_compliance_export_readiness' => $this['gstr_compliance_export_readiness'] ?? [],
        ];
    }
}
