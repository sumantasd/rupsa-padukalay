<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreAccessController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Get Store Access Matrix (Users list with assigned stores and available active stores).
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::with(['stores' => function ($q) {
            $q->whereNull('stores.deleted_at');
        }, 'roles'])->whereNull('deleted_at')->get();

        $activeStores = Store::where('is_active', true)->whereNull('deleted_at')->get(['id', 'code', 'name', 'city']);

        $matrix = $users->map(function ($user) {
            $defaultStore = $user->stores->firstWhere('pivot.is_default', 1);

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'is_active' => (bool) $user->is_active,
                'is_protected' => (bool) $user->is_protected,
                'roles' => $user->roles->pluck('name')->toArray(),
                'assigned_stores' => $user->stores->map(function ($s) {
                    return [
                        'id' => $s->id,
                        'code' => $s->code,
                        'name' => $s->name,
                        'is_default' => (bool) $s->pivot->is_default,
                    ];
                })->values(),
                'assigned_store_ids' => $user->stores->pluck('id')->toArray(),
                'default_store_id' => $defaultStore ? $defaultStore->id : ($user->stores->first()?->id ?? null),
            ];
        });

        return $this->successResponse([
            'users' => $matrix,
            'stores' => $activeStores,
        ], 'Store access matrix retrieved successfully.');
    }

    /**
     * Assign stores to a user and set default store.
     */
    public function assignStoreAccess(Request $request, int $userId): JsonResponse
    {
        $user = User::find($userId);

        if (! $user || $user->trashed()) {
            return $this->errorResponse('User not found.', 404);
        }

        $validated = $request->validate([
            'store_ids' => 'required|array|min:1',
            'store_ids.*' => 'exists:stores,id',
            'default_store_id' => 'required|integer|exists:stores,id',
        ]);

        // Validation: Verify all assigned stores are active and not soft-deleted
        $invalidStores = Store::whereIn('id', $validated['store_ids'])
            ->where(function ($q) {
                $q->where('is_active', false)->orWhereNotNull('deleted_at');
            })->pluck('name')->toArray();

        if (! empty($invalidStores)) {
            return $this->errorResponse('Cannot assign inactive or deleted store(s): ' . implode(', ', $invalidStores), 422);
        }

        // Validation: Default store MUST be in the assigned stores array
        if (! in_array($validated['default_store_id'], $validated['store_ids'])) {
            return $this->errorResponse('Default store must be selected from the assigned stores.', 422);
        }

        $beforeState = [
            'assigned_store_ids' => $user->stores()->pluck('stores.id')->toArray(),
            'default_store_id' => $user->stores()->wherePivot('is_default', 1)->first()?->id,
        ];

        DB::beginTransaction();
        try {
            // Prepare pivot data
            $pivotData = [];
            foreach ($validated['store_ids'] as $storeId) {
                $pivotData[$storeId] = [
                    'is_default' => ($storeId == $validated['default_store_id']) ? 1 : 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $user->stores()->sync($pivotData);

            DB::commit();

            $afterState = [
                'assigned_store_ids' => $validated['store_ids'],
                'default_store_id' => $validated['default_store_id'],
            ];

            $this->auditService->logEvent([
                'module' => 'store_access',
                'event_type' => 'store_access_updated',
                'auditable_type' => User::class,
                'auditable_id' => $user->id,
                'before_state' => $beforeState,
                'after_state' => $afterState,
                'reason_notes' => "Updated store access for user {$user->username} (Default Store: {$validated['default_store_id']})",
            ]);

            return $this->successResponse([
                'user_id' => $user->id,
                'assigned_store_ids' => $validated['store_ids'],
                'default_store_id' => $validated['default_store_id'],
            ], 'Store access updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to update store access: ' . $e->getMessage(), 500);
        }
    }
}
