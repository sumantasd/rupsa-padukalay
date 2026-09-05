<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuditService $auditService
    ) {}

    public function index(): JsonResponse
    {
        $roles = Role::withCount(['users', 'permissions'])->with('permissions')->get();

        return $this->successResponse(
            RoleResource::collection($roles),
            'Roles fetched successfully.'
        );
    }

    public function permissions(): JsonResponse
    {
        $permissions = Permission::all(['id', 'name', 'guard_name', 'module_group', 'display_name']);
        
        $grouped = $permissions->groupBy(function ($perm) {
            return $perm->module_group ?: 'General';
        })->map(function ($items, $group) {
            return [
                'group' => $group,
                'permissions' => $items->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'display_name' => $p->display_name ?: $p->name,
                ])->values(),
            ];
        })->values();

        return $this->successResponse($grouped, 'Permissions fetched successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $role = Role::with(['permissions', 'users'])->find($id);

        if (! $role) {
            return $this->errorResponse('Role not found.', 404);
        }

        return $this->successResponse([
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'users_count' => $role->users()->count(),
            'permissions_count' => $role->permissions()->count(),
            'permission_ids' => $role->permissions->pluck('id')->toArray(),
            'permissions' => $role->permissions->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'module_group' => $p->module_group,
                'display_name' => $p->display_name,
            ]),
        ], 'Role details retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:roles,name',
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        DB::beginTransaction();
        try {
            $role = Role::create([
                'name' => trim($validated['name']),
                'guard_name' => 'web',
                'description' => $validated['description'] ?? null,
            ]);

            if (! empty($validated['permissions'])) {
                $role->permissions()->sync($validated['permissions']);
            }

            DB::commit();

            $this->auditService->logEvent([
                'module' => 'access_control',
                'event_type' => 'role_created',
                'auditable_type' => Role::class,
                'auditable_id' => $role->id,
                'after_state' => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'description' => $role->description,
                    'permissions_count' => count($validated['permissions'] ?? []),
                ],
                'reason_notes' => "Created new role: {$role->name}",
            ]);

            $role->load('permissions');

            return $this->successResponse(
                new RoleResource($role),
                'Role created successfully.',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to create role: ' . $e->getMessage(), 500);
        }
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $role = Role::find($id);
        if (! $role) {
            return $this->errorResponse('Role not found.', 404);
        }

        // Protected system role enforcement
        if (in_array(strtolower($role->name), ['super admin', 'system admin'])) {
            // Cannot rename Super Admin
            if ($request->has('name') && trim($request->input('name')) !== $role->name) {
                return $this->errorResponse('Protected system role name cannot be modified.', 403);
            }
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:roles,name,' . $role->id,
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        $beforeState = [
            'name' => $role->name,
            'description' => $role->description,
            'permissions' => $role->permissions()->pluck('permissions.id')->toArray(),
        ];

        DB::beginTransaction();
        try {
            $role->update([
                'name' => trim($validated['name']),
                'description' => $validated['description'] ?? null,
            ]);

            if (isset($validated['permissions'])) {
                // If Super Admin role, ensure all system permissions are maintained
                if (in_array(strtolower($role->name), ['super admin', 'system admin'])) {
                    $allPermIds = Permission::pluck('id')->toArray();
                    $role->permissions()->sync($allPermIds);
                } else {
                    $role->permissions()->sync($validated['permissions']);
                }
            }

            DB::commit();

            $afterState = [
                'name' => $role->name,
                'description' => $role->description,
                'permissions' => $role->permissions()->pluck('permissions.id')->toArray(),
            ];

            $this->auditService->logEvent([
                'module' => 'access_control',
                'event_type' => 'role_updated',
                'auditable_type' => Role::class,
                'auditable_id' => $role->id,
                'before_state' => $beforeState,
                'after_state' => $afterState,
                'reason_notes' => "Updated role: {$role->name}",
            ]);

            $role->load('permissions');

            return $this->successResponse(
                new RoleResource($role),
                'Role updated successfully.'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to update role: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $role = Role::withCount('users')->find($id);
        if (! $role) {
            return $this->errorResponse('Role not found.', 404);
        }

        // Protected system role guard
        if (in_array(strtolower($role->name), ['super admin', 'system admin'])) {
            return $this->errorResponse('Protected system role cannot be deleted.', 403);
        }

        if ($role->users_count > 0) {
            return $this->errorResponse("Cannot delete role '{$role->name}' because it is assigned to {$role->users_count} user(s).", 422);
        }

        $beforeState = [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
        ];

        DB::beginTransaction();
        try {
            $role->permissions()->detach();
            $role->delete();

            DB::commit();

            $this->auditService->logEvent([
                'module' => 'access_control',
                'event_type' => 'role_deleted',
                'auditable_type' => Role::class,
                'auditable_id' => $id,
                'before_state' => $beforeState,
                'reason_notes' => "Deleted role: {$beforeState['name']}",
            ]);

            return $this->successResponse(null, 'Role deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to delete role: ' . $e->getMessage(), 500);
        }
    }
}
