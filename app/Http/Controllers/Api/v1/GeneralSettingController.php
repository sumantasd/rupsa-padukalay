<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\CmsSetting;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeneralSettingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuditService $auditService
    ) {}

    private function getDefaultGeneralSettings(): array
    {
        return [
            'app_title' => 'RUPSA PADUKALAYA ERP',
            'timezone' => 'Asia/Kolkata',
            'currency_symbol' => '₹',
            'currency_code' => 'INR',
            'date_format' => 'DD/MM/YYYY',
            'time_format' => '12h',
            'decimal_precision' => 2,
            'default_language' => 'en',
            'session_timeout_minutes' => 60,
            'enable_email_notifications' => true,
            'enable_browser_notifications' => true,
        ];
    }

    public function getSettings(): JsonResponse
    {
        $raw = CmsSetting::getSetting('general_settings');
        $saved = $raw ? json_decode($raw, true) : [];
        $settings = array_merge($this->getDefaultGeneralSettings(), is_array($saved) ? $saved : []);

        return $this->successResponse($settings, 'General settings retrieved successfully.');
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'app_title' => 'required|string|max:100',
            'timezone' => 'required|string|max:100',
            'currency_symbol' => 'required|string|max:10',
            'currency_code' => 'required|string|max:10',
            'date_format' => 'required|string|max:30',
            'time_format' => 'required|string|in:12h,24h',
            'decimal_precision' => 'required|integer|min:0|max:4',
            'default_language' => 'required|string|max:10',
            'session_timeout_minutes' => 'required|integer|min:15|max:1440',
            'enable_email_notifications' => 'required|boolean',
            'enable_browser_notifications' => 'required|boolean',
        ]);

        $raw = CmsSetting::getSetting('general_settings');
        $before = $raw ? json_decode($raw, true) : $this->getDefaultGeneralSettings();

        CmsSetting::setSetting('general_settings', json_encode($validated));

        $this->auditService->logEvent([
            'module' => 'general_settings',
            'event_type' => 'general_settings_updated',
            'before_state' => $before,
            'after_state' => $validated,
            'reason_notes' => 'Updated application general preferences and system defaults',
        ]);

        return $this->successResponse($validated, 'General settings updated successfully.');
    }
}
