<?php

use App\Models\CmsSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $existing = CmsSetting::getSetting('company_profile');

        if (! $existing) {
            $defaultProfile = [
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

            CmsSetting::setSetting('company_profile', json_encode($defaultProfile));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep settings data preserved on rollback
    }
};
