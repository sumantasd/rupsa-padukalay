<?php

namespace Tests\Feature\Api\v1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_with_valid_credentials(): void
    {
        $user = User::create([
            'name' => 'Test Cashier',
            'username' => 'cashier1',
            'email' => 'cashier1@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'cashier1',
            'password' => 'secret123',
            'device_name' => 'POS Device 1',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login successful.',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'access_token',
                    'token_type',
                    'user' => ['id', 'name', 'username', 'email', 'is_active'],
                ],
            ]);
    }

    public function test_login_rejection_with_invalid_credentials(): void
    {
        User::create([
            'name' => 'Test Cashier',
            'username' => 'cashier1',
            'email' => 'cashier1@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'cashier1',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid username/email or password.',
            ]);
    }

    public function test_login_validation_for_missing_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['login', 'password']);
    }

    public function test_successful_authenticated_me_request(): void
    {
        $user = User::create([
            'name' => 'Test Cashier',
            'username' => 'cashier1',
            'email' => 'cashier1@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'username' => 'cashier1',
                ],
            ]);
    }

    public function test_unauthenticated_me_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_successful_logout_for_authenticated_user(): void
    {
        $user = User::create([
            'name' => 'Test Cashier',
            'username' => 'cashier1',
            'email' => 'cashier1@example.com',
            'password' => Hash::make('secret123'),
            'is_active' => true,
        ]);

        $token = $user->createToken('TestToken')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully.',
            ]);

        $this->assertCount(0, $user->tokens);
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertStatus(401);
    }
}
