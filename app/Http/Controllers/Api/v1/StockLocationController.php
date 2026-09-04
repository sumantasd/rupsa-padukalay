<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreStockLocationRequest;
use App\Http\Requests\Master\UpdateStockLocationRequest;
use App\Http\Resources\StockLocationResource;
use App\Models\StockLocation;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockLocationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = StockLocation::with(['store', 'warehouse']);

        // Non-Super Admin users only see locations in authorized stores/warehouses
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $query->where(function ($q) use ($userStoreIds) {
                $q->whereIn('store_id', $userStoreIds)
                  ->orWhereHas('warehouse.stores', function ($sq) use ($userStoreIds) {
                      $sq->whereIn('stores.id', $userStoreIds);
                  });
            });
        }

        if ($request->has('store_id')) {
            $query->where('store_id', (int) $request->input('store_id'));
        }

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', (int) $request->input('warehouse_id'));
        }

        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('code', 'LIKE', "%{$search}%")
                  ->orWhere('name', 'LIKE', "%{$search}%");
            });
        }

        $locations = $query->orderBy('code')->get();

        return $this->successResponse(
            StockLocationResource::collection($locations),
            'Stock locations retrieved successfully.'
        );
    }

    public function store(StoreStockLocationRequest $request): JsonResponse
    {
        $location = StockLocation::create([
            'store_id' => $request->input('store_id'),
            'warehouse_id' => $request->input('warehouse_id'),
            'code' => strtoupper(trim($request->input('code'))),
            'name' => trim($request->input('name')),
        ]);

        return $this->successResponse(
            new StockLocationResource($location->load(['store', 'warehouse'])),
            'Stock location created successfully.',
            201
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $location = StockLocation::with(['store', 'warehouse'])->find($id);

        if (! $location) {
            return $this->errorResponse('Stock location not found.', 404);
        }

        $user = $request->user();
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $hasAccess = false;

            if ($location->store_id && $userStoreIds->contains($location->store_id)) {
                $hasAccess = true;
            } elseif ($location->warehouse_id && $location->warehouse->stores()->whereIn('stores.id', $userStoreIds)->exists()) {
                $hasAccess = true;
            }

            if (! $hasAccess) {
                return $this->errorResponse(
                    "Forbidden: You are not authorized to access Stock Location ID {$id}.",
                    403
                );
            }
        }

        return $this->successResponse(
            new StockLocationResource($location),
            'Stock location details retrieved successfully.'
        );
    }

    public function update(UpdateStockLocationRequest $request, int $id): JsonResponse
    {
        $location = StockLocation::find($id);

        if (! $location) {
            return $this->errorResponse('Stock location not found.', 404);
        }

        $location->code = strtoupper(trim($request->input('code')));
        $location->name = trim($request->input('name'));

        if ($request->has('store_id')) {
            $location->store_id = $request->input('store_id');
        }
        if ($request->has('warehouse_id')) {
            $location->warehouse_id = $request->input('warehouse_id');
        }

        $location->save();

        return $this->successResponse(
            new StockLocationResource($location->load(['store', 'warehouse'])),
            'Stock location updated successfully.'
        );
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $location = StockLocation::find($id);

        if (! $location) {
            return $this->errorResponse('Stock location not found.', 404);
        }

        return $this->successResponse(
            new StockLocationResource($location),
            'Stock location status toggled successfully.'
        );
    }
}
