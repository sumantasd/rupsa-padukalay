<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CrossStoreBalanceResource;
use App\Http\Resources\InventoryTurnoverAnalyticsResource;
use App\Http\Resources\StoreTransferMatrixResource;
use App\Http\Resources\StoreTransferSummaryResource;
use App\Services\StoreTransferAnalyticsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreTransferAnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected StoreTransferAnalyticsService $analyticsService
    ) {}

    private function getErrorCode(\Exception $e): int
    {
        $code = $e->getCode();

        return (is_int($code) && $code >= 400 && $code < 600) ? $code : 422;
    }

    public function summary(Request $request): JsonResponse
    {
        try {
            $summary = $this->analyticsService->getTransferSummary($request->all(), $request->user());

            return $this->successResponse(
                new StoreTransferSummaryResource($summary),
                'Store transfer summary analytics retrieved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve transfer summary: '.$e->getMessage(), 500);
        }
    }

    public function matrix(Request $request): JsonResponse
    {
        try {
            $matrix = $this->analyticsService->getTransferMatrix($request->all(), $request->user());

            return $this->successResponse(
                StoreTransferMatrixResource::collection($matrix),
                'Store transfer matrix retrieved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve transfer matrix: '.$e->getMessage(), 500);
        }
    }

    public function inventoryTurnover(Request $request): JsonResponse
    {
        try {
            $analytics = $this->analyticsService->getInventoryTurnoverAnalytics($request->all(), $request->user());

            return $this->successResponse(
                new InventoryTurnoverAnalyticsResource($analytics),
                'Inventory turnover analytics retrieved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve inventory turnover analytics: '.$e->getMessage(), 500);
        }
    }

    public function crossStoreBalance(Request $request): JsonResponse
    {
        try {
            $paginated = $this->analyticsService->getCrossStoreBalance($request->all(), $request->user());

            return $this->successResponse([
                'items' => CrossStoreBalanceResource::collection($paginated->items()),
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ],
            ], 'Cross-store inventory balance retrieved successfully.');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve cross-store inventory balance: '.$e->getMessage(), 500);
        }
    }

    public function history(Request $request): JsonResponse
    {
        try {
            $paginated = $this->analyticsService->getTransferHistory($request->all(), $request->user());

            return $this->successResponse([
                'items' => $paginated->items(),
                'pagination' => [
                    'current_page' => $paginated->currentPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                    'last_page' => $paginated->lastPage(),
                ],
            ], 'Store transfer history retrieved successfully.');
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve transfer history: '.$e->getMessage(), 500);
        }
    }
}
