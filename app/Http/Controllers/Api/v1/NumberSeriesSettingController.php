<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\CmsSetting;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NumberSeriesSettingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuditService $auditService
    ) {}

    private function getDefaultSeries(): array
    {
        return [
            'sales_invoice' => [
                'name' => 'Sales Invoice',
                'prefix' => 'INV-',
                'start_number' => 1,
                'current_number' => 1,
                'padding' => 6,
                'sample' => 'INV-000001',
            ],
            'pos_invoice' => [
                'name' => 'POS Invoice',
                'prefix' => 'POS-',
                'start_number' => 1,
                'current_number' => 1,
                'padding' => 6,
                'sample' => 'POS-000001',
            ],
            'sales_return' => [
                'name' => 'Sales Return',
                'prefix' => 'SR-',
                'start_number' => 1,
                'current_number' => 1,
                'padding' => 6,
                'sample' => 'SR-000001',
            ],
            'exchange' => [
                'name' => 'Item Exchange Note',
                'prefix' => 'EX-',
                'start_number' => 1,
                'current_number' => 1,
                'padding' => 6,
                'sample' => 'EX-000001',
            ],
            'purchase_order' => [
                'name' => 'Purchase Order',
                'prefix' => 'PO-',
                'start_number' => 1,
                'current_number' => 1,
                'padding' => 6,
                'sample' => 'PO-000001',
            ],
            'goods_receive' => [
                'name' => 'Goods Receive Note (GRN)',
                'prefix' => 'GRN-',
                'start_number' => 1,
                'current_number' => 1,
                'padding' => 6,
                'sample' => 'GRN-000001',
            ],
            'purchase_bill' => [
                'name' => 'Purchase Bill',
                'prefix' => 'BILL-',
                'start_number' => 1,
                'current_number' => 1,
                'padding' => 6,
                'sample' => 'BILL-000001',
            ],
            'purchase_return' => [
                'name' => 'Purchase Return',
                'prefix' => 'PR-',
                'start_number' => 1,
                'current_number' => 1,
                'padding' => 6,
                'sample' => 'PR-000001',
            ],
        ];
    }

    public function getSettings(): JsonResponse
    {
        $raw = CmsSetting::getSetting('number_series_settings');
        $saved = $raw ? json_decode($raw, true) : [];
        $defaults = $this->getDefaultSeries();

        $merged = [];
        foreach ($defaults as $key => $defaultVal) {
            if (isset($saved[$key]) && is_array($saved[$key])) {
                $merged[$key] = array_merge($defaultVal, $saved[$key]);
            } else {
                $merged[$key] = $defaultVal;
            }
            $num = str_pad((string) ($merged[$key]['current_number'] ?? 1), (int) ($merged[$key]['padding'] ?? 6), '0', STR_PAD_LEFT);
            $merged[$key]['sample'] = ($merged[$key]['prefix'] ?? '') . $num;
        }

        return $this->successResponse($merged, 'Number series configuration retrieved successfully.');
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'series' => 'required|array',
            'series.*.prefix' => 'required|string|max:20',
            'series.*.start_number' => 'required|integer|min:1',
            'series.*.current_number' => 'required|integer|min:1',
            'series.*.padding' => 'required|integer|min:3|max:10',
        ]);

        $raw = CmsSetting::getSetting('number_series_settings');
        $before = $raw ? json_decode($raw, true) : $this->getDefaultSeries();
        $defaults = $this->getDefaultSeries();

        $newSeries = [];
        foreach ($defaults as $key => $defaultVal) {
            if (isset($validated['series'][$key])) {
                $item = $validated['series'][$key];
                $newSeries[$key] = [
                    'name' => $defaultVal['name'],
                    'prefix' => strtoupper(trim($item['prefix'])),
                    'start_number' => (int) $item['start_number'],
                    'current_number' => (int) $item['current_number'],
                    'padding' => (int) $item['padding'],
                ];
            } else {
                $newSeries[$key] = $before[$key] ?? $defaultVal;
            }
        }

        CmsSetting::setSetting('number_series_settings', json_encode($newSeries));

        $this->auditService->logEvent([
            'module' => 'number_series',
            'event_type' => 'number_series_updated',
            'before_state' => $before,
            'after_state' => $newSeries,
            'reason_notes' => 'Updated document sequential number series configuration',
        ]);

        return $this->getSettings();
    }
}
