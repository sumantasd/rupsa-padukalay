<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\PosRegisterCashMovement;
use App\Services\PaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashDrawerController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function status(Request $request): JsonResponse
    {
        $storeId = (int) ($request->input('store_id', 1));
        $statusData = $this->paymentService->getCashDrawerStatus($storeId);

        return $this->successResponse($statusData, 'Cash drawer status retrieved successfully.');
    }

    public function movements(Request $request): JsonResponse
    {
        $query = PosRegisterCashMovement::with(['posRegister', 'user']);

        if ($request->has('pos_session_id')) {
            $query->where('pos_session_id', (int) $request->input('pos_session_id'));
        }

        if ($request->has('movement_type')) {
            $query->where('movement_type', trim((string) $request->input('movement_type')));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $paginated = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->successResponse([
            'items' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 'Cash drawer movements retrieved successfully.');
    }

    public function recordMovement(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'nullable|exists:stores,id',
            'movement_type' => 'required|string|in:cash_in,cash_out,drawer_drop',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:255',
            'reference_number' => 'nullable|string|max:100',
        ]);

        try {
            $movement = $this->paymentService->recordCashMovement($validated, $request->user());
            return $this->successResponse($movement, 'Cash movement recorded successfully.', 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to record cash movement: '.$e->getMessage(), 500);
        }
    }
}
