<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerPurchaseAnalyticsResource;
use App\Http\Resources\CustomerPurchaseHistoryResource;
use App\Http\Resources\CustomerPurchaseSummaryResource;
use App\Http\Resources\CustomerRfmResource;
use App\Models\Customer;
use App\Services\CustomerAnalyticsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected CustomerAnalyticsService $analyticsService
    ) {}

    private function getErrorCode(\Exception $e): int
    {
        $code = $e->getCode();

        return (is_int($code) && $code >= 400 && $code < 600) ? $code : 422;
    }

    public function purchaseHistory(Request $request, int $id): JsonResponse
    {
        $customer = Customer::find($id);

        if (! $customer) {
            return $this->errorResponse('Customer not found.', 404);
        }

        try {
            $paginated = $this->analyticsService->getPurchaseHistory($customer, $request->all(), $request->user());

            return $this->successResponse([
                'items' => CustomerPurchaseHistoryResource::collection($paginated->items()),
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ],
            ], 'Customer purchase history retrieved successfully.');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve customer purchase history: '.$e->getMessage(), 500);
        }
    }

    public function purchaseSummary(Request $request, int $id): JsonResponse
    {
        $customer = Customer::find($id);

        if (! $customer) {
            return $this->errorResponse('Customer not found.', 404);
        }

        try {
            $summary = $this->analyticsService->getPurchaseSummary($customer, $request->all(), $request->user());

            return $this->successResponse(
                new CustomerPurchaseSummaryResource($summary),
                'Customer purchase summary retrieved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve customer purchase summary: '.$e->getMessage(), 500);
        }
    }

    public function purchaseAnalytics(Request $request, int $id): JsonResponse
    {
        $customer = Customer::find($id);

        if (! $customer) {
            return $this->errorResponse('Customer not found.', 404);
        }

        try {
            $analytics = $this->analyticsService->getPurchaseAnalytics($customer, $request->all(), $request->user());

            return $this->successResponse(
                new CustomerPurchaseAnalyticsResource($analytics),
                'Customer purchase analytics retrieved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve customer purchase analytics: '.$e->getMessage(), 500);
        }
    }

    public function rfmSegmentation(Request $request, int $id): JsonResponse
    {
        $customer = Customer::find($id);

        if (! $customer) {
            return $this->errorResponse('Customer not found.', 404);
        }

        try {
            $rfm = $this->analyticsService->getRfmSegmentation($customer, $request->user());

            return $this->successResponse(
                new CustomerRfmResource($rfm),
                'Customer RFM segmentation calculated successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to calculate customer RFM segmentation: '.$e->getMessage(), 500);
        }
    }
}
