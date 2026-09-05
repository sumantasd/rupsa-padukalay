<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request): JsonResponse
    {
        $loginInput = trim($request->input('login'));
        $password = $request->input('password');

        $user = User::where('username', $loginInput)
            ->orWhere('email', $loginInput)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return $this->errorResponse('Invalid username/email or password.', 401);
        }

        if (! $user->is_active) {
            return $this->errorResponse('Account is deactivated. Please contact your administrator.', 403);
        }

        $deviceName = $request->input('device_name', 'API Device');
        $token = $user->createToken($deviceName)->plainTextToken;

        $user->load(['roles.permissions', 'stores']);

        return $this->successResponse([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 'Login successful.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles.permissions', 'stores']);

        return $this->successResponse(
            new UserResource($user),
            'Authenticated user profile fetched successfully.'
        );
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user->update($validated);
        $user->load(['roles.permissions', 'stores']);

        return $this->successResponse(
            new UserResource($user),
            'Profile updated successfully.'
        );
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($request->input('current_password'), $user->password)) {
            return $this->errorResponse('Current password is incorrect.', 422);
        }

        $user->password = Hash::make($request->input('new_password'));
        $user->save();

        return $this->successResponse(null, 'Password changed successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logged out successfully.');
    }
}
