<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\RedeemLoyaltyPointsRequest;
use App\Http\Resources\LoyaltyAccountResource;
use App\Http\Resources\LoyaltyTransactionResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\LoyaltyTransaction;
use App\Services\LoyaltyService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected LoyaltyService $loyaltyService
    ) {}

    public function getAccount(Request $request, int $id): JsonResponse
    {
        $customer = Customer::find($id);

        if (! $customer) {
            return $this->errorResponse('Customer not found.', 404);
        }

        $account = $this->loyaltyService->getOrCreateAccount($customer);

        return $this->successResponse(
            new LoyaltyAccountResource($account->load('customer')),
            'Customer loyalty account details retrieved successfully.'
        );
    }

    public function getTransactions(Request $request, int $id): JsonResponse
    {
        $customer = Customer::find($id);

        if (! $customer) {
            return $this->errorResponse('Customer not found.', 404);
        }

        $query = LoyaltyTransaction::with(['store', 'performer'])
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
                'items' => LoyaltyTransactionResource::collection($transactions->items()),
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                    'last_page' => $transactions->lastPage(),
                ],
            ],
            'Customer loyalty transactions retrieved successfully.'
        );
    }

    public function redeemPoints(RedeemLoyaltyPointsRequest $request, int $id): JsonResponse
    {
        $invoice = Invoice::find($id);

        if (! $invoice) {
            return $this->errorResponse('Invoice/sale record not found.', 404);
        }

        if (! $invoice->customer_id) {
            return $this->errorResponse('Invoice is not linked to a registered customer.', 422);
        }

        $customer = Customer::find($invoice->customer_id);
        if (! $customer) {
            return $this->errorResponse('Customer not found.', 404);
        }

        $user = $request->user();

        // Store Access Control for non-Super Admin
        if (! $user->roles()->where('name', 'Super Admin')->exists()) {
            $userStoreIds = $user->stores()->pluck('stores.id');
            if (! $userStoreIds->contains($invoice->store_id)) {
                return $this->errorResponse('Forbidden: You are not authorized to perform operations for this store.', 403);
            }
        }

        $points = (int) $request->input('points');
        $notes = $request->input('notes');

        try {
            $result = $this->loyaltyService->redeemPoints(
                $customer,
                $points,
                $invoice,
                $user,
                $invoice->store_id,
                $notes
            );

            return $this->successResponse(
                [
                    'transaction' => new LoyaltyTransactionResource($result['transaction']),
                    'discount_value' => $result['discount_value'],
                ],
                'Loyalty points redeemed successfully.'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to redeem loyalty points: '.$e->getMessage(), 500);
        }
    }
}
