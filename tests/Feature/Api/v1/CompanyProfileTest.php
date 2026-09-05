<?php

namespace Tests\Feature\Api\v1;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ImageUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $permSys = Permission::firstOrCreate(['name' => 'system.settings'], ['guard_name' => 'web', 'module_group' => 'Settings', 'display_name' => 'Manage Settings']);
        $role = Role::firstOrCreate(['name' => 'Super Admin'], ['guard_name' => 'web']);
        $role->permissions()->syncWithoutDetaching([$permSys->id]);

        $this->adminUser = User::create([
            'name' => 'Company Admin User',
            'username' => 'company_admin_' . rand(1000, 9999),
            'email' => 'company_admin_' . rand(1000, 9999) . '@rupsa.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($role->id, ['model_type' => User::class]);
    }

    public function test_image_url_service_formatting(): void
    {
        $this::assertNull(ImageUrlService::format(null));
        $this::assertNull(ImageUrlService::format(''));
        $this::assertEquals('https://example.com/logo.png', ImageUrlService::format('https://example.com/logo.png'));
        $this::assertEquals('/storage/company/logo.png', ImageUrlService::format('company/logo.png'));
        $this::assertEquals('/storage/company/logo.png', ImageUrlService::format('/storage/company/logo.png'));
        $this::assertEquals('/storage/company/logo.png', ImageUrlService::format('/storage/storage/company/logo.png'));
        $this::assertEquals('/storage/company/logo.png', ImageUrlService::format('storage/company/logo.png'));
    }

    public function test_get_company_profile_returns_default_settings(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/v1/settings/company');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.company_name', 'RUPSA PADUKALAYA')
            ->assertJsonPath('data.tagline', 'STEP INTO COMFORT');
    }

    public function test_update_company_profile_text_fields(): void
    {
        $payload = [
            'company_name' => 'RUPSA FOOTWEAR STORE',
            'tagline' => 'STEP WITH CONFIDENCE',
            'legal_name' => 'RUPSA FOOTWEAR RETAIL PVT LTD',
            'gstin' => '19AAECR9999F1Z0',
            'phone' => '+91 9830099999',
            'email' => 'contact@rupsafootwear.com',
            'website' => 'https://rupsafootwear.com',
            'address_line1' => 'MAIN ROAD',
            'address_line2' => 'DHANTALA',
            'city' => 'NADIA',
            'state' => 'WEST BENGAL',
            'pincode' => '741202',
            'country' => 'INDIA',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/settings/company', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.company_name', 'RUPSA FOOTWEAR STORE')
            ->assertJsonPath('data.tagline', 'STEP WITH CONFIDENCE')
            ->assertJsonPath('data.gstin', '19AAECR9999F1Z0');
    }

    public function test_upload_and_remove_primary_logo(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('primary_logo.png', 100, 'image/png');

        $uploadResponse = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/settings/company/primary-logo', [
                'logo' => $file,
            ]);

        $uploadResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $primaryPath = $uploadResponse->json('data.primary_logo_path');
        $primaryUrl = $uploadResponse->json('data.primary_logo_url');

        $this->assertNotEmpty($primaryPath);
        $this->assertStringContainsString('/storage/', $primaryUrl);
        $this->assertStringNotContainsString('/storage/storage/', $primaryUrl);
        Storage::disk('public')->assertExists($primaryPath);

        // Delete primary logo
        $deleteResponse = $this->actingAs($this->adminUser)
            ->deleteJson('/api/v1/settings/company/primary-logo');

        $deleteResponse->assertStatus(200)
            ->assertJsonPath('data.primary_logo_path', null)
            ->assertJsonPath('data.primary_logo_url', null);

        Storage::disk('public')->assertMissing($primaryPath);
    }

    public function test_upload_and_remove_white_logo(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('white_logo.png', 100, 'image/png');

        $uploadResponse = $this->actingAs($this->adminUser)
            ->postJson('/api/v1/settings/company/white-logo', [
                'logo' => $file,
            ]);

        $uploadResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $whitePath = $uploadResponse->json('data.white_logo_path');
        $whiteUrl = $uploadResponse->json('data.white_logo_url');

        $this->assertNotEmpty($whitePath);
        $this->assertStringContainsString('/storage/', $whiteUrl);
        $this->assertStringNotContainsString('/storage/storage/', $whiteUrl);
        Storage::disk('public')->assertExists($whitePath);

        // Delete white logo
        $deleteResponse = $this->actingAs($this->adminUser)
            ->deleteJson('/api/v1/settings/company/white-logo');

        $deleteResponse->assertStatus(200)
            ->assertJsonPath('data.white_logo_path', null)
            ->assertJsonPath('data.white_logo_url', null);

        Storage::disk('public')->assertMissing($whitePath);
    }
}
