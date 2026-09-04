<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\SyncOfflinePosSaleRequest;
use App\Services\OfflineSyncService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class OfflineSyncController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected OfflineSyncService $syncService
    ) {}

    public function sync(SyncOfflinePosSaleRequest $request): JsonResponse
    {
        $user = $request->user();

        // Extract payloads from batch ('sales') or single transaction
        $salesPayloads = [];
        if ($request->has('sales') && is_array($request->input('sales'))) {
            $salesPayloads = $request->input('sales');
        } else {
            $salesPayloads = [$request->all()];
        }

        $syncSummary = $this->syncService->syncSales($user, $salesPayloads);

        return $this->successResponse(
            $syncSummary,
            'Offline sales synchronized successfully.',
            200
        );
    }
}
