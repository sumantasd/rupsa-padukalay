<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\AssignPosRegisterRequest;
use App\Http\Requests\Master\ClosePosRegisterRequest;
use App\Http\Requests\Master\OpenPosRegisterRequest;
use App\Http\Requests\Master\RecordCashMovementRequest;
use App\Http\Requests\Master\StorePosRegisterRequest;
use App\Http\Requests\Master\UpdatePosRegisterRequest;
use App\Http\Resources\PosCashMovementResource;
use App\Http\Resources\PosRegisterDrawerSummaryResource;
use App\Http\Resources\PosRegisterResource;
use App\Models\PosRegister;
use App\Models\PosRegisterCashMovement;
use App\Models\PosSession;
use App\Services\PosRegisterService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosRegisterController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PosRegisterService $registerService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = PosRegister::with(['store', 'assignedUser', 'sessions']);

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $query->whereIn('store_id', $userStoreIds);
        } else {
            if ($request->has('store_id')) {
                $query->where('store_id', (int) $request->input('store_id'));
            }
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $registers = $query->orderBy('id', 'asc')->paginate($perPage);

        return $this->successResponse(
            [
                'items' => PosRegisterResource::collection($registers->items()),
                'pagination' => [
                    'current_page' => $registers->currentPage(),
                    'per_page' => $registers->perPage(),
                    'total' => $registers->total(),
                    'last_page' => $registers->lastPage(),
                ],
            ],
            'POS registers retrieved successfully.'
        );
    }

    public function store(StorePosRegisterRequest $request): JsonResponse
    {
        $user = $request->user();
        $storeId = (int) $request->input('store_id');

        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($storeId)) {
                return $this->errorResponse('Forbidden: You are not authorized to create registers for this store.', 403);
            }
        }

        $code = strtoupper(trim((string) $request->input('code')));
        $existing = PosRegister::where('store_id', $storeId)->where('code', $code)->first();
        if ($existing) {
            return $this->errorResponse("POS Register code '{$code}' already exists for this store.", 422);
        }

        $register = PosRegister::create([
            'store_id' => $storeId,
            'code' => $code,
            'name' => trim((string) $request->input('name')),
            'is_active' => $request->has('is_active') ? (bool) $request->input('is_active') : true,
            'assigned_user_id' => $request->input('assigned_user_id'),
        ]);

        return $this->successResponse(
            new PosRegisterResource($register->load(['store', 'assignedUser', 'sessions'])),
            'POS register created successfully.',
            201
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $register = PosRegister::with(['store', 'assignedUser', 'sessions'])->find($id);

        if (! $register) {
            return $this->errorResponse('POS register not found.', 404);
        }

        $user = $request->user();
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($register->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to view registers for this store.', 403);
            }
        }

        return $this->successResponse(
            new PosRegisterResource($register),
            'POS register details retrieved successfully.'
        );
    }

    public function update(UpdatePosRegisterRequest $request, int $id): JsonResponse
    {
        $register = PosRegister::find($id);

        if (! $register) {
            return $this->errorResponse('POS register not found.', 404);
        }

        $user = $request->user();
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($register->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to edit registers for this store.', 403);
            }
        }

        $data = $request->validated();
        if (isset($data['code'])) {
            $code = strtoupper(trim($data['code']));
            $existing = PosRegister::where('store_id', $register->store_id)
                ->where('code', $code)
                ->where('id', '!=', $register->id)
                ->first();
            if ($existing) {
                return $this->errorResponse("POS Register code '{$code}' already exists for this store.", 422);
            }
            $data['code'] = $code;
        }

        $register->update($data);

        return $this->successResponse(
            new PosRegisterResource($register->load(['store', 'assignedUser', 'sessions'])),
            'POS register updated successfully.'
        );
    }

    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $register = PosRegister::find($id);

        if (! $register) {
            return $this->errorResponse('POS register not found.', 404);
        }

        $user = $request->user();
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($register->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to toggle register status for this store.', 403);
            }
        }

        $register->update(['is_active' => ! $register->is_active]);

        return $this->successResponse(
            new PosRegisterResource($register->load(['store', 'assignedUser', 'sessions'])),
            'POS register status updated successfully.'
        );
    }

    public function assign(AssignPosRegisterRequest $request, int $id): JsonResponse
    {
        $register = PosRegister::find($id);

        if (! $register) {
            return $this->errorResponse('POS register not found.', 404);
        }

        $user = $request->user();
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($register->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to assign registers for this store.', 403);
            }
        }

        $register->update(['assigned_user_id' => $request->input('user_id')]);

        return $this->successResponse(
            new PosRegisterResource($register->load(['store', 'assignedUser', 'sessions'])),
            'POS register cashier assigned successfully.'
        );
    }

    public function open(OpenPosRegisterRequest $request, int $id): JsonResponse
    {
        $register = PosRegister::find($id);

        if (! $register) {
            return $this->errorResponse('POS register not found.', 404);
        }

        $user = $request->user();
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($register->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to open registers for this store.', 403);
            }
        }

        try {
            $session = $this->registerService->openRegister(
                $register,
                $user,
                (float) $request->input('opening_cash', 0.0),
                $request->input('notes'),
                $request->input('client_session_uuid')
            );

            $summary = $this->registerService->calculateDrawerSummary($session);

            return $this->successResponse(
                new PosRegisterDrawerSummaryResource($summary),
                'POS register opened successfully.',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to open POS register: '.$e->getMessage(), 500);
        }
    }

    public function currentSession(Request $request, int $id): JsonResponse
    {
        $register = PosRegister::find($id);

        if (! $register) {
            return $this->errorResponse('POS register not found.', 404);
        }

        $user = $request->user();
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($register->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to view session for this store.', 403);
            }
        }

        $session = PosSession::where('pos_register_id', $register->id)
            ->where('status', 'open')
            ->first();

        if (! $session) {
            return $this->errorResponse('No active open session for this register.', 404);
        }

        $summary = $this->registerService->calculateDrawerSummary($session);

        return $this->successResponse(
            new PosRegisterDrawerSummaryResource($summary),
            'Current register session summary retrieved successfully.'
        );
    }

    public function recordCashMovement(RecordCashMovementRequest $request): JsonResponse
    {
        $user = $request->user();
        $sessionId = $request->input('pos_session_id');

        if ($sessionId) {
            $session = PosSession::find((int) $sessionId);
        } else {
            $registerId = $request->input('pos_register_id');
            if ($registerId) {
                $session = PosSession::where('pos_register_id', (int) $registerId)->where('status', 'open')->first();
            } else {
                $session = PosSession::where('user_id', $user->id)->where('status', 'open')->latest('opened_at')->first();
            }
        }

        if (! $session) {
            return $this->errorResponse('Active POS session required to record cash movement.', 422);
        }

        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($session->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to record cash movements for this store.', 403);
            }
        }

        try {
            $movement = DB::transaction(function () use ($session, $user, $request) {
                return $this->registerService->recordCashMovement(
                    $session,
                    $user,
                    (string) $request->input('movement_type'),
                    (float) $request->input('amount'),
                    (string) $request->input('reason'),
                    $request->input('reference_number'),
                    $request->input('client_trans_uuid')
                );
            });

            return $this->successResponse(
                new PosCashMovementResource($movement->load(['user'])),
                'Cash movement recorded successfully.',
                201
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to record cash movement: '.$e->getMessage(), 500);
        }
    }

    public function cashMovements(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = PosRegisterCashMovement::with(['user', 'posRegister', 'posSession']);

        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $query->whereHas('posSession', function ($q) use ($userStoreIds) {
                $q->whereIn('store_id', $userStoreIds);
            });
        }

        if ($request->has('pos_session_id')) {
            $query->where('pos_session_id', (int) $request->input('pos_session_id'));
        }

        if ($request->has('pos_register_id')) {
            $query->where('pos_register_id', (int) $request->input('pos_register_id'));
        }

        if ($request->has('movement_type')) {
            $query->where('movement_type', trim((string) $request->input('movement_type')));
        }

        $perPage = (int) $request->input('per_page', 15);
        $movements = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->successResponse(
            [
                'items' => PosCashMovementResource::collection($movements->items()),
                'pagination' => [
                    'current_page' => $movements->currentPage(),
                    'per_page' => $movements->perPage(),
                    'total' => $movements->total(),
                    'last_page' => $movements->lastPage(),
                ],
            ],
            'Cash movements retrieved successfully.'
        );
    }

    public function close(ClosePosRegisterRequest $request, int $id): JsonResponse
    {
        $register = PosRegister::find($id);

        if (! $register) {
            return $this->errorResponse('POS register not found.', 404);
        }

        $user = $request->user();
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($register->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to close session for this store.', 403);
            }
        }

        $session = PosSession::where('pos_register_id', $register->id)
            ->where('status', 'open')
            ->first();

        if (! $session) {
            return $this->errorResponse('No open session found for this register.', 422);
        }

        try {
            $closedSession = DB::transaction(function () use ($session, $request) {
                return $this->registerService->closeRegister(
                    $session,
                    (float) $request->input('closing_cash_actual'),
                    $request->input('notes')
                );
            });

            $summary = $this->registerService->calculateDrawerSummary($closedSession);

            return $this->successResponse(
                new PosRegisterDrawerSummaryResource($summary),
                'POS register closed and reconciled successfully.'
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to close POS register: '.$e->getMessage(), 500);
        }
    }

    public function reconciliations(Request $request, int $id): JsonResponse
    {
        $register = PosRegister::find($id);

        if (! $register) {
            return $this->errorResponse('POS register not found.', 404);
        }

        $user = $request->user();
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($register->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to view reconciliations for this store.', 403);
            }
        }

        $sessions = PosSession::where('pos_register_id', $register->id)
            ->where('status', 'closed')
            ->orderBy('closed_at', 'desc')
            ->paginate((int) $request->input('per_page', 15));

        $summaries = collect($sessions->items())->map(function ($sess) {
            return $this->registerService->calculateDrawerSummary($sess);
        });

        return $this->successResponse(
            [
                'items' => PosRegisterDrawerSummaryResource::collection($summaries),
                'pagination' => [
                    'current_page' => $sessions->currentPage(),
                    'per_page' => $sessions->perPage(),
                    'total' => $sessions->total(),
                    'last_page' => $sessions->lastPage(),
                ],
            ],
            'Register closed session reconciliations retrieved successfully.'
        );
    }
}
