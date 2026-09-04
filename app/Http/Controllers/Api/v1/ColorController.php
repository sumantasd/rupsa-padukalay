<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreColorRequest;
use App\Http\Requests\Master\UpdateColorRequest;
use App\Http\Resources\ColorResource;
use App\Models\Color;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ColorController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Color::query();

        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where('name', 'LIKE', "%{$search}%")
                ->orWhere('code', 'LIKE', "%{$search}%");
        }

        $colors = $query->orderBy('name')->get();

        return $this->successResponse(
            ColorResource::collection($colors),
            'Colors retrieved successfully.'
        );
    }

    public function store(StoreColorRequest $request): JsonResponse
    {
        $color = Color::create([
            'name' => trim($request->input('name')),
            'code' => strtoupper(trim($request->input('code'))),
            'hex_code' => $request->input('hex_code'),
        ]);

        return $this->successResponse(
            new ColorResource($color),
            'Color created successfully.',
            201
        );
    }

    public function show(int $id): JsonResponse
    {
        $color = Color::find($id);

        if (! $color) {
            return $this->errorResponse('Color not found.', 404);
        }

        return $this->successResponse(
            new ColorResource($color),
            'Color details retrieved successfully.'
        );
    }

    public function update(UpdateColorRequest $request, int $id): JsonResponse
    {
        $color = Color::find($id);

        if (! $color) {
            return $this->errorResponse('Color not found.', 404);
        }

        $color->name = trim($request->input('name'));
        $color->code = strtoupper(trim($request->input('code')));
        if ($request->has('hex_code')) {
            $color->hex_code = $request->input('hex_code');
        }

        $color->save();

        return $this->successResponse(
            new ColorResource($color),
            'Color updated successfully.'
        );
    }

    public function toggleStatus(int $id): JsonResponse
    {
        $color = Color::find($id);

        if (! $color) {
            return $this->errorResponse('Color not found.', 404);
        }

        return $this->successResponse(
            new ColorResource($color),
            'Color status toggled successfully.'
        );
    }
}
