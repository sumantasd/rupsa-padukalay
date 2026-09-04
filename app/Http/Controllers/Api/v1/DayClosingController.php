<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\DayClosing;
use App\Services\PaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DayClosingController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = DayClosing::with(['store', 'closer', 'reopener']);

        if ($request->has('store_id')) {
            $query->where('store_id', (int) $request->input('store_id'));
        }

        if ($request->has('date_from')) {
            $query->where('closing_date', '>=', trim((string) $request->input('date_from')));
        }

        if ($request->has('date_to')) {
            $query->where('closing_date', '<=', trim((string) $request->input('date_to')));
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginated = $query->orderBy('closing_date', 'desc')->paginate($perPage);

        return $this->successResponse([
            'items' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 'Day closings retrieved successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $dayClosing = DayClosing::with(['store', 'posSession', 'closer', 'reopener'])->find($id);
        if (! $dayClosing) {
            return $this->errorResponse('Day closing record not found.', 404);
        }

        return $this->successResponse($dayClosing, 'Day closing details retrieved successfully.');
    }

    public function summary(Request $request): JsonResponse
    {
        $storeId = (int) ($request->input('store_id', 1));
        $closingDate = $request->input('closing_date', now()->toDateString());

        $summary = $this->paymentService->getComprehensiveDayClosingSummary($storeId, $closingDate);

        return $this->successResponse($summary, 'Daily financial summary retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'nullable|exists:stores,id',
            'closing_date' => 'nullable|date',
            'actual_cash' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'denomination_breakdown' => 'nullable|array',
        ]);

        try {
            $closing = $this->paymentService->performDayClosing($validated, $request->user());
            return $this->successResponse($closing, 'Day closing completed successfully.', 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Illuminate\Database\QueryException $e) {
            return $this->errorResponse('Day closing for this date has already been completed.', 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function reopen(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        try {
            $reopened = $this->paymentService->reopenDayClosing($id, $validated['reason'], $request->user());
            return $this->successResponse($reopened, 'Day closing reopened successfully.');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to reopen day closing: '.$e->getMessage(), 500);
        }
    }
}
