<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\CmsSetting;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleSettingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Get all active system module toggle states.
     */
    public function index(): JsonResponse
    {
        $multiStore = CmsSetting::getSetting('module_multi_store', '0') === '1';
        $loyalty = CmsSetting::getSetting('module_loyalty', '1') === '1';
        $transfers = CmsSetting::getSetting('module_transfers', '1') === '1';
        $advancedReports = CmsSetting::getSetting('module_advanced_reports', '1') === '1';
        $expenses = CmsSetting::getSetting('module_expenses', '1') === '1';
        $allowNewStoreCreation = CmsSetting::getSetting('allow_new_store_creation', '0') === '1';

        $productFieldCategory = CmsSetting::getSetting('product_field_category', '0') === '1';
        $productFieldGender = CmsSetting::getSetting('product_field_gender', '0') === '1';
        $productFieldUpperMaterial = CmsSetting::getSetting('product_field_upper_material', '0') === '1';
        $productFieldSoleMaterial = CmsSetting::getSetting('product_field_sole_material', '0') === '1';
        $productFieldColor = CmsSetting::getSetting('product_field_color', '0') === '1';

        return $this->successResponse([
            'multi_store_enabled' => $multiStore,
            'loyalty_enabled' => $loyalty,
            'transfers_enabled' => $transfers,
            'advanced_reports_enabled' => $advancedReports,
            'expenses_enabled' => $expenses,
            'allow_new_store_creation' => $allowNewStoreCreation,
            'product_field_category' => $productFieldCategory,
            'product_field_gender' => $productFieldGender,
            'product_field_upper_material' => $productFieldUpperMaterial,
            'product_field_sole_material' => $productFieldSoleMaterial,
            'product_field_color' => $productFieldColor,
        ], 'Module settings retrieved successfully.');
    }

    /**
     * Update system module toggle states.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'multi_store_enabled' => 'nullable|boolean',
            'loyalty_enabled' => 'nullable|boolean',
            'transfers_enabled' => 'nullable|boolean',
            'advanced_reports_enabled' => 'nullable|boolean',
            'expenses_enabled' => 'nullable|boolean',
            'allow_new_store_creation' => 'nullable|boolean',
            'product_field_category' => 'nullable|boolean',
            'product_field_gender' => 'nullable|boolean',
            'product_field_upper_material' => 'nullable|boolean',
            'product_field_sole_material' => 'nullable|boolean',
            'product_field_color' => 'nullable|boolean',
        ]);

        if (array_key_exists('multi_store_enabled', $validated)) {
            CmsSetting::setSetting('module_multi_store', $validated['multi_store_enabled'] ? '1' : '0');
        }
        if (array_key_exists('loyalty_enabled', $validated)) {
            CmsSetting::setSetting('module_loyalty', $validated['loyalty_enabled'] ? '1' : '0');
        }
        if (array_key_exists('transfers_enabled', $validated)) {
            CmsSetting::setSetting('module_transfers', $validated['transfers_enabled'] ? '1' : '0');
        }
        if (array_key_exists('advanced_reports_enabled', $validated)) {
            CmsSetting::setSetting('module_advanced_reports', $validated['advanced_reports_enabled'] ? '1' : '0');
        }
        if (array_key_exists('expenses_enabled', $validated)) {
            CmsSetting::setSetting('module_expenses', $validated['expenses_enabled'] ? '1' : '0');
        }
        if (array_key_exists('allow_new_store_creation', $validated)) {
            CmsSetting::setSetting('allow_new_store_creation', $validated['allow_new_store_creation'] ? '1' : '0');
        }
        if (array_key_exists('product_field_category', $validated)) {
            CmsSetting::setSetting('product_field_category', $validated['product_field_category'] ? '1' : '0');
        }
        if (array_key_exists('product_field_gender', $validated)) {
            CmsSetting::setSetting('product_field_gender', $validated['product_field_gender'] ? '1' : '0');
        }
        if (array_key_exists('product_field_upper_material', $validated)) {
            CmsSetting::setSetting('product_field_upper_material', $validated['product_field_upper_material'] ? '1' : '0');
        }
        if (array_key_exists('product_field_sole_material', $validated)) {
            CmsSetting::setSetting('product_field_sole_material', $validated['product_field_sole_material'] ? '1' : '0');
        }
        if (array_key_exists('product_field_color', $validated)) {
            CmsSetting::setSetting('product_field_color', $validated['product_field_color'] ? '1' : '0');
        }

        $this->auditService->logEvent([
            'module' => 'module_settings',
            'event_type' => 'module_settings_updated',
            'after_state' => $validated,
            'reason_notes' => 'Updated system module feature toggles',
        ]);

        return $this->index();
    }
}
