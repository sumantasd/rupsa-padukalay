<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogDetailResource;
use App\Http\Resources\AuditLogResource;
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuditService $auditService
    ) {}

    private function getErrorCode(\Exception $e): int
    {
        $code = $e->getCode();

        return (is_int($code) && $code >= 400 && $code < 600) ? $code : 422;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $logs = $this->auditService->getAuditLogs($request->all(), $request->user());

            return $this->successResponse(
                [
                    'items' => AuditLogResource::collection($logs->items()),
                    'pagination' => [
                        'current_page' => $logs->currentPage(),
                        'per_page' => $logs->perPage(),
                        'total' => $logs->total(),
                        'last_page' => $logs->lastPage(),
                    ],
                ],
                'Audit logs retrieved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve audit logs: '.$e->getMessage(), 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $log = $this->auditService->getAuditLogDetails($id, $request->user());

            return $this->successResponse(
                new AuditLogDetailResource($log),
                'Audit log details retrieved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve audit log details: '.$e->getMessage(), 500);
        }
    }

    public function financialTrail(Request $request): JsonResponse
    {
        try {
            $logs = $this->auditService->getFinancialAuditTrail($request->all(), $request->user());

            return $this->successResponse(
                [
                    'items' => AuditLogResource::collection($logs->items()),
                    'pagination' => [
                        'current_page' => $logs->currentPage(),
                        'per_page' => $logs->perPage(),
                        'total' => $logs->total(),
                        'last_page' => $logs->lastPage(),
                    ],
                ],
                'Financial audit trail retrieved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve financial audit trail: '.$e->getMessage(), 500);
        }
    }

    public function entityHistory(Request $request, string $type, int $id): JsonResponse
    {
        try {
            $logs = $this->auditService->getEntityAuditHistory($type, $id, $request->user());

            return $this->successResponse(
                [
                    'items' => AuditLogResource::collection($logs->items()),
                    'pagination' => [
                        'current_page' => $logs->currentPage(),
                        'per_page' => $logs->perPage(),
                        'total' => $logs->total(),
                        'last_page' => $logs->lastPage(),
                    ],
                ],
                'Entity audit history retrieved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve entity audit history: '.$e->getMessage(), 500);
        }
    }

    public function userActivity(Request $request, int $userId): JsonResponse
    {
        try {
            $logs = $this->auditService->getUserActivityTrail($userId, $request->all(), $request->user());

            return $this->successResponse(
                [
                    'items' => AuditLogResource::collection($logs->items()),
                    'pagination' => [
                        'current_page' => $logs->currentPage(),
                        'per_page' => $logs->perPage(),
                        'total' => $logs->total(),
                        'last_page' => $logs->lastPage(),
                    ],
                ],
                'User activity audit trail retrieved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve user activity audit trail: '.$e->getMessage(), 500);
        }
    }

    public function storeHistory(Request $request, int $storeId): JsonResponse
    {
        try {
            $logs = $this->auditService->getStoreAuditHistory($storeId, $request->all(), $request->user());

            return $this->successResponse(
                [
                    'items' => AuditLogResource::collection($logs->items()),
                    'pagination' => [
                        'current_page' => $logs->currentPage(),
                        'per_page' => $logs->perPage(),
                        'total' => $logs->total(),
                        'last_page' => $logs->lastPage(),
                    ],
                ],
                'Store audit history retrieved successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), $this->getErrorCode($e));
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve store audit history: '.$e->getMessage(), 500);
        }
    }
}
