<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\CmsSetting;
use App\Services\AuditService;
use App\Services\ImageUrlService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyProfileController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Default Company Profile schema.
     */
    private function getDefaultProfile(): array
    {
        return [
            'company_name' => 'RUPSA PADUKALAYA',
            'tagline' => 'STEP INTO COMFORT',
            'legal_name' => 'RUPSA PADUKALAYA RETAIL PRIVATE LIMITED',
            'gstin' => '19AAECR1234F1Z5',
            'phone' => '+91 9735125112',
            'email' => 'support@rupsapadukalaya.com',
            'website' => 'https://rupsapadukalaya.com',
            'address_line1' => 'DHANTALA BAZAR',
            'address_line2' => 'DHANTALA, RANAGHAT - II',
            'city' => 'NADIA',
            'state' => 'WEST BENGAL',
            'pincode' => '741202',
            'country' => 'INDIA',
            'primary_logo_path' => null,
            'primary_logo_url' => null,
            'white_logo_path' => null,
            'white_logo_url' => null,
        ];
    }

    /**
     * Fetch current Company Profile & Logo configuration.
     */
    public function show(): JsonResponse
    {
        $raw = CmsSetting::getSetting('company_profile');
        $saved = $raw ? json_decode($raw, true) : [];
        $profile = array_replace_recursive($this->getDefaultProfile(), is_array($saved) ? $saved : []);

        // Format image URLs
        $profile['primary_logo_url'] = ImageUrlService::format($profile['primary_logo_path'] ?? $profile['primary_logo_url'] ?? null);
        $profile['white_logo_url'] = ImageUrlService::format($profile['white_logo_path'] ?? $profile['white_logo_url'] ?? null);

        return $this->successResponse($profile, 'Company Profile retrieved successfully.');
    }

    /**
     * Update Company Profile text details.
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'company_name' => ['required', 'string', 'max:150'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:150'],
            'gstin' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'website' => ['nullable', 'string', 'max:255'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
        ]);

        $raw = CmsSetting::getSetting('company_profile');
        $current = $raw ? json_decode($raw, true) : $this->getDefaultProfile();

        $merged = array_merge($current, $request->only([
            'company_name',
            'tagline',
            'legal_name',
            'gstin',
            'phone',
            'email',
            'website',
            'address_line1',
            'address_line2',
            'city',
            'state',
            'pincode',
            'country',
        ]));

        CmsSetting::setSetting('company_profile', json_encode($merged));

        $this->auditService->logEvent([
            'module' => 'company_profile',
            'event_type' => 'company_profile_updated',
            'before_state' => $current,
            'after_state' => $merged,
            'reason_notes' => 'Updated company profile details',
        ]);

        $merged['primary_logo_url'] = ImageUrlService::format($merged['primary_logo_path'] ?? $merged['primary_logo_url'] ?? null);
        $merged['white_logo_url'] = ImageUrlService::format($merged['white_logo_path'] ?? $merged['white_logo_url'] ?? null);

        return $this->successResponse($merged, 'Company Profile updated successfully.');
    }

    /**
     * Upload Primary Logo (For white/light backgrounds).
     */
    public function uploadPrimaryLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp,svg', 'max:5120'],
        ]);

        $raw = CmsSetting::getSetting('company_profile');
        $profile = $raw ? json_decode($raw, true) : $this->getDefaultProfile();

        if (! empty($profile['primary_logo_path'])) {
            Storage::disk('public')->delete($profile['primary_logo_path']);
        }

        $path = $request->file('logo')->store('company', 'public');
        $profile['primary_logo_path'] = $path;
        $profile['primary_logo_url'] = ImageUrlService::format($path);

        CmsSetting::setSetting('company_profile', json_encode($profile));

        return $this->successResponse($profile, 'Primary Logo uploaded successfully.');
    }

    /**
     * Remove Primary Logo.
     */
    public function deletePrimaryLogo(): JsonResponse
    {
        $raw = CmsSetting::getSetting('company_profile');
        $profile = $raw ? json_decode($raw, true) : $this->getDefaultProfile();

        if (! empty($profile['primary_logo_path'])) {
            Storage::disk('public')->delete($profile['primary_logo_path']);
        }

        $profile['primary_logo_path'] = null;
        $profile['primary_logo_url'] = null;

        CmsSetting::setSetting('company_profile', json_encode($profile));

        return $this->successResponse($profile, 'Primary Logo removed successfully.');
    }

    /**
     * Upload White Logo (For dark/black backgrounds).
     */
    public function uploadWhiteLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp,svg', 'max:5120'],
        ]);

        $raw = CmsSetting::getSetting('company_profile');
        $profile = $raw ? json_decode($raw, true) : $this->getDefaultProfile();

        if (! empty($profile['white_logo_path'])) {
            Storage::disk('public')->delete($profile['white_logo_path']);
        }

        $path = $request->file('logo')->store('company', 'public');
        $profile['white_logo_path'] = $path;
        $profile['white_logo_url'] = ImageUrlService::format($path);

        CmsSetting::setSetting('company_profile', json_encode($profile));

        return $this->successResponse($profile, 'White Logo uploaded successfully.');
    }

    /**
     * Remove White Logo.
     */
    public function deleteWhiteLogo(): JsonResponse
    {
        $raw = CmsSetting::getSetting('company_profile');
        $profile = $raw ? json_decode($raw, true) : $this->getDefaultProfile();

        if (! empty($profile['white_logo_path'])) {
            Storage::disk('public')->delete($profile['white_logo_path']);
        }

        $profile['white_logo_path'] = null;
        $profile['white_logo_url'] = null;

        CmsSetting::setSetting('company_profile', json_encode($profile));

        return $this->successResponse($profile, 'White Logo removed successfully.');
    }
}
