<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreTaxRateRequest;
use App\Http\Requests\Master\UpdateTaxRateRequest;
use App\Http\Resources\TaxRateResource;
use App\Models\TaxRate;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxRateController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = TaxRate::query();

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        $rates = $query->orderBy('rate_percentage')->get();

        return $this->successResponse(
            TaxRateResource::collection($rates),
            'Tax rates retrieved successfully.'
        );
    }

    public function store(StoreTaxRateRequest $request): JsonResponse
    {
        $rate = (float) $request->input('rate_percentage');
        $halfRate = round($rate / 2, 2);

        $taxRate = TaxRate::create([
            'name' => trim($request->input('name')),
            'rate_percentage' => $rate,
            'cgst_percentage' => (float) $request->input('cgst_percentage', $halfRate),
            'sgst_percentage' => (float) $request->input('sgst_percentage', $halfRate),
            'igst_percentage' => (float) $request->input('igst_percentage', $rate),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return $this->successResponse(
            new TaxRateResource($taxRate),
            'Tax rate created successfully.',
            201
        );
    }

    public function show(int $id): JsonResponse
    {
        $taxRate = TaxRate::find($id);

        if (! $taxRate) {
            return $this->errorResponse('Tax rate not found.', 404);
        }

        return $this->successResponse(
            new TaxRateResource($taxRate),
            'Tax rate details retrieved successfully.'
        );
    }

    public function update(UpdateTaxRateRequest $request, int $id): JsonResponse
    {
        $taxRate = TaxRate::find($id);

        if (! $taxRate) {
            return $this->errorResponse('Tax rate not found.', 404);
        }

        $rate = (float) $request->input('rate_percentage');
        $halfRate = round($rate / 2, 2);

        $taxRate->name = trim($request->input('name'));
        $taxRate->rate_percentage = $rate;
        $taxRate->cgst_percentage = (float) $request->input('cgst_percentage', $halfRate);
        $taxRate->sgst_percentage = (float) $request->input('sgst_percentage', $halfRate);
        $taxRate->igst_percentage = (float) $request->input('igst_percentage', $rate);

        if ($request->has('is_active')) {
            $taxRate->is_active = $request->boolean('is_active');
        }

        $taxRate->save();

        return $this->successResponse(
            new TaxRateResource($taxRate),
            'Tax rate updated successfully.'
        );
    }
}
