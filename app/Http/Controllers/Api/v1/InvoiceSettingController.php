<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\CmsSetting;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceSettingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuditService $auditService
    ) {}

    private function getDefaultInvoiceSettings(): array
    {
        return [
            'invoice_prefix' => 'INV-',
            'invoice_title' => 'TAX INVOICE',
            'date_format' => 'DD/MM/YYYY',
            'show_customer_phone' => true,
            'show_customer_address' => true,
            'show_item_sku' => true,
            'show_item_size' => true,
            'show_item_color' => false,
            'show_tax_breakdown' => true,
            'show_discount_summary' => true,
            'footer_note' => 'Thank you for shopping with RUPSA PADUKALAYA! Visit again.',
            'terms_conditions' => "1. Goods once sold can be exchanged within 7 days with original bill.\n2. No cash refund.\n3. Cut or damaged items will not be accepted.",
            'signature_label' => 'Authorized Signatory',
            'enable_signature' => true,
        ];
    }

    public function getSettings(): JsonResponse
    {
        $raw = CmsSetting::getSetting('invoice_settings');
        $saved = $raw ? json_decode($raw, true) : [];
        $settings = array_merge($this->getDefaultInvoiceSettings(), is_array($saved) ? $saved : []);

        return $this->successResponse($settings, 'Invoice settings retrieved successfully.');
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_prefix' => 'required|string|max:20',
            'invoice_title' => 'required|string|max:100',
            'date_format' => 'required|string|max:30',
            'show_customer_phone' => 'nullable|boolean',
            'show_customer_address' => 'nullable|boolean',
            'show_item_sku' => 'nullable|boolean',
            'show_item_size' => 'nullable|boolean',
            'show_item_color' => 'nullable|boolean',
            'show_tax_breakdown' => 'nullable|boolean',
            'show_discount_summary' => 'nullable|boolean',
            'footer_note' => 'nullable|string|max:500',
            'terms_conditions' => 'nullable|string|max:1000',
            'signature_label' => 'nullable|string|max:100',
            'enable_signature' => 'nullable|boolean',
        ]);

        $raw = CmsSetting::getSetting('invoice_settings');
        $before = $raw ? json_decode($raw, true) : $this->getDefaultInvoiceSettings();

        CmsSetting::setSetting('invoice_settings', json_encode($validated));

        $this->auditService->logEvent([
            'module' => 'invoice_settings',
            'event_type' => 'invoice_settings_updated',
            'before_state' => $before,
            'after_state' => $validated,
            'reason_notes' => 'Updated invoice receipt template and formatting settings',
        ]);

        return $this->successResponse($validated, 'Invoice settings updated successfully.');
    }
}
