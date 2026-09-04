<?php

namespace Tests\Feature\Api\v1;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrinterLogoTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $unauthorizedUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $permView = Permission::firstOrCreate(['name' => 'products.view'], ['guard_name' => 'web', 'module_group' => 'Catalog', 'display_name' => 'View Products']);
        $permEdit = Permission::firstOrCreate(['name' => 'products.edit'], ['guard_name' => 'web', 'module_group' => 'Catalog', 'display_name' => 'Edit Products']);

        $adminRole = Role::firstOrCreate(['name' => 'Super Admin'], ['guard_name' => 'web']);
        $adminRole->permissions()->syncWithoutDetaching([$permView->id, $permEdit->id]);

        $this->adminUser = User::create([
            'name' => 'Logo Admin User',
            'username' => 'logo_admin_' . rand(1000, 9999),
            'email' => 'logo_admin_' . rand(1000, 9999) . '@rupsa.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->adminUser->roles()->attach($adminRole->id, ['model_type' => User::class]);

        $this->unauthorizedUser = User::create([
            'name' => 'Regular User',
            'username' => 'regular_user_' . rand(1000, 9999),
            'email' => 'regular_' . rand(1000, 9999) . '@rupsa.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
    }

    public function test_default_printer_settings_include_logo_fields()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/v1/settings/printer');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.printer_logo_path', null)
            ->assertJsonPath('data.printer_logo_url', null)
            ->assertJsonPath('data.show_logo', true);
    }

    public function test_successful_logo_upload()
    {
        $file = UploadedFile::fake()->create('store_logo.png', 100, 'image/png');

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/settings/printer/logo', [
                'logo' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.show_logo', true);

        $data = $response->json('data');
        $this->assertNotEmpty($data['printer_logo_path']);
        $this->assertNotEmpty($data['printer_logo_url']);

        Storage::disk('public')->assertExists($data['printer_logo_path']);
    }

    public function test_invalid_file_format_rejection()
    {
        $file = UploadedFile::fake()->create('malicious.php', 100, 'text/x-php');

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/settings/printer/logo', [
                'logo' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['logo']);
    }

    public function test_oversized_logo_rejection()
    {
        $file = UploadedFile::fake()->create('huge_logo.png', 3000, 'image/png'); // 3MB

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/settings/printer/logo', [
                'logo' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['logo']);
    }

    public function test_logo_replacement_deletes_old_file()
    {
        $file1 = UploadedFile::fake()->create('first_logo.png', 100, 'image/png');
        $res1 = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/settings/printer/logo', ['logo' => $file1]);

        $oldPath = $res1->json('data.printer_logo_path');
        Storage::disk('public')->assertExists($oldPath);

        $file2 = UploadedFile::fake()->create('second_logo.png', 120, 'image/png');
        $res2 = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/settings/printer/logo', ['logo' => $file2]);

        $newPath = $res2->json('data.printer_logo_path');
        $this->assertNotEquals($oldPath, $newPath);

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    public function test_logo_removal()
    {
        $file = UploadedFile::fake()->create('logo_to_remove.png', 100, 'image/png');
        $res1 = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/settings/printer/logo', ['logo' => $file]);

        $path = $res1->json('data.printer_logo_path');
        Storage::disk('public')->assertExists($path);

        $res2 = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson('/api/v1/settings/printer/logo');

        $res2->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.printer_logo_path', null)
            ->assertJsonPath('data.printer_logo_url', null)
            ->assertJsonPath('data.show_logo', false);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_unauthorized_user_cannot_upload_or_delete_logo()
    {
        $file = UploadedFile::fake()->create('logo.png', 50, 'image/png');

        $this->actingAs($this->unauthorizedUser, 'sanctum')
            ->postJson('/api/v1/settings/printer/logo', ['logo' => $file])
            ->assertStatus(403);

        $this->actingAs($this->unauthorizedUser, 'sanctum')
            ->deleteJson('/api/v1/settings/printer/logo')
            ->assertStatus(403);
    }

    public function test_printer_settings_remain_intact_after_logo_upload()
    {
        // 1. Update custom settings first
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/settings/printer', [
                'printer_width' => '58mm',
                'thank_you_message' => 'Custom Thank You Rupsa',
            ])->assertStatus(200);

        // 2. Upload logo
        $file = UploadedFile::fake()->create('store_logo.png', 50, 'image/png');
        $res = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/v1/settings/printer/logo', ['logo' => $file]);

        $res->assertStatus(200)
            ->assertJsonPath('data.printer_width', '58mm')
            ->assertJsonPath('data.thank_you_message', 'Custom Thank You Rupsa')
            ->assertJsonPath('data.show_logo', true);
    }
}
