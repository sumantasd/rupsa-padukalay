<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\AdjustStoreCreditRequest;
use App\Http\Resources\StoreCreditAccountResource;
use App\Http\Resources\StoreCreditTransactionResource;
use App\Models\Customer;
use App\Models\StoreCreditTransaction;
use App\Services\StoreCreditService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreCreditController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected StoreCreditService $creditService
    ) {}

    public function getAccount(Request $request, int $id): JsonResponse
    {
        $customer = Customer::find($id);

        if (! $customer) {
            return $this->errorResponse('Customer not found.', 404);
        }

        $account = $this->creditService->getOrCreateAccount($customer);

        return $this->successResponse(
            new StoreCreditAccountResource($account->load('customer')),
            'Customer store credit account details retrieved successfully.'
        );
    }

    public function getTransactions(Request $request, int $id): JsonResponse
    {
        $customer = Customer::find($id);

        if (! $customer) {
            return $this->errorResponse('Customer not found.', 404);
        }

        $query = StoreCreditTransaction::with(['store', 'performer'])
            ->where('customer_id', $customer->id);

        if ($request->has('store_id')) {
            $query->where('store_id', (int) $request->input('store_id'));
        }

        if ($request->has('transaction_type')) {
            $query->where('transaction_type', trim((string) $request->input('transaction_type')));
        }

        $perPage = (int) $request->input('per_page', 15);
        $transactions = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->successResponse(
            [
                'items' => StoreCreditTransactionResource::collection($transactions->items()),
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                    'last_page' => $transactions->lastPage(),
                ],
            ],
            'Customer store credit transactions retrieved successfully.'
        );
    }

    public function issueOrAdjust(AdjustStoreCreditRequest $request, int $id): JsonResponse
    {
        $customer = Customer::find($id);

        if (! $customer) {
            return $this->errorResponse('Customer not found.', 404);
        }

        $user = $request->user();
        $storeId = $request->input('store_id');

        if ($storeId && ! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains((int) $storeId)) {
                return $this->errorResponse('Forbidden: You are not authorized to issue store credit for this store.', 403);
            }
        }

        $amount = (float) $request->input('amount');
        $type = $request->input('transaction_type', $amount > 0 ? 'issue_adjustment' : 'payment_used');
        $notes = $request->input('notes');

        try {
            $trans = $this->creditService->issueOrAdjustCredit(
                $customer,
                $amount,
                $type,
                $user,
                $storeId ? (int) $storeId : null,
                'manual_adjustment',
                null,
                null,
                $notes
            );

            return $this->successResponse(
                new StoreCreditTransactionResource($trans->load(['store', 'performer'])),
                'Customer store credit balance adjusted successfully.'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to adjust store credit: '.$e->getMessage(), 500);
        }
    }
}
