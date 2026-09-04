<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardCustomerKpiResource;
use App\Http\Resources\DashboardInventoryKpiResource;
use App\Http\Resources\DashboardPosRegisterKpiResource;
use App\Http\Resources\DashboardProductPerformanceResource;
use App\Http\Resources\DashboardSalesTrendResource;
use App\Http\Resources\DashboardStorePerformanceResource;
use App\Http\Resources\ExecutiveKpiResource;
use App\Services\ExecutiveDashboardService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExecutiveDashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ExecutiveDashboardService $dashboardService
    ) {}

    private function getErrorCode(\Exception $e): int
    {
        $code = $e->getCode();

        return (is_int($code) && $code >= 400 && $code < 600) ? $code : 422;
    }

    public function executiveKpi(Request $request): JsonResponse
    {
        try {
            $kpis = $this->dashboardService->getExecutiveKpiSummary($request->all(), $request->user());

            return $this->successResponse(
                new ExecutiveKpiResource($kpis),
                'Executive KPI summary retrieved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve executive KPI summary: '.$e->getMessage(), 500);
        }
    }

    public function salesTrend(Request $request): JsonResponse
    {
        try {
            $trend = $this->dashboardService->getSalesTrends($request->all(), $request->user());

            return $this->successResponse(
                DashboardSalesTrendResource::collection($trend),
                'Sales trend analytics retrieved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve sales trend analytics: '.$e->getMessage(), 500);
        }
    }

    public function storePerformance(Request $request): JsonResponse
    {
        try {
            $rankings = $this->dashboardService->getStorePerformanceRankings($request->all(), $request->user());

            return $this->successResponse(
                DashboardStorePerformanceResource::collection($rankings),
                'Store performance rankings retrieved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve store performance rankings: '.$e->getMessage(), 500);
        }
    }

    public function productPerformance(Request $request): JsonResponse
    {
        try {
            $products = $this->dashboardService->getProductPerformanceRankings($request->all(), $request->user());

            return $this->successResponse(
                new DashboardProductPerformanceResource($products),
                'Product performance rankings retrieved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve product performance rankings: '.$e->getMessage(), 500);
        }
    }

    public function inventoryKpi(Request $request): JsonResponse
    {
        try {
            $kpis = $this->dashboardService->getInventoryKpis($request->all(), $request->user());

            return $this->successResponse(
                new DashboardInventoryKpiResource($kpis),
                'Inventory KPIs retrieved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve inventory KPIs: '.$e->getMessage(), 500);
        }
    }

    public function posRegisterKpi(Request $request): JsonResponse
    {
        try {
            $kpis = $this->dashboardService->getPosRegisterKpis($request->all(), $request->user());

            return $this->successResponse(
                new DashboardPosRegisterKpiResource($kpis),
                'POS register KPIs retrieved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve POS register KPIs: '.$e->getMessage(), 500);
        }
    }

    public function customerKpi(Request $request): JsonResponse
    {
        try {
            $kpis = $this->dashboardService->getCustomerKpis($request->all(), $request->user());

            return $this->successResponse(
                new DashboardCustomerKpiResource($kpis),
                'Customer KPIs retrieved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve customer KPIs: '.$e->getMessage(), 500);
        }
    }
}
