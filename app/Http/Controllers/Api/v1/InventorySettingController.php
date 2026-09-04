<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Services\LowStockService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventorySettingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LowStockService $lowStockService
    ) {}

    public function getSettings(): JsonResponse
    {
        $settings = $this->lowStockService->getSettings();

        return $this->successResponse(
            $settings,
            'Inventory low stock settings retrieved successfully.'
        );
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enable_low_stock_alerts' => 'nullable|boolean',
            'default_low_stock_threshold' => 'nullable|integer|min:0',
            'default_out_of_stock_threshold' => 'nullable|integer|min:0',
            'enable_dashboard_notifications' => 'nullable|boolean',
            'enable_sound_notifications' => 'nullable|boolean',
        ]);

        $saved = $this->lowStockService->saveSettings($validated);

        return $this->successResponse(
            $saved,
            'Inventory low stock settings updated successfully.'
        );
    }
}
