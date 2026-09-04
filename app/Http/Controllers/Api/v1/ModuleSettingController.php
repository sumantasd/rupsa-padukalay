<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\CmsSetting;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleSettingController extends Controller
{
    use ApiResponse;

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

        return $this->successResponse([
            'multi_store_enabled' => $multiStore,
            'loyalty_enabled' => $loyalty,
            'transfers_enabled' => $transfers,
            'advanced_reports_enabled' => $advancedReports,
            'expenses_enabled' => $expenses,
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

        return $this->index();
    }
}
