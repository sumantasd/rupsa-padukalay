<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use ApiResponse;

    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function index(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $query = User::query()->with(['roles.permissions', 'stores']);

        // System Admin Protection: Exclude protected accounts if requesting admin is not a protected system admin
        if (! $currentUser->is_protected) {
            $query->notProtected();
        }

        // Search Filter (name, username, email, phone)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Status Filter
        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        // Role Filter
        if ($roleId = $request->input('role_id')) {
            $query->whereHas('roles', function ($q) use ($roleId) {
                $q->where('roles.id', $roleId);
            });
        } elseif ($roleName = $request->input('role')) {
            $query->whereHas('roles', function ($q) use ($roleName) {
                $q->where('roles.name', $roleName);
            });
        }

        // Store Filter
        if ($storeId = $request->input('store_id')) {
            $query->whereHas('stores', function ($q) use ($storeId) {
                $q->where('stores.id', $storeId);
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $users = $query->orderBy('name', 'asc')->paginate($perPage);

        return $this->successResponse([
            'items' => UserResource::collection($users->items()),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ], 'Users fetched successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role_id' => ['required', 'exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
            'store_ids' => ['nullable', 'array'],
            'store_ids.*' => ['exists:stores,id'],
        ]);

        // Security check: non-protected admins cannot grant Super Admin role
        $targetRole = Role::findOrFail($validated['role_id']);
        if ($targetRole->name === 'Super Admin' && ! $currentUser->is_protected) {
            return $this->errorResponse('Forbidden: Only System Administrators can grant Super Admin privileges.', 403);
        }

        $user = new User();
        $user->name = $validated['name'];
        $user->username = $validated['username'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? null;
        $user->password = Hash::make($validated['password']);
        $user->is_active = $validated['is_active'] ?? true;
        $user->is_protected = false;
        $user->save();

        // Attach Role using explicit pivot data
        $user->roles()->syncWithPivotValues([$targetRole->id], ['model_type' => User::class]);

        // Attach Stores
        if (! empty($validated['store_ids'])) {
            $storeSync = [];
            foreach ($validated['store_ids'] as $idx => $stId) {
                $storeSync[$stId] = ['is_default' => ($idx === 0)];
            }
            $user->stores()->sync($storeSync);
        }

        $user->load(['roles.permissions', 'stores']);

        // Audit Log
        $this->auditService->logEvent([
            'event_type' => 'user_created',
            'severity' => 'info',
            'user_id' => $currentUser->id,
            'description' => "Created new user '{$user->name}' (@{$user->username}) with role '{$targetRole->name}'.",
            'after_state' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $targetRole->name,
            ],
        ]);

        return $this->successResponse(new UserResource($user), 'User account created successfully.', 201);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $currentUser = $request->user();
        $user = User::with(['roles.permissions', 'stores'])->findOrFail($id);

        if ($user->is_protected && ! $currentUser->is_protected) {
            $this->auditService->logEvent([
                'event_type' => 'protected_account_access_attempt',
                'severity' => 'warning',
                'user_id' => $currentUser->id,
                'description' => "Unauthorized access attempt on protected System Admin account (User ID: {$user->id}).",
            ]);

            return $this->errorResponse('Forbidden: Cannot access protected System Admin account.', 403);
        }

        return $this->successResponse(new UserResource($user), 'User details fetched successfully.');
    }

    public function update(Request $request, $id): JsonResponse
    {
        $currentUser = $request->user();
        $user = User::with(['roles.permissions', 'stores'])->findOrFail($id);

        // System Admin Protection Boundary
        if ($user->is_protected && ! $currentUser->is_protected) {
            $this->auditService->logEvent([
                'event_type' => 'protected_account_edit_attempt',
                'severity' => 'warning',
                'user_id' => $currentUser->id,
                'description' => "Unauthorized edit attempt on protected System Admin account (User ID: {$user->id}).",
            ]);

            return $this->errorResponse('Forbidden: Cannot modify protected System Admin account.', 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role_id' => ['nullable', 'exists:roles,id'],
            'is_active' => ['nullable', 'boolean'],
            'store_ids' => ['nullable', 'array'],
            'store_ids.*' => ['exists:stores,id'],
        ]);

        $beforeState = [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'is_active' => $user->is_active,
        ];

        $user->name = $validated['name'];
        $user->username = $validated['username'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? null;

        if (array_key_exists('is_active', $validated) && ! $user->is_protected) {
            $user->is_active = (bool) $validated['is_active'];
        }

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        // Role update
        if (! empty($validated['role_id'])) {
            $targetRole = Role::findOrFail($validated['role_id']);
            if ($targetRole->name === 'Super Admin' && ! $currentUser->is_protected) {
                return $this->errorResponse('Forbidden: Only System Administrators can grant Super Admin privileges.', 403);
            }
            $user->roles()->syncWithPivotValues([$targetRole->id], ['model_type' => User::class]);
        }

        // Store access update
        if (isset($validated['store_ids'])) {
            $storeSync = [];
            foreach ($validated['store_ids'] as $idx => $stId) {
                $storeSync[$stId] = ['is_default' => ($idx === 0)];
            }
            $user->stores()->sync($storeSync);
        }

        $user->load(['roles.permissions', 'stores']);

        $afterState = [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'is_active' => $user->is_active,
        ];

        $this->auditService->logEvent([
            'event_type' => 'user_updated',
            'severity' => 'info',
            'user_id' => $currentUser->id,
            'description' => "Updated user profile for '{$user->name}' (@{$user->username}).",
            'before_state' => $beforeState,
            'after_state' => $afterState,
        ]);

        return $this->successResponse(new UserResource($user), 'User updated successfully.');
    }

    public function destroy(Request $request, $id): JsonResponse
    {
        $currentUser = $request->user();
        $user = User::findOrFail($id);

        if ($user->is_protected || $user->id === $currentUser->id) {
            $this->auditService->logEvent([
                'event_type' => 'protected_account_delete_attempt',
                'severity' => 'warning',
                'user_id' => $currentUser->id,
                'description' => "Unauthorized delete attempt on protected or active account (User ID: {$user->id}).",
            ]);

            return $this->errorResponse('Forbidden: Cannot delete protected System Admin or active personal account.', 403);
        }

        $user->delete();

        $this->auditService->logEvent([
            'event_type' => 'user_deleted',
            'severity' => 'warning',
            'user_id' => $currentUser->id,
            'description' => "Deleted user account '{$user->name}' (@{$user->username}).",
        ]);

        return $this->successResponse(null, 'User account deleted successfully.');
    }

    public function toggleStatus(Request $request, $id): JsonResponse
    {
        $currentUser = $request->user();
        $user = User::with(['roles.permissions', 'stores'])->findOrFail($id);

        if ($user->is_protected || $user->id === $currentUser->id) {
            return $this->errorResponse('Forbidden: Cannot deactivate protected System Admin or your own active account.', 403);
        }

        $user->is_active = ! $user->is_active;
        $user->save();

        $statusText = $user->is_active ? 'activated' : 'deactivated';

        $this->auditService->logEvent([
            'event_type' => 'user_status_changed',
            'severity' => 'info',
            'user_id' => $currentUser->id,
            'description' => "User '{$user->name}' (@{$user->username}) was {$statusText}.",
        ]);

        return $this->successResponse(new UserResource($user), "User account {$statusText} successfully.");
    }

    public function resetPassword(Request $request, $id): JsonResponse
    {
        $currentUser = $request->user();
        $user = User::findOrFail($id);

        if ($user->is_protected && ! $currentUser->is_protected) {
            return $this->errorResponse('Forbidden: Cannot reset password for protected System Admin account.', 403);
        }

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->password = Hash::make($validated['password']);
        $user->save();

        $this->auditService->logEvent([
            'event_type' => 'user_password_reset',
            'severity' => 'warning',
            'user_id' => $currentUser->id,
            'description' => "Administrator reset password for user '{$user->name}' (@{$user->username}).",
        ]);

        return $this->successResponse(null, 'User password reset successfully.');
    }
}
