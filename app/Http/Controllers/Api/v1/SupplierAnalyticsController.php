<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierPerformanceAnalyticsResource;
use App\Http\Resources\SupplierPurchaseHistoryResource;
use App\Http\Resources\SupplierPurchaseSummaryResource;
use App\Http\Resources\SupplierRankingResource;
use App\Models\Supplier;
use App\Services\SupplierAnalyticsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierAnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected SupplierAnalyticsService $analyticsService
    ) {}

    private function getErrorCode(\Exception $e): int
    {
        $code = $e->getCode();

        return (is_int($code) && $code >= 400 && $code < 600) ? $code : 422;
    }

    public function purchaseHistory(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::find($id);

        if (! $supplier) {
            return $this->errorResponse('Supplier not found.', 404);
        }

        try {
            $paginated = $this->analyticsService->getPurchaseHistory($supplier, $request->all(), $request->user());

            return $this->successResponse([
                'items' => SupplierPurchaseHistoryResource::collection($paginated->items()),
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ],
            ], 'Supplier purchase history retrieved successfully.');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve supplier purchase history: '.$e->getMessage(), 500);
        }
    }

    public function purchaseSummary(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::find($id);

        if (! $supplier) {
            return $this->errorResponse('Supplier not found.', 404);
        }

        try {
            $summary = $this->analyticsService->getPurchaseSummary($supplier, $request->all(), $request->user());

            return $this->successResponse(
                new SupplierPurchaseSummaryResource($summary),
                'Supplier purchase summary retrieved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve supplier purchase summary: '.$e->getMessage(), 500);
        }
    }

    public function performanceAnalytics(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::find($id);

        if (! $supplier) {
            return $this->errorResponse('Supplier not found.', 404);
        }

        try {
            $analytics = $this->analyticsService->getPerformanceAnalytics($supplier, $request->all(), $request->user());

            return $this->successResponse(
                new SupplierPerformanceAnalyticsResource($analytics),
                'Supplier performance analytics retrieved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve supplier performance analytics: '.$e->getMessage(), 500);
        }
    }

    public function performanceRanking(Request $request): JsonResponse
    {
        try {
            $paginated = $this->analyticsService->getSupplierRankings($request->all(), $request->user());

            return $this->successResponse([
                'items' => SupplierRankingResource::collection($paginated->items()),
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ],
            ], 'Supplier performance rankings retrieved successfully.');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve supplier performance rankings: '.$e->getMessage(), 500);
        }
    }
}
