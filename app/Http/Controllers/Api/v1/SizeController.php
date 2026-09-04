<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreSizeRequest;
use App\Http\Requests\Master\UpdateSizeRequest;
use App\Http\Resources\SizeResource;
use App\Models\Size;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SizeController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Size::query();

        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where('size_number', 'LIKE', "%{$search}%");
        }

        if ($request->has('system')) {
            $system = trim((string) $request->input('system'));
            $query->where('size_system', $system);
        }

        $sizes = $query->orderBy('sort_order')->orderBy('size_number')->get();

        return $this->successResponse(
            SizeResource::collection($sizes),
            'Sizes retrieved successfully.'
        );
    }

    public function store(StoreSizeRequest $request): JsonResponse
    {
        $size = Size::create([
            'size_number' => trim($request->input('size_number')),
            'size_system' => $request->input('size_system', 'UK/IND'),
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->successResponse(
            new SizeResource($size),
            'Size created successfully.',
            201
        );
    }

    public function show(int $id): JsonResponse
    {
        $size = Size::find($id);

        if (! $size) {
            return $this->errorResponse('Size not found.', 404);
        }

        return $this->successResponse(
            new SizeResource($size),
            'Size details retrieved successfully.'
        );
    }

    public function update(UpdateSizeRequest $request, int $id): JsonResponse
    {
        $size = Size::find($id);

        if (! $size) {
            return $this->errorResponse('Size not found.', 404);
        }

        $size->size_number = trim($request->input('size_number'));
        if ($request->has('size_system')) {
            $size->size_system = $request->input('size_system');
        }
        if ($request->has('sort_order')) {
            $size->sort_order = (int) $request->input('sort_order');
        }
        if ($request->has('is_active')) {
            $size->is_active = $request->boolean('is_active');
        }

        $size->save();

        return $this->successResponse(
            new SizeResource($size),
            'Size updated successfully.'
        );
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $size = Size::find($id);

        if (! $size) {
            return $this->errorResponse('Size not found.', 404);
        }

        $size->is_active = ! ($size->is_active ?? true);
        $size->save();

        return $this->successResponse(
            new SizeResource($size),
            'Size status toggled successfully.'
        );
    }
}
