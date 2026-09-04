<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\CustomerPayment;
use App\Models\InvoicePayment;
use App\Services\PaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PaymentCollectionController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $storeId = $request->filled('store_id') ? (int) $request->input('store_id') : null;
        $customerId = $request->filled('customer_id') ? (int) $request->input('customer_id') : null;
        $paymentMethod = $request->filled('payment_method') ? trim((string) $request->input('payment_method')) : null;
        $dateFrom = $request->filled('date_from') ? $request->input('date_from') : null;
        $dateTo = $request->filled('date_to') ? $request->input('date_to') : null;
        $search = $request->filled('search') ? trim((string) $request->input('search')) : null;

        // 1. Fetch Customer Payments
        $custQuery = CustomerPayment::with(['customer', 'invoice', 'store', 'collector']);
        if ($storeId) {
            $custQuery->where('store_id', $storeId);
        }
        if ($customerId) {
            $custQuery->where('customer_id', $customerId);
        }
        if ($paymentMethod) {
            $custQuery->where('payment_method', $paymentMethod);
        }
        if ($dateFrom) {
            $custQuery->where('payment_date', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo) {
            $custQuery->where('payment_date', '<=', Carbon::parse($dateTo)->endOfDay());
        }
        if ($search) {
            $custQuery->where(function ($q) use ($search) {
                $q->where('payment_number', 'LIKE', "%{$search}%")
                  ->orWhere('transaction_reference', 'LIKE', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'LIKE', "%{$search}%")
                         ->orWhere('mobile_number', 'LIKE', "%{$search}%");
                  });
            });
        }
        $customerPayments = $custQuery->get();

        // 2. Fetch Invoice Payments
        $invQuery = InvoicePayment::with(['invoice.customer', 'invoice.store', 'invoice.creator']);
        if ($storeId) {
            $invQuery->whereHas('invoice', fn($q) => $q->where('store_id', $storeId));
        }
        if ($customerId) {
            $invQuery->whereHas('invoice', fn($q) => $q->where('customer_id', $customerId));
        }
        if ($paymentMethod) {
            $invQuery->where('payment_method', $paymentMethod);
        }
        if ($dateFrom) {
            $invQuery->where('payment_time', '>=', Carbon::parse($dateFrom)->startOfDay());
        }
        if ($dateTo) {
            $invQuery->where('payment_time', '<=', Carbon::parse($dateTo)->endOfDay());
        }
        if ($search) {
            $invQuery->where(function ($q) use ($search) {
                $q->where('transaction_reference', 'LIKE', "%{$search}%")
                  ->orWhereHas('invoice', function ($iq) use ($search) {
                      $iq->where('invoice_number', 'LIKE', "%{$search}%")
                         ->orWhereHas('customer', function ($cq) use ($search) {
                             $cq->where('name', 'LIKE', "%{$search}%")
                                ->orWhere('mobile_number', 'LIKE', "%{$search}%");
                         });
                  });
            });
        }
        $invoicePayments = $invQuery->get();

        // Combine into unified payment list
        $combined = collect();

        foreach ($customerPayments as $cp) {
            $combined->push([
                'id' => 'cp_' . $cp->id,
                'source' => 'customer_payment',
                'payment_number' => $cp->payment_number ?? ('PAY-' . $cp->id),
                'payment_date' => $cp->payment_date?->toIso8601String() ?? $cp->created_at?->toIso8601String(),
                'created_at' => $cp->created_at?->toIso8601String(),
                'amount' => (float) $cp->amount,
                'payment_method' => is_object($cp->payment_method) ? $cp->payment_method->value : (string) $cp->payment_method,
                'transaction_reference' => $cp->transaction_reference,
                'notes' => $cp->notes,
                'customer' => $cp->customer,
                'invoice' => $cp->invoice,
                'store' => $cp->store,
                'collector' => $cp->collector,
            ]);
        }

        foreach ($invoicePayments as $ip) {
            $methodStr = is_object($ip->payment_method) ? $ip->payment_method->value : (string) $ip->payment_method;
            $invNum = $ip->invoice?->invoice_number ?? ('INV-' . $ip->invoice_id);
            $combined->push([
                'id' => 'ip_' . $ip->id,
                'source' => 'invoice_payment',
                'payment_number' => 'PAY-' . $invNum,
                'payment_date' => $ip->payment_time ? Carbon::parse($ip->payment_time)->toIso8601String() : $ip->created_at?->toIso8601String(),
                'created_at' => $ip->created_at?->toIso8601String(),
                'amount' => (float) $ip->amount,
                'payment_method' => $methodStr,
                'transaction_reference' => $ip->transaction_reference,
                'notes' => $ip->notes,
                'customer' => $ip->invoice?->customer,
                'invoice' => $ip->invoice,
                'store' => $ip->invoice?->store,
                'collector' => $ip->invoice?->creator,
            ]);
        }

        // Sort by date descending
        $sorted = $combined->sortByDesc('payment_date')->values();

        $page = (int) $request->input('page', 1);
        $perPage = min((int) $request->input('per_page', 15), 100);
        $total = $sorted->count();
        $sliced = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        return $this->successResponse([
            'items' => $sliced,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / max(1, $perPage)),
            ],
        ], 'Payment collections retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'invoice_id' => 'nullable|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|string|in:cash,upi,card,bank_transfer,cheque,other',
            'transaction_reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'store_id' => 'nullable|exists:stores,id',
            'payment_date' => 'nullable|date',
        ]);

        try {
            $payment = $this->paymentService->collectCustomerPayment($validated, $request->user());
            return $this->successResponse($payment, 'Payment collected successfully.', 201);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to record customer payment: '.$e->getMessage(), 500);
        }
    }
}
