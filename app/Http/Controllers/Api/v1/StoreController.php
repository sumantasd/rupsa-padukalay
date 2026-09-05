<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\AssignStoreUsersRequest;
use App\Http\Requests\Master\StoreStoreRequest;
use App\Http\Requests\Master\UpdateStoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\CmsSetting;
use App\Models\Store;
use App\Services\StorePerformanceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    use ApiResponse;

    public function performanceOverview(Request $request, StorePerformanceService $service): JsonResponse
    {
        $user = $request->user();
        $storeId = (int) ($request->input('store_id') ?? 1);

        // Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $assignedStoreIds = $user->stores()->pluck('stores.id')->toArray();
            if (count($assignedStoreIds) === 0) {
                return $this->errorResponse('Forbidden: You have no store assignments.', 403);
            }
            if (! in_array($storeId, $assignedStoreIds)) {
                $storeId = $assignedStoreIds[0];
            }
        }

        $store = Store::find($storeId) ?? Store::first();

        if (! $store) {
            return $this->errorResponse('No active store found.', 404);
        }

        $data = $service->getPerformance($store, $request->all(), $user);

        return $this->successResponse($data, 'Store performance overview retrieved successfully.');
    }

    public function performance(Request $request, int $id, StorePerformanceService $service): JsonResponse
    {
        $user = $request->user();
        $store = Store::find($id);

        if (! $store) {
            return $this->errorResponse('Store not found.', 404);
        }

        // Store Access Check for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $hasAccess = $user->stores()->where('stores.id', $store->id)->exists();
            if (! $hasAccess) {
                return $this->errorResponse("Forbidden: You are not authorized to access Store ID {$store->id}.", 403);
            }
        }

        $data = $service->getPerformance($store, $request->all(), $user);

        return $this->successResponse($data, 'Store performance details retrieved successfully.');
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Store::with('users');

        // Non-Super Admin users only see stores assigned to them
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $assignedStoreIds = $user->stores()->pluck('stores.id');
            $query->whereIn('id', $assignedStoreIds);
        }

        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%")
                  ->orWhere('city', 'LIKE', "%{$search}%");
            });
        }

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        } elseif ($request->boolean('include_inactive', false)) {
            // Include inactive stores if explicitly requested
        } else {
            // Default: only active stores are available for user access & assignment
            $query->where('is_active', true);
        }

        $stores = $query->orderBy('name')->get();

        return $this->successResponse(
            StoreResource::collection($stores),
            'Stores retrieved successfully.'
        );
    }

    public function store(StoreStoreRequest $request): JsonResponse
    {
        if (CmsSetting::getSetting('allow_new_store_creation', '0') !== '1') {
            return $this->errorResponse('New store creation is currently disabled in system module settings.', 403);
        }
        $store = Store::create([
            'code' => strtoupper(trim($request->input('code'))),
            'name' => trim($request->input('name')),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'address' => $request->input('address'),
            'city' => $request->input('city'),
            'pincode' => $request->input('pincode'),
            'default_warehouse_id' => $request->input('default_warehouse_id'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->successResponse(
            new StoreResource($store),
            'Store created successfully.',
            201
        );
    }

    public function show(int $id): JsonResponse
    {
        $store = Store::with('users')->find($id);

        if (! $store) {
            return $this->errorResponse('Store not found.', 404);
        }

        return $this->successResponse(
            new StoreResource($store),
            'Store details retrieved successfully.'
        );
    }

    public function update(UpdateStoreRequest $request, int $id): JsonResponse
    {
        $store = Store::find($id);

        if (! $store) {
            return $this->errorResponse('Store not found.', 404);
        }

        $store->code = strtoupper(trim($request->input('code')));
        $store->name = trim($request->input('name'));

        if ($request->has('phone')) {
            $store->phone = $request->input('phone');
        }
        if ($request->has('email')) {
            $store->email = $request->input('email');
        }
        if ($request->has('address')) {
            $store->address = $request->input('address');
        }
        if ($request->has('city')) {
            $store->city = $request->input('city');
        }
        if ($request->has('pincode')) {
            $store->pincode = $request->input('pincode');
        }
        if ($request->has('default_warehouse_id')) {
            $store->default_warehouse_id = $request->input('default_warehouse_id');
        }
        if ($request->has('is_active')) {
            $store->is_active = $request->boolean('is_active');
        }

        $store->save();

        return $this->successResponse(
            new StoreResource($store->load('users')),
            'Store updated successfully.'
        );
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $store = Store::find($id);

        if (! $store) {
            return $this->errorResponse('Store not found.', 404);
        }

        $store->is_active = ! $store->is_active;
        $store->save();

        $statusText = $store->is_active ? 'activated' : 'deactivated';

        return $this->successResponse(
            new StoreResource($store),
            "Store {$statusText} successfully."
        );
    }

    public function assignUsers(AssignStoreUsersRequest $request, int $id): JsonResponse
    {
        $store = Store::find($id);

        if (! $store) {
            return $this->errorResponse('Store not found.', 404);
        }

        $userIds = $request->input('user_ids', []);
        $store->users()->sync($userIds);

        return $this->successResponse(
            new StoreResource($store->load('users')),
            'Store users assigned successfully.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $store = Store::find($id);

        if (! $store) {
            return $this->errorResponse('Store not found.', 404);
        }

        // 1. Protect Main/Default Store (STR-001 / ID 1)
        if ($store->code === 'STR-001' || $store->code === 'ST-001' || $store->id === 1) {
            return $this->errorResponse(
                'Main/default store (STR-001) cannot be deleted as it is the active primary outlet.',
                422
            );
        }

        // 2. Protect Active Primary Store if it is the only active store
        if (Store::where('is_active', true)->count() <= 1 && $store->is_active) {
            return $this->errorResponse(
                'Cannot delete the only active store in the system.',
                422
            );
        }

        // 3. Check Dependent Operational Records
        $hasInventory = \App\Models\InventoryStock::where('store_id', $store->id)->exists();
        $hasSessions = \App\Models\PosSession::where('store_id', $store->id)->exists();
        $hasInvoices = \App\Models\Invoice::where('store_id', $store->id)->exists();
        $hasPurchaseOrders = \App\Models\PurchaseOrder::where('store_id', $store->id)->exists();
        $hasStockMovements = \App\Models\StockMovement::where('store_id', $store->id)->exists();

        if ($hasInventory || $hasSessions || $hasInvoices || $hasPurchaseOrders || $hasStockMovements) {
            return $this->errorResponse(
                'This store cannot be deleted because it has existing sales, inventory, or transaction records. You may deactivate it instead.',
                422
            );
        }

        // 4. Detach Users & Safe Delete
        $store->users()->detach();
        $store->delete();

        return $this->successResponse(null, 'Store deleted successfully.');
    }
}
