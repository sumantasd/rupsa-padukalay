<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreExpenseRequest;
use App\Http\Requests\Master\UpdateExpenseRequest;
use App\Http\Resources\ExpenseCategoryResource;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PosSession;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Expense::with(['category', 'store', 'warehouse', 'posSession', 'creator']);

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $query->whereIn('store_id', $userStoreIds);
        } else {
            if ($request->has('store_id')) {
                $query->where('store_id', (int) $request->input('store_id'));
            }
        }

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', (int) $request->input('warehouse_id'));
        }

        if ($request->has('expense_category_id')) {
            $query->where('expense_category_id', (int) $request->input('expense_category_id'));
        }

        if ($request->has('payment_method')) {
            $query->where('payment_method', trim((string) $request->input('payment_method')));
        }

        if ($request->has('pos_session_id')) {
            $query->where('pos_session_id', (int) $request->input('pos_session_id'));
        }

        if ($request->has('created_by')) {
            $query->where('created_by', (int) $request->input('created_by'));
        }

        if ($request->has('date_from')) {
            $query->whereDate('expense_date', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to')) {
            $query->whereDate('expense_date', '<=', $request->input('date_to'));
        }

        if ($request->has('min_amount')) {
            $query->where('amount', '>=', (float) $request->input('min_amount'));
        }

        if ($request->has('max_amount')) {
            $query->where('amount', '<=', (float) $request->input('max_amount'));
        }

        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('description', 'LIKE', "%{$search}%")
                    ->orWhere('voucher_number', 'LIKE', "%{$search}%")
                    ->orWhereHas('category', function ($cq) use ($search) {
                        $cq->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $expenses = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->successResponse(
            [
                'items' => ExpenseResource::collection($expenses->items()),
                'pagination' => [
                    'current_page' => $expenses->currentPage(),
                    'per_page' => $expenses->perPage(),
                    'total' => $expenses->total(),
                    'last_page' => $expenses->lastPage(),
                ],
            ],
            'Expenses retrieved successfully.'
        );
    }

    public function categories(Request $request): JsonResponse
    {
        $categories = ExpenseCategory::orderBy('name', 'asc')->get();

        return $this->successResponse(
            ExpenseCategoryResource::collection($categories),
            'Expense categories retrieved successfully.'
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $expense = Expense::with(['category', 'store', 'warehouse', 'posSession', 'creator'])->find($id);

        if (! $expense) {
            return $this->errorResponse('Expense record not found.', 404);
        }

        $user = $request->user();

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if ($expense->store_id && ! $userStoreIds->contains($expense->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to view expenses for this store.', 403);
            }
        }

        return $this->successResponse(
            new ExpenseResource($expense),
            'Expense details retrieved successfully.'
        );
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $request->input('store_id');

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');

            if ($storeId) {
                if (! $userStoreIds->contains((int) $storeId)) {
                    return $this->errorResponse('Forbidden: You are not authorized to create expenses for this store.', 403);
                }
            } else {
                $storeId = $userStoreIds->first();
            }
        }

        // Automatically link open POS session if applicable
        $posSessionId = $request->input('pos_session_id');
        if (! $posSessionId && $storeId) {
            $openSession = PosSession::where('store_id', $storeId)
                ->where('user_id', $user->id)
                ->where('status', 'open')
                ->first();
            if ($openSession) {
                $posSessionId = $openSession->id;
            }
        }

        $paymentMethodVal = trim((string) $request->input('payment_method'));
        $paymentMethodEnum = PaymentMethod::tryFrom($paymentMethodVal) ?? $paymentMethodVal;

        $expense = DB::transaction(function () use ($request, $user, $storeId, $posSessionId, $paymentMethodEnum) {
            $exp = Expense::create([
                'expense_category_id' => (int) $request->input('expense_category_id'),
                'store_id' => $storeId ? (int) $storeId : null,
                'warehouse_id' => $request->input('warehouse_id') ? (int) $request->input('warehouse_id') : null,
                'pos_session_id' => $posSessionId ? (int) $posSessionId : null,
                'amount' => (float) $request->input('amount'),
                'payment_method' => $paymentMethodEnum,
                'description' => $request->input('description'),
                'voucher_number' => $request->input('voucher_number'),
                'expense_date' => $request->input('expense_date'),
                'created_by' => $user->id,
            ]);

            app(\App\Services\AuditService::class)->logEvent([
                'user_id' => $user->id,
                'store_id' => $exp->store_id,
                'pos_session_id' => $exp->pos_session_id,
                'module' => 'expense',
                'event_type' => 'expense_created',
                'auditable_type' => Expense::class,
                'auditable_id' => $exp->id,
                'after_state' => $exp->toArray(),
                'reason_notes' => "Expense recorded: {$exp->description}",
            ]);

            return $exp;
        });

        return $this->successResponse(
            new ExpenseResource($expense->load(['category', 'store', 'warehouse', 'posSession', 'creator'])),
            'Expense recorded successfully.',
            201
        );
    }

    public function update(UpdateExpenseRequest $request, int $id): JsonResponse
    {
        $expense = Expense::find($id);

        if (! $expense) {
            return $this->errorResponse('Expense record not found.', 404);
        }

        $user = $request->user();

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');

            if ($expense->store_id && ! $userStoreIds->contains($expense->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to update expenses for this store.', 403);
            }

            if ($request->has('store_id') && $request->input('store_id')) {
                if (! $userStoreIds->contains((int) $request->input('store_id'))) {
                    return $this->errorResponse('Forbidden: You are not authorized to reassign expenses to this store.', 403);
                }
            }
        }

        DB::transaction(function () use ($expense, $request) {
            $data = $request->only([
                'expense_category_id',
                'store_id',
                'warehouse_id',
                'pos_session_id',
                'amount',
                'description',
                'voucher_number',
                'expense_date',
            ]);

            if ($request->has('payment_method')) {
                $methodVal = trim((string) $request->input('payment_method'));
                $data['payment_method'] = PaymentMethod::tryFrom($methodVal) ?? $methodVal;
            }

            $expense->update(array_filter($data, fn ($val) => $val !== null));
        });

        return $this->successResponse(
            new ExpenseResource($expense->fresh(['category', 'store', 'warehouse', 'posSession', 'creator'])),
            'Expense updated successfully.'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $expense = Expense::find($id);

        if (! $expense) {
            return $this->errorResponse('Expense record not found.', 404);
        }

        $user = $request->user();

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if ($expense->store_id && ! $userStoreIds->contains($expense->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to delete expenses for this store.', 403);
            }
        }

        DB::transaction(function () use ($expense) {
            $expense->delete();
        });

        return $this->successResponse(null, 'Expense deleted successfully.');
    }
}
