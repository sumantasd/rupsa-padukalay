<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\CmsSetting;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosSettingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuditService $auditService
    ) {}

    private function getDefaultPosSettings(): array
    {
        return [
            'allow_negative_stock' => false,
            'require_customer_details' => false,
            'allow_manual_item_discount' => true,
            'allow_bill_level_discount' => true,
            'max_discount_percentage' => 20,
            'require_session_opening_float' => true,
            'auto_print_receipt_on_settle' => true,
            'enable_sound_effects' => true,
            'barcode_auto_add_to_cart' => true,
            'enable_quick_cash_buttons' => true,
            'quick_cash_denominations' => [100, 200, 500, 1000, 2000],
            'holding_cart_limit' => 10,
            'cashier_can_void_item' => true,
        ];
    }

    /**
     * Read-only POS settings endpoint accessible to cashiers & POS terminal.
     */
    public function getSettings(): JsonResponse
    {
        $raw = CmsSetting::getSetting('pos_settings');
        $saved = $raw ? json_decode($raw, true) : [];
        $settings = array_merge($this->getDefaultPosSettings(), is_array($saved) ? $saved : []);

        return $this->successResponse($settings, 'POS settings retrieved successfully.');
    }

    /**
     * Admin write endpoint.
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'allow_negative_stock' => 'required|boolean',
            'require_customer_details' => 'required|boolean',
            'allow_manual_item_discount' => 'required|boolean',
            'allow_bill_level_discount' => 'required|boolean',
            'max_discount_percentage' => 'required|numeric|min:0|max:100',
            'require_session_opening_float' => 'required|boolean',
            'auto_print_receipt_on_settle' => 'required|boolean',
            'enable_sound_effects' => 'required|boolean',
            'barcode_auto_add_to_cart' => 'required|boolean',
            'enable_quick_cash_buttons' => 'required|boolean',
            'holding_cart_limit' => 'required|integer|min:1|max:50',
            'cashier_can_void_item' => 'required|boolean',
        ]);

        $raw = CmsSetting::getSetting('pos_settings');
        $before = $raw ? json_decode($raw, true) : $this->getDefaultPosSettings();

        // Maintain quick cash denominations if not provided
        $validated['quick_cash_denominations'] = $before['quick_cash_denominations'] ?? [100, 200, 500, 1000, 2000];

        CmsSetting::setSetting('pos_settings', json_encode($validated));

        $this->auditService->logEvent([
            'module' => 'pos_settings',
            'event_type' => 'pos_settings_updated',
            'before_state' => $before,
            'after_state' => $validated,
            'reason_notes' => 'Updated POS terminal cashier workflow and operational settings',
        ]);

        return $this->successResponse($validated, 'POS settings updated successfully.');
    }
}
