<?php

namespace App\Services;

use App\Models\HsnCode;
use App\Models\TaxSetting;

class TaxService
{
    /**
     * Check if GST is currently enabled globally in system settings.
     */
    public function isGstEnabled(): bool
    {
        return (string) TaxSetting::getSetting('gst_enabled', '0') === '1';
    }

    /**
     * Calculate tax breakdown for a unit selling price and quantity.
     * When GST is OFF, returns 0 tax.
     * When GST is ON, uses configurable HSN rate or provided rate.
     */
    public function calculateItemTax(
        float $unitPrice,
        int $quantity,
        float $discountAmount = 0.0,
        ?HsnCode $hsnCode = null,
        ?float $overrideTaxRate = null
    ): array {
        $subtotal = max(0.0, ($unitPrice * $quantity) - $discountAmount);

        if (! $this->isGstEnabled()) {
            return [
                'is_gst_enabled' => false,
                'tax_rate_percentage' => 0.0,
                'taxable_value' => round($subtotal, 2),
                'cgst_amount' => 0.0,
                'sgst_amount' => 0.0,
                'igst_amount' => 0.0,
                'total_tax_amount' => 0.0,
                'line_total' => round($subtotal, 2),
            ];
        }

        $taxRate = $overrideTaxRate ?? $hsnCode?->default_gst_rate ?? 0.0;
        
        // Tax-inclusive pricing calculation formula: Taxable = Amount / (1 + Rate/100)
        $taxableValue = round($subtotal / (1 + ($taxRate / 100)), 2);
        $totalTax = round($subtotal - $taxableValue, 2);
        
        // Equal split for intra-state CGST & SGST
        $halfTax = round($totalTax / 2, 2);

        return [
            'is_gst_enabled' => true,
            'tax_rate_percentage' => round($taxRate, 2),
            'taxable_value' => $taxableValue,
            'cgst_amount' => $halfTax,
            'sgst_amount' => round($totalTax - $halfTax, 2), // Adjust rounding remainder
            'igst_amount' => 0.0,
            'total_tax_amount' => $totalTax,
            'line_total' => round($subtotal, 2),
        ];
    }
}
