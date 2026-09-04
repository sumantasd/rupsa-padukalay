<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreHsnCodeRequest;
use App\Http\Requests\Master\UpdateHsnCodeRequest;
use App\Http\Resources\HsnCodeResource;
use App\Models\HsnCode;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HsnCodeController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = HsnCode::query();

        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where('code', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%");
        }

        $hsnCodes = $query->orderBy('code')->get();

        return $this->successResponse(
            HsnCodeResource::collection($hsnCodes),
            'HSN codes retrieved successfully.'
        );
    }

    public function store(StoreHsnCodeRequest $request): JsonResponse
    {
        $hsn = HsnCode::create([
            'code' => trim($request->input('code')),
            'description' => $request->input('description'),
            'default_gst_rate' => (float) $request->input('default_gst_rate', 0.00),
        ]);

        return $this->successResponse(
            new HsnCodeResource($hsn),
            'HSN code created successfully.',
            201
        );
    }

    public function show(int $id): JsonResponse
    {
        $hsn = HsnCode::find($id);

        if (! $hsn) {
            return $this->errorResponse('HSN code not found.', 404);
        }

        return $this->successResponse(
            new HsnCodeResource($hsn),
            'HSN code details retrieved successfully.'
        );
    }

    public function update(UpdateHsnCodeRequest $request, int $id): JsonResponse
    {
        $hsn = HsnCode::find($id);

        if (! $hsn) {
            return $this->errorResponse('HSN code not found.', 404);
        }

        $hsn->code = trim($request->input('code'));
        if ($request->has('description')) {
            $hsn->description = $request->input('description');
        }
        if ($request->has('default_gst_rate')) {
            $hsn->default_gst_rate = (float) $request->input('default_gst_rate');
        }

        $hsn->save();

        return $this->successResponse(
            new HsnCodeResource($hsn),
            'HSN code updated successfully.'
        );
    }
}
