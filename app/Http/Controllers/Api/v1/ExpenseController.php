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
use App\Services\AuditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExpenseController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected AuditService $auditService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Expense::with(['category', 'store', 'warehouse', 'posSession', 'creator']);

        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            $query->where(function ($q) use ($userStoreIds) {
                $q->whereIn('store_id', $userStoreIds)->orWhereNull('store_id');
            });
        } else {
            if ($request->has('store_id') && $request->input('store_id') !== '') {
                $query->where('store_id', (int) $request->input('store_id'));
            }
        }

        if ($request->has('expense_category_id') && $request->input('expense_category_id') !== '') {
            $query->where('expense_category_id', (int) $request->input('expense_category_id'));
        }

        if ($request->has('payment_method') && $request->input('payment_method') !== '') {
            $query->where('payment_method', trim((string) $request->input('payment_method')));
        }

        if ($request->has('status') && $request->input('status') !== '') {
            $query->where('status', trim((string) $request->input('status')));
        }

        if ($request->has('date_from') && $request->input('date_from') !== '') {
            $query->whereDate('expense_date', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to') && $request->input('date_to') !== '') {
            $query->whereDate('expense_date', '<=', $request->input('date_to'));
        }

        if ($request->has('search') && $request->input('search') !== '') {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('expense_number', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%")
                    ->orWhere('payee_name', 'LIKE', "%{$search}%")
                    ->orWhere('voucher_number', 'LIKE', "%{$search}%")
                    ->orWhereHas('category', function ($cq) use ($search) {
                        $cq->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $expenses = $query->orderBy('expense_date', 'desc')->orderBy('id', 'desc')->paginate($perPage);

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
        $this->ensureDefaultCategoriesExist();

        $categories = ExpenseCategory::orderBy('name', 'asc')->get();

        return $this->successResponse(
            ExpenseCategoryResource::collection($categories),
            'Expense categories retrieved successfully.'
        );
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:expense_categories,name'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
        ]);

        $category = ExpenseCategory::create([
            'name' => trim($request->input('name')),
            'code' => $request->input('code') ? strtoupper(trim($request->input('code'))) : null,
            'description' => $request->input('description'),
            'is_active' => true,
        ]);

        try {
            $this->auditService->logEvent([
                'module' => 'expense_category',
                'event_type' => 'created',
                'auditable_type' => ExpenseCategory::class,
                'auditable_id' => $category->id,
                'reason_notes' => "Created expense category '{$category->name}'",
            ]);
        } catch (\Throwable $e) {}

        return $this->successResponse(
            new ExpenseCategoryResource($category),
            'Expense category created successfully.',
            201
        );
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $category = ExpenseCategory::find($id);
        if (! $category) {
            return $this->errorResponse('Expense category not found.', 404);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:expense_categories,name,' . $id],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $category->update([
            'name' => trim($request->input('name')),
            'code' => $request->input('code') ? strtoupper(trim($request->input('code'))) : $category->code,
            'description' => $request->has('description') ? $request->input('description') : $category->description,
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $category->is_active,
        ]);

        return $this->successResponse(
            new ExpenseCategoryResource($category),
            'Expense category updated successfully.'
        );
    }

    public function destroyCategory(Request $request, int $id): JsonResponse
    {
        $category = ExpenseCategory::find($id);
        if (! $category) {
            return $this->errorResponse('Expense category not found.', 404);
        }

        if ($category->expenses()->whereNull('deleted_at')->exists()) {
            return $this->errorResponse('Cannot delete expense category because active expenses are linked to it.', 422);
        }

        $category->delete();

        return $this->successResponse(null, 'Expense category deleted successfully.');
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $expense = Expense::with(['category', 'store', 'warehouse', 'posSession', 'creator'])->find($id);

        if (! $expense) {
            return $this->errorResponse('Expense record not found.', 404);
        }

        $user = $request->user();

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

        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');

            if ($storeId) {
                if (! $userStoreIds->contains((int) $storeId)) {
                    return $this->errorResponse('Forbidden: You are not authorized to record expenses for this store.', 403);
                }
            } else {
                $storeId = $userStoreIds->first();
            }
        }

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

        $attachmentUrl = null;
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('expenses', 'public');
            $attachmentUrl = '/storage/' . $path;
        }

        $expNumber = 'EXP-' . date('Ymd') . '-' . strtoupper(Str::random(5));

        $expense = DB::transaction(function () use ($request, $user, $storeId, $posSessionId, $paymentMethodEnum, $attachmentUrl, $expNumber) {
            $exp = Expense::create([
                'expense_number' => $expNumber,
                'expense_category_id' => (int) $request->input('expense_category_id'),
                'payee_name' => $request->input('payee_name'),
                'store_id' => $storeId ? (int) $storeId : null,
                'warehouse_id' => $request->input('warehouse_id') ? (int) $request->input('warehouse_id') : null,
                'pos_session_id' => $posSessionId ? (int) $posSessionId : null,
                'amount' => (float) $request->input('amount'),
                'payment_method' => $paymentMethodEnum,
                'status' => $request->input('status', 'paid'),
                'description' => $request->input('description'),
                'voucher_number' => $request->input('voucher_number'),
                'attachment_url' => $attachmentUrl,
                'notes' => $request->input('notes'),
                'expense_date' => $request->input('expense_date'),
                'created_by' => $user->id,
            ]);

            $pmStr = is_object($exp->payment_method) ? ($exp->payment_method->value ?? (string) $exp->payment_method) : (string) $exp->payment_method;

            $this->auditService->logEvent([
                'user_id' => $user->id,
                'store_id' => $exp->store_id,
                'pos_session_id' => $exp->pos_session_id,
                'module' => 'expense',
                'event_type' => 'expense_created',
                'auditable_type' => Expense::class,
                'auditable_id' => $exp->id,
                'after_state' => $exp->toArray(),
                'reason_notes' => "Recorded expense #{$exp->expense_number}: ₹{$exp->amount} ({$pmStr})",
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

        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');

            if ($expense->store_id && ! $userStoreIds->contains($expense->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to update expenses for this store.', 403);
            }
        }

        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('expenses', 'public');
            $expense->attachment_url = '/storage/' . $path;
        }

        DB::transaction(function () use ($expense, $request) {
            if ($request->has('expense_category_id')) $expense->expense_category_id = (int) $request->input('expense_category_id');
            if ($request->has('payee_name')) $expense->payee_name = $request->input('payee_name');
            if ($request->has('amount')) $expense->amount = (float) $request->input('amount');
            if ($request->has('expense_date')) $expense->expense_date = $request->input('expense_date');
            if ($request->has('status')) $expense->status = $request->input('status');
            if ($request->has('description')) $expense->description = $request->input('description');
            if ($request->has('voucher_number')) $expense->voucher_number = $request->input('voucher_number');
            if ($request->has('notes')) $expense->notes = $request->input('notes');

            if ($request->has('payment_method')) {
                $methodVal = trim((string) $request->input('payment_method'));
                $expense->payment_method = PaymentMethod::tryFrom($methodVal) ?? $methodVal;
            }

            $expense->save();
        });

        try {
            $this->auditService->logEvent([
                'user_id' => $user->id,
                'store_id' => $expense->store_id,
                'module' => 'expense',
                'event_type' => 'expense_updated',
                'auditable_type' => Expense::class,
                'auditable_id' => $expense->id,
                'reason_notes' => "Updated expense #{$expense->expense_number}",
            ]);
        } catch (\Throwable $e) {}

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

        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if ($expense->store_id && ! $userStoreIds->contains($expense->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to delete expenses for this store.', 403);
            }
        }

        DB::transaction(function () use ($expense) {
            $expense->delete();
        });

        try {
            $this->auditService->logEvent([
                'user_id' => $user->id,
                'store_id' => $expense->store_id,
                'module' => 'expense',
                'event_type' => 'expense_archived',
                'auditable_type' => Expense::class,
                'auditable_id' => $expense->id,
                'reason_notes' => "Archived expense #{$expense->expense_number}",
            ]);
        } catch (\Throwable $e) {}

        return $this->successResponse(null, 'Expense deleted successfully.');
    }

    public function reports(Request $request): JsonResponse
    {
        $query = Expense::where('status', '!=', 'cancelled');

        if ($request->has('date_from') && $request->input('date_from') !== '') {
            $query->whereDate('expense_date', '>=', $request->input('date_from'));
        }

        if ($request->has('date_to') && $request->input('date_to') !== '') {
            $query->whereDate('expense_date', '<=', $request->input('date_to'));
        }

        if ($request->has('store_id') && $request->input('store_id') !== '') {
            $query->where('store_id', (int) $request->input('store_id'));
        }

        $totalExpenses = (float) (clone $query)->sum('amount');
        $expenseCount = (clone $query)->count();

        // Expenses by category
        $byCategoryRaw = (clone $query)
            ->select('expense_category_id', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as count'))
            ->groupBy('expense_category_id')
            ->get();

        $byCategory = $byCategoryRaw->map(function ($row) {
            $cat = ExpenseCategory::find($row->expense_category_id);
            return [
                'category_id' => $row->expense_category_id,
                'category_name' => $cat?->name ?? 'Uncategorized',
                'total_amount' => (float) $row->total_amount,
                'count' => (int) $row->count,
            ];
        });

        // Expenses by payment method
        $byPaymentMethodRaw = (clone $query)
            ->select('payment_method', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as count'))
            ->groupBy('payment_method')
            ->get();

        $byPaymentMethod = $byPaymentMethodRaw->map(function ($row) {
            $pm = is_object($row->payment_method) && property_exists($row->payment_method, 'value') ? $row->payment_method->value : (string) $row->payment_method;
            return [
                'payment_method' => $pm,
                'total_amount' => (float) $row->total_amount,
                'count' => (int) $row->count,
            ];
        });

        return $this->successResponse([
            'summary' => [
                'total_expenses' => $totalExpenses,
                'total_records' => $expenseCount,
            ],
            'by_category' => $byCategory,
            'by_payment_method' => $byPaymentMethod,
        ], 'Expense analytics retrieved successfully.');
    }

    private function ensureDefaultCategoriesExist(): void
    {
        $defaults = [
            ['name' => 'Rent', 'code' => 'RENT', 'description' => 'Store & warehouse property lease payments'],
            ['name' => 'Electricity', 'code' => 'ELEC', 'description' => 'Power & utility bills'],
            ['name' => 'Salary', 'code' => 'SALARY', 'description' => 'Staff wages and payroll allowances'],
            ['name' => 'Transport', 'code' => 'TRANS', 'description' => 'Logistics, freight & local conveyance'],
            ['name' => 'Internet', 'code' => 'NET', 'description' => 'Broadband & telecommunication charges'],
            ['name' => 'Maintenance', 'code' => 'MAINT', 'description' => 'Store repairs & equipment maintenance'],
            ['name' => 'Packaging', 'code' => 'PKG', 'description' => 'Carry bags, boxes & packaging materials'],
            ['name' => 'Marketing', 'code' => 'MKT', 'description' => 'Advertisements, banners & promotions'],
            ['name' => 'Office Expenses', 'code' => 'OFFICE', 'description' => 'Stationery, tea & daily sundries'],
            ['name' => 'Other', 'code' => 'MISC', 'description' => 'Miscellaneous expenses'],
        ];

        foreach ($defaults as $d) {
            ExpenseCategory::firstOrCreate(
                ['name' => $d['name']],
                ['code' => $d['code'], 'description' => $d['description'], 'is_active' => true]
            );
        }
    }
}
