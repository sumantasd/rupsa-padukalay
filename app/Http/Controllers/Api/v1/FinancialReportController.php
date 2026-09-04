<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConsolidatedSalesResource;
use App\Http\Resources\GstLiabilityResource;
use App\Http\Resources\ProfitLossResource;
use App\Services\FinancialReportService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected FinancialReportService $reportService
    ) {}

    private function getErrorCode(\Exception $e): int
    {
        $code = $e->getCode();

        return (is_int($code) && $code >= 400 && $code < 600) ? $code : 422;
    }

    public function consolidatedSales(Request $request): JsonResponse
    {
        try {
            $data = $this->reportService->getConsolidatedSales($request->all(), $request->user());

            return $this->successResponse(
                new ConsolidatedSalesResource($data),
                'Consolidated multi-store sales report retrieved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve consolidated sales report: '.$e->getMessage(), 500);
        }
    }

    public function profitLoss(Request $request): JsonResponse
    {
        try {
            $data = $this->reportService->getProfitAndLoss($request->all(), $request->user());

            return $this->successResponse(
                new ProfitLossResource($data),
                'Profit & Loss statement retrieved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve Profit & Loss statement: '.$e->getMessage(), 500);
        }
    }

    public function gstLiability(Request $request): JsonResponse
    {
        try {
            $data = $this->reportService->getGstLiability($request->all(), $request->user());

            return $this->successResponse(
                new GstLiabilityResource($data),
                'GST & Tax liability report retrieved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve GST liability report: '.$e->getMessage(), 500);
        }
    }
}
