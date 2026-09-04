<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreCustomerRequest;
use App\Http\Requests\Master\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $storeId = $request->filled('store_id') ? (int) $request->input('store_id') : null;
        $dateFrom = $request->filled('date_from') ? $request->input('date_from') : null;
        $dateTo = $request->filled('date_to') ? $request->input('date_to') : null;

        $invoiceConstraint = function ($q) use ($storeId, $dateFrom, $dateTo) {
            $q->where('status', '!=', 'cancelled')
              ->where('status', '!=', 'CANCELLED');

            if ($storeId) {
                $q->where('store_id', $storeId);
            }
            if ($dateFrom) {
                $q->where('created_at', '>=', \Illuminate\Support\Carbon::parse($dateFrom)->startOfDay());
            }
            if ($dateTo) {
                $q->where('created_at', '<=', \Illuminate\Support\Carbon::parse($dateTo)->endOfDay());
            }
        };

        $paymentConstraint = function ($q) use ($storeId, $dateFrom, $dateTo) {
            if ($storeId) {
                $q->where('store_id', $storeId);
            }
            if ($dateFrom) {
                $q->where('payment_date', '>=', \Illuminate\Support\Carbon::parse($dateFrom)->startOfDay());
            }
            if ($dateTo) {
                $q->where('payment_date', '<=', \Illuminate\Support\Carbon::parse($dateTo)->endOfDay());
            }
        };

        $query = Customer::query()
            ->with(['loyaltyAccount'])
            ->withCount(['invoices as total_purchases_count' => $invoiceConstraint])
            ->withSum(['invoices as total_spent_amount' => $invoiceConstraint], 'grand_total')
            ->withSum(['invoices as invoice_paid_amount' => $invoiceConstraint], 'paid_amount')
            ->withSum(['payments as direct_paid_amount' => $paymentConstraint], 'amount');

        if ($request->has('mobile_number')) {
            $mobile = trim((string) $request->input('mobile_number'));
            $query->where('mobile_number', 'LIKE', "%{$mobile}%");
        }

        if ($request->has('city')) {
            $city = trim((string) $request->input('city'));
            $query->where('city', 'LIKE', "%{$city}%");
        }

        if ($request->has('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('mobile_number', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%")
                  ->orWhere('city', 'LIKE', "%{$search}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 15), 100);
        $paginated = $query->orderBy('id', 'desc')->paginate($perPage);

        return $this->successResponse([
            'items' => CustomerResource::collection($paginated->items()),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'last_page' => $paginated->lastPage(),
            ],
        ], 'Customers retrieved successfully.');
    }

    public function show(int $id): JsonResponse
    {
        $customer = Customer::query()
            ->with(['loyaltyAccount'])
            ->withCount(['invoices as total_purchases_count' => function ($q) {
                $q->where('status', '!=', 'cancelled')
                  ->where('status', '!=', 'CANCELLED');
            }])
            ->withSum(['invoices as total_spent_amount' => function ($q) {
                $q->where('status', '!=', 'cancelled')
                  ->where('status', '!=', 'CANCELLED');
            }], 'grand_total')
            ->withSum(['invoices as invoice_paid_amount' => function ($q) {
                $q->where('status', '!=', 'cancelled')
                  ->where('status', '!=', 'CANCELLED');
            }], 'paid_amount')
            ->withSum('payments as direct_paid_amount', 'amount')
            ->find($id);

        if (! $customer) {
            return $this->errorResponse('Customer not found.', 404);
        }

        return $this->successResponse(
            new CustomerResource($customer),
            'Customer details retrieved successfully.'
        );
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['name'])) {
            $data['name'] = 'Walk-in Customer';
        }

        $customer = Customer::create($data);

        return $this->successResponse(
            new CustomerResource($customer),
            'Customer created successfully.',
            201
        );
    }

    public function update(UpdateCustomerRequest $request, int $id): JsonResponse
    {
        $customer = Customer::find($id);

        if (! $customer) {
            return $this->errorResponse('Customer not found.', 404);
        }

        $customer->update($request->validated());

        return $this->successResponse(
            new CustomerResource($customer),
            'Customer updated successfully.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $customer = Customer::find($id);

        if (! $customer) {
            return $this->errorResponse('Customer not found.', 404);
        }

        $customer->delete();

        return $this->successResponse(null, 'Customer deleted successfully.');
    }
}
