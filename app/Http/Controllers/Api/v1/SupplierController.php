<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreSupplierRequest;
use App\Http\Requests\Master\UpdateSupplierRequest;
use App\Http\Resources\SupplierPaymentResource;
use App\Http\Resources\SupplierResource;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\SupplierAnalyticsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    use ApiResponse;

    protected SupplierAnalyticsService $analyticsService;

    public function __construct(SupplierAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Supplier::query();

        if ($request->has('phone')) {
            $phone = trim((string) $request->input('phone'));
            $query->where('phone', 'LIKE', "%{$phone}%");
        }

        if ($request->has('company_name')) {
            $comp = trim((string) $request->input('company_name'));
            $query->where('company_name', 'LIKE', "%{$comp}%");
        }

        if ($request->has('city')) {
            $city = trim((string) $request->input('city'));
            $query->where('city', 'LIKE', "%{$city}%");
        }

        if ($request->has('status') && $request->input('status') !== '') {
            $status = $request->input('status');
            if ($status === 'active' || $status === '1' || $status === true) {
                $query->where('is_active', true);
            } elseif ($status === 'inactive' || $status === '0' || $status === false) {
                $query->where('is_active', false);
            }
        }

        if ($request->has('search') && $request->input('search') !== '') {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('code', 'LIKE', "%{$search}%")
                    ->orWhere('company_name', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('gstin', 'LIKE', "%{$search}%")
                    ->orWhere('city', 'LIKE', "%{$search}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginated = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->successResponse([
            'items' => SupplierResource::collection($paginated->items()),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 'Suppliers retrieved successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $supplier = Supplier::find($id);

        if (! $supplier) {
            return $this->errorResponse('Supplier not found.', 404);
        }

        return $this->successResponse(
            new SupplierResource($supplier),
            'Supplier details retrieved successfully.'
        );
    }

    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (empty($data['code'])) {
            $maxId = (int) Supplier::max('id');
            $data['code'] = 'SUP-'.str_pad((string) ($maxId + 1), 4, '0', STR_PAD_LEFT);
        }

        $supplier = Supplier::create($data);
        $balances = $supplier->calculateBalances();
        $supplier->update(['current_balance' => $balances['net_balance']]);

        return $this->successResponse(
            new SupplierResource($supplier->fresh()),
            'Supplier created successfully.',
            201
        );
    }

    public function update(UpdateSupplierRequest $request, int $id): JsonResponse
    {
        $supplier = Supplier::find($id);

        if (! $supplier) {
            return $this->errorResponse('Supplier not found.', 404);
        }

        $supplier->update($request->validated());
        $balances = $supplier->calculateBalances();
        $supplier->update(['current_balance' => $balances['net_balance']]);

        return $this->successResponse(
            new SupplierResource($supplier->fresh()),
            'Supplier updated successfully.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $supplier = Supplier::find($id);

        if (! $supplier) {
            return $this->errorResponse('Supplier not found.', 404);
        }

        $hasPos = $supplier->purchaseOrders()->count() > 0;
        $hasPayments = $supplier->payments()->count() > 0;
        $hasReturns = $supplier->purchaseReturns()->count() > 0;

        if ($hasPos || $hasPayments || $hasReturns) {
            return $this->errorResponse(
                'This supplier cannot be deleted because transaction records already exist. You may deactivate this supplier instead.',
                422
            );
        }

        $supplier->delete();

        return $this->successResponse(null, 'Supplier deleted successfully.');
    }

    public function storePayment(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::find($id);

        if (! $supplier) {
            return $this->errorResponse('Supplier not found.', 404);
        }

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['nullable', 'date'],
            'payment_method' => ['required', 'string', 'in:cash,upi,bank_transfer,card,other'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'transaction_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        return DB::transaction(function () use ($supplier, $validated, $request) {
            $paymentCount = SupplierPayment::count() + 1;
            $paymentNumber = 'SPAY-'.date('Ym').'-'.str_pad((string) $paymentCount, 4, '0', STR_PAD_LEFT);

            $poId = ! empty($validated['purchase_order_id']) ? (int) $validated['purchase_order_id'] : null;

            if ($poId) {
                $po = PurchaseOrder::where('id', $poId)->where('supplier_id', $supplier->id)->first();
                if ($po) {
                    $newPaid = round((float) $po->paid_amount + (float) $validated['amount'], 2);
                    $newDue = max(0.00, round((float) $po->grand_total - $newPaid, 2));

                    $poStatus = $po->status;
                    if ($newDue <= 0 && $newPaid >= (float) $po->grand_total) {
                        // Keep received/ordered status
                    }

                    $po->update([
                        'paid_amount' => $newPaid,
                        'due_amount' => $newDue,
                    ]);
                }
            }

            $payment = SupplierPayment::create([
                'payment_number' => $paymentNumber,
                'supplier_id' => $supplier->id,
                'purchase_order_id' => $poId,
                'payment_date' => $validated['payment_date'] ?? now()->toDateString(),
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'transaction_reference' => $validated['transaction_reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'recorded_by' => $request->user()?->id ?? 1,
            ]);

            $balances = $supplier->calculateBalances();
            $supplier->update(['current_balance' => $balances['net_balance']]);

            return $this->successResponse(
                new SupplierPaymentResource($payment->fresh(['supplier', 'purchaseOrder', 'recorder'])),
                'Supplier payment recorded successfully.',
                201
            );
        });
    }

    public function payments(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::find($id);

        if (! $supplier) {
            return $this->errorResponse('Supplier not found.', 404);
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginated = $supplier->payments()
            ->with(['purchaseOrder', 'recorder'])
            ->orderBy('payment_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return $this->successResponse([
            'items' => SupplierPaymentResource::collection($paginated->items()),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 'Supplier payment records retrieved successfully.');
    }

    public function ledger(Request $request, int $id): JsonResponse
    {
        $supplier = Supplier::find($id);

        if (! $supplier) {
            return $this->errorResponse('Supplier not found.', 404);
        }

        $ledgerData = $this->analyticsService->getSupplierLedger($supplier, $request->all(), $request->user());

        return $this->successResponse($ledgerData, 'Supplier ledger retrieved successfully.');
    }
}
