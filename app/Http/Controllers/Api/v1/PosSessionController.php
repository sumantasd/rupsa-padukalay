<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\PosSessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\ClosePosSessionRequest;
use App\Http\Requests\Master\OpenPosSessionRequest;
use App\Http\Resources\PosSessionResource;
use App\Models\PosSession;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosSessionController extends Controller
{
    use ApiResponse;

    public function open(OpenPosSessionRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->is_active) {
            return $this->errorResponse('Forbidden: User account is inactive.', 403);
        }

        $storeId = (int) ($request->input('store_id') ?: 1);

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($storeId)) {
                return $this->errorResponse('Forbidden: You are not authorized to open a POS session for this store.', 403);
            }
        }

        // Active Session Conflict Check
        $activeSessionExists = PosSession::where('user_id', $user->id)
            ->where('store_id', $storeId)
            ->where('status', PosSessionStatus::OPEN->value)
            ->exists();

        if ($activeSessionExists) {
            return $this->errorResponse('User already has an active POS session for this store. Please close the active session first.', 422);
        }

        try {
            $session = DB::transaction(function () use ($request, $user, $storeId) {
                return PosSession::create([
                    'client_session_uuid' => $request->input('client_session_uuid') ?? (string) Str::uuid(),
                    'store_id' => $storeId,
                    'user_id' => $user->id,
                    'opened_at' => now(),
                    'opening_cash' => (float) $request->input('opening_cash'),
                    'closing_cash_system' => (float) $request->input('opening_cash'),
                    'closing_cash_actual' => 0.00,
                    'cash_difference' => 0.00,
                    'status' => PosSessionStatus::OPEN->value,
                    'notes' => $request->input('notes'),
                ]);
            });

            return $this->successResponse(
                new PosSessionResource($session->load(['store', 'user'])),
                'POS session opened successfully.',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to open POS session: '.$e->getMessage(), 500);
        }
    }

    public function current(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = PosSession::with(['store', 'user'])
            ->where('user_id', $user->id)
            ->where('status', PosSessionStatus::OPEN->value);

        if ($request->has('store_id')) {
            $storeId = (int) $request->input('store_id');
            $query->where('store_id', $storeId);
        }

        $session = $query->latest('opened_at')->first();

        if (! $session) {
            return $this->errorResponse('No active POS session found for this user.', 404);
        }

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($session->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to view this store session.', 403);
            }
        }

        return $this->successResponse(
            new PosSessionResource($session),
            'Active POS session retrieved successfully.'
        );
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = PosSession::with(['store', 'user']);

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');

            if ($request->has('store_id')) {
                $requestedStoreId = (int) $request->input('store_id');
                if (! $userStoreIds->contains($requestedStoreId)) {
                    return $this->errorResponse('Forbidden: You are not authorized to view POS sessions for this store.', 403);
                }
            }

            $query->whereIn('store_id', $userStoreIds);
        } else {
            if ($request->has('store_id')) {
                $query->where('store_id', (int) $request->input('store_id'));
            }
        }

        if ($request->has('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->has('status')) {
            $query->where('status', trim((string) $request->input('status')));
        }

        // Date Range Filters
        if ($request->has('date_from')) {
            $dateFrom = trim((string) $request->input('date_from'));
            $query->where('opened_at', '>=', "{$dateFrom} 00:00:00");
        }

        if ($request->has('date_to')) {
            $dateTo = trim((string) $request->input('date_to'));
            $query->where('opened_at', '<=', "{$dateTo} 23:59:59");
        }

        // Search Filter
        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('client_session_uuid', 'LIKE', "%{$search}%")
                  ->orWhere('notes', 'LIKE', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('store', function ($sq) use ($search) {
                      $sq->where('name', 'LIKE', "%{$search}%");
                  });
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginated = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->successResponse([
            'items' => PosSessionResource::collection($paginated->items()),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 'POS sessions retrieved successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $session = PosSession::with(['store', 'user'])->find($id);

        if (! $session) {
            return $this->errorResponse('POS session not found.', 404);
        }

        $user = $request->user();

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($session->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to view this store session.', 403);
            }
        }

        return $this->successResponse(
            new PosSessionResource($session),
            'POS session details retrieved successfully.'
        );
    }

    public function close(ClosePosSessionRequest $request, int $id): JsonResponse
    {
        $session = PosSession::find($id);

        if (! $session) {
            return $this->errorResponse('POS session not found.', 404);
        }

        $user = $request->user();

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($session->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to close this store session.', 403);
            }
        }

        $statusValue = is_object($session->status) && property_exists($session->status, 'value')
            ? $session->status->value
            : (string) $session->status;

        if ($statusValue === PosSessionStatus::CLOSED->value) {
            return $this->errorResponse('POS session is already closed.', 422);
        }

        try {
            $updatedSession = DB::transaction(function () use ($request, $id) {
                $lockedSession = PosSession::where('id', $id)->lockForUpdate()->first();

                $statusVal = is_object($lockedSession->status) && property_exists($lockedSession->status, 'value')
                    ? $lockedSession->status->value
                    : (string) $lockedSession->status;

                if ($statusVal === PosSessionStatus::CLOSED->value) {
                    throw new \RuntimeException('POS session is already closed.');
                }

                // Compute closing cash system (Opening balance + cash sales - cash expenses)
                // Note: For Task #36 prior to Billing, invoices count is 0.
                $cashSalesTotal = (float) $lockedSession->invoices()->where('status', 'paid')->sum('paid_amount');
                $cashExpensesTotal = (float) $lockedSession->expenses()->where('payment_method', 'cash')->sum('amount');

                $closingSystem = round((float) $lockedSession->opening_cash + $cashSalesTotal - $cashExpensesTotal, 2);
                $closingActual = (float) $request->input('closing_cash_actual');
                $difference = round($closingActual - $closingSystem, 2);

                $lockedSession->closed_at = now();
                $lockedSession->closing_cash_system = $closingSystem;
                $lockedSession->closing_cash_actual = $closingActual;
                $lockedSession->cash_difference = $difference;
                $lockedSession->status = PosSessionStatus::CLOSED->value;

                if ($request->has('notes') && ! empty($request->input('notes'))) {
                    $lockedSession->notes = trim(($lockedSession->notes ? $lockedSession->notes . ' | ' : '') . $request->input('notes'));
                }

                $lockedSession->save();

                return $lockedSession->load(['store', 'user']);
            });

            return $this->successResponse(
                new PosSessionResource($updatedSession),
                'POS session closed successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to close POS session: '.$e->getMessage(), 500);
        }
    }
}
