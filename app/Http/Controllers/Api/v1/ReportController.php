<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ReportFilterRequest;
use App\Services\ReportService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ReportService $reportService
    ) {}

    public function salesSummary(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->validated();

        $summary = $this->reportService->getSalesSummary($user, $filters);

        return $this->successResponse(
            $summary,
            'Sales summary report retrieved successfully.'
        );
    }

    public function storePerformance(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->validated();

        $performance = $this->reportService->getStorePerformance($user, $filters);

        return $this->successResponse(
            $performance,
            'Store performance report retrieved successfully.'
        );
    }

    public function stockValuation(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->validated();

        $valuation = $this->reportService->getStockValuation($user, $filters);

        return $this->successResponse(
            $valuation,
            'Stock valuation report retrieved successfully.'
        );
    }

    public function taxSummary(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->validated();

        $taxSummary = $this->reportService->getTaxSummary($user, $filters);

        return $this->successResponse(
            $taxSummary,
            'GST tax summary report retrieved successfully.'
        );
    }

    public function purchaseSummary(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->validated();

        $summary = $this->reportService->getPurchaseSummary($user, $filters);

        return $this->successResponse(
            $summary,
            'Purchase summary report retrieved successfully.'
        );
    }

    public function customerSummary(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->validated();

        $summary = $this->reportService->getCustomerSummary($user, $filters);

        return $this->successResponse(
            $summary,
            'Customer summary report retrieved successfully.'
        );
    }

    public function paymentSummary(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->validated();

        $summary = $this->reportService->getPaymentSummary($user, $filters);

        return $this->successResponse(
            $summary,
            'Payment summary report retrieved successfully.'
        );
    }

    public function itemWiseSales(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->validated();

        $summary = $this->reportService->getItemWiseSales($user, $filters);

        return $this->successResponse(
            $summary,
            'Item-wise sales report retrieved successfully.'
        );
    }

    public function dateWiseSales(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->validated();

        $summary = $this->reportService->getDateWiseSales($user, $filters);

        return $this->successResponse(
            $summary,
            'Date-wise sales report retrieved successfully.'
        );
    }

    public function dateWisePayments(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->validated();

        $summary = $this->reportService->getDateWisePayments($user, $filters);

        return $this->successResponse(
            $summary,
            'Date-wise payments report retrieved successfully.'
        );
    }

    public function dateWiseProfitLoss(ReportFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        $filters = $request->validated();

        $summary = $this->reportService->getDateWiseProfitLoss($user, $filters);

        return $this->successResponse(
            $summary,
            'Date-wise Profit & Loss statement retrieved successfully.'
        );
    }
}
