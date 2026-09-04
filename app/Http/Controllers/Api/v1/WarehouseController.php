<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\AssignStoreWarehousesRequest;
use App\Http\Requests\Master\StoreWarehouseRequest;
use App\Http\Requests\Master\UpdateWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Store;
use App\Models\Warehouse;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Warehouse::with(['manager', 'stores']);

        // Non-Super Admin users only see warehouses linked to their authorized stores
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $query->where(function ($q) use ($user, $userStoreIds) {
                $q->whereHas('stores', function ($sq) use ($userStoreIds) {
                    $sq->whereIn('stores.id', $userStoreIds);
                })->orWhere('manager_user_id', $user->id);
            });
        }

        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%")
                  ->orWhere('city', 'LIKE', "%{$search}%");
            });
        }

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        $warehouses = $query->orderBy('name')->get();

        return $this->successResponse(
            WarehouseResource::collection($warehouses),
            'Warehouses retrieved successfully.'
        );
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $warehouse = Warehouse::create([
            'code' => strtoupper(trim($request->input('code'))),
            'name' => trim($request->input('name')),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'address' => $request->input('address'),
            'city' => $request->input('city'),
            'pincode' => $request->input('pincode'),
            'manager_user_id' => $request->input('manager_user_id'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->successResponse(
            new WarehouseResource($warehouse),
            'Warehouse created successfully.',
            201
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $warehouse = Warehouse::with(['manager', 'stores'])->find($id);

        if (! $warehouse) {
            return $this->errorResponse('Warehouse not found.', 404);
        }

        $user = $request->user();
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $hasAccess = $warehouse->stores()->whereIn('stores.id', $userStoreIds)->exists()
                || $warehouse->manager_user_id === $user->id;

            if (! $hasAccess) {
                return $this->errorResponse(
                    "Forbidden: You are not authorized to access Warehouse ID {$id}.",
                    403
                );
            }
        }

        return $this->successResponse(
            new WarehouseResource($warehouse),
            'Warehouse details retrieved successfully.'
        );
    }

    public function update(UpdateWarehouseRequest $request, int $id): JsonResponse
    {
        $warehouse = Warehouse::find($id);

        if (! $warehouse) {
            return $this->errorResponse('Warehouse not found.', 404);
        }

        $warehouse->code = strtoupper(trim($request->input('code')));
        $warehouse->name = trim($request->input('name'));

        if ($request->has('phone')) {
            $warehouse->phone = $request->input('phone');
        }
        if ($request->has('email')) {
            $warehouse->email = $request->input('email');
        }
        if ($request->has('address')) {
            $warehouse->address = $request->input('address');
        }
        if ($request->has('city')) {
            $warehouse->city = $request->input('city');
        }
        if ($request->has('pincode')) {
            $warehouse->pincode = $request->input('pincode');
        }
        if ($request->has('manager_user_id')) {
            $warehouse->manager_user_id = $request->input('manager_user_id');
        }
        if ($request->has('is_active')) {
            $warehouse->is_active = $request->boolean('is_active');
        }

        $warehouse->save();

        return $this->successResponse(
            new WarehouseResource($warehouse->load(['manager', 'stores'])),
            'Warehouse updated successfully.'
        );
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $warehouse = Warehouse::find($id);

        if (! $warehouse) {
            return $this->errorResponse('Warehouse not found.', 404);
        }

        $warehouse->is_active = ! $warehouse->is_active;
        $warehouse->save();

        $statusText = $warehouse->is_active ? 'activated' : 'deactivated';

        return $this->successResponse(
            new WarehouseResource($warehouse),
            "Warehouse {$statusText} successfully."
        );
    }

    public function assignToStore(AssignStoreWarehousesRequest $request, int $storeId): JsonResponse
    {
        $store = Store::find($storeId);

        if (! $store) {
            return $this->errorResponse('Store not found.', 404);
        }

        $warehouseIds = array_unique($request->input('warehouse_ids', []));
        $store->warehouses()->sync($warehouseIds);

        return $this->successResponse(
            WarehouseResource::collection($store->warehouses()->with(['manager', 'stores'])->get()),
            'Store warehouses assigned successfully.'
        );
    }
}
