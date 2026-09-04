<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\UpdateTaxSettingRequest;
use App\Models\TaxSetting;
use App\Services\TaxService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class TaxSettingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected TaxService $taxService
    ) {}

    public function index(): JsonResponse
    {
        $gstEnabled = $this->taxService->isGstEnabled();

        return $this->successResponse([
            'gst_enabled' => $gstEnabled,
        ], 'Tax settings retrieved successfully.');
    }

    public function update(UpdateTaxSettingRequest $request): JsonResponse
    {
        $enabled = $request->boolean('gst_enabled') || (string) $request->input('gst_enabled') === '1' ? '1' : '0';

        TaxSetting::setSetting('gst_enabled', $enabled);

        $gstEnabled = $this->taxService->isGstEnabled();

        return $this->successResponse([
            'gst_enabled' => $gstEnabled,
        ], 'Global GST setting updated successfully.');
    }
}
