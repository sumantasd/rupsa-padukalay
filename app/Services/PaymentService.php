<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerRefund;
use App\Models\DayClosing;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PosRegisterCashMovement;
use App\Models\PosSession;
use App\Models\Store;
use App\Models\StoreCreditAccount;
use App\Models\StoreCreditTransaction;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    /**
     * Process Customer Due Payment / Collection
     */
    public function collectCustomerPayment(array $data, User $user): CustomerPayment
    {
        $customerId = (int) $data['customer_id'];
        $customer = Customer::findOrFail($customerId);

        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $storeId = (int) ($data['store_id'] ?? 1);
        $paymentMethod = strtolower(trim($data['payment_method'] ?? 'cash'));
        $transactionRef = isset($data['transaction_reference']) ? trim($data['transaction_reference']) : null;
        $notes = isset($data['notes']) ? trim($data['notes']) : null;
        $paymentDate = $data['payment_date'] ?? now()->toDateTimeString();

        return DB::transaction(function () use ($data, $customer, $amount, $storeId, $paymentMethod, $transactionRef, $notes, $paymentDate, $user) {
            $paymentNumber = 'PAY-'.date('Ymd').'-'.str_pad((string) (CustomerPayment::count() + 1), 5, '0', STR_PAD_LEFT);

            $invoiceId = isset($data['invoice_id']) && (int) $data['invoice_id'] > 0 ? (int) $data['invoice_id'] : null;
            $invoice = null;

            if ($invoiceId) {
                $invoice = Invoice::where('customer_id', $customer->id)->find($invoiceId);
                if (! $invoice) {
                    throw new \InvalidArgumentException("Invoice #{$invoiceId} does not belong to customer {$customer->name}.");
                }

                $due = round((float) $invoice->grand_total - (float) $invoice->paid_amount, 2);
                if ($due <= 0) {
                    throw new \InvalidArgumentException("Invoice #{$invoice->invoice_number} is already fully paid.");
                }

                if ($amount > $due + 0.01) {
                    throw new \InvalidArgumentException("Payment amount ₹{$amount} exceeds remaining invoice due amount ₹{$due}.");
                }

                // Update Invoice
                $newPaid = round((float) $invoice->paid_amount + $amount, 2);
                $status = $newPaid >= (float) $invoice->grand_total ? 'paid' : 'partial';

                $invoice->update([
                    'paid_amount' => $newPaid,
                    'payment_status' => $status,
                ]);

                // Also record in InvoicePayment table
                InvoicePayment::create([
                    'invoice_id' => $invoice->id,
                    'payment_method' => in_array($paymentMethod, ['cash', 'upi', 'card', 'store_credit']) ? $paymentMethod : 'other',
                    'amount' => $amount,
                    'transaction_reference' => $transactionRef,
                    'notes' => $notes ?? "Customer Payment Collection #{$paymentNumber}",
                    'payment_time' => $paymentDate,
                ]);
            } else {
                // Auto-apply payment to oldest unpaid invoices of customer
                $unpaidInvoices = Invoice::where('customer_id', $customer->id)
                    ->whereIn('payment_status', ['unpaid', 'partial'])
                    ->orderBy('id', 'asc')
                    ->get();

                $remainingToApply = $amount;
                foreach ($unpaidInvoices as $unpInv) {
                    if ($remainingToApply <= 0) {
                        break;
                    }

                    $due = round((float) $unpInv->grand_total - (float) $unpInv->paid_amount, 2);
                    if ($due <= 0) {
                        continue;
                    }

                    $apply = min($due, $remainingToApply);
                    $newPaid = round((float) $unpInv->paid_amount + $apply, 2);
                    $status = $newPaid >= (float) $unpInv->grand_total ? 'paid' : 'partial';

                    $unpInv->update([
                        'paid_amount' => $newPaid,
                        'payment_status' => $status,
                    ]);

                    InvoicePayment::create([
                        'invoice_id' => $unpInv->id,
                        'payment_method' => in_array($paymentMethod, ['cash', 'upi', 'card', 'store_credit']) ? $paymentMethod : 'other',
                        'amount' => $apply,
                        'transaction_reference' => $transactionRef,
                        'notes' => $notes ?? "Customer Payment Collection #{$paymentNumber}",
                        'payment_time' => $paymentDate,
                    ]);

                    $remainingToApply = round($remainingToApply - $apply, 2);
                }
            }

            // Record CustomerPayment Header
            $customerPayment = CustomerPayment::create([
                'payment_number' => $paymentNumber,
                'store_id' => $storeId,
                'customer_id' => $customer->id,
                'invoice_id' => $invoice?->id,
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'transaction_reference' => $transactionRef,
                'payment_date' => $paymentDate,
                'notes' => $notes,
                'collected_by' => $user->id,
            ]);

            // If payment is Cash, record movement into active POS Session Cash Drawer
            if ($paymentMethod === 'cash') {
                $activeSession = PosSession::where('store_id', $storeId)->where('status', 'open')->first();
                if ($activeSession) {
                    $register = \App\Models\PosRegister::firstOrCreate(
                        ['store_id' => $storeId],
                        ['code' => 'REG-01', 'name' => 'Main Counter Register', 'is_active' => true]
                    );

                    PosRegisterCashMovement::create([
                        'pos_session_id' => $activeSession->id,
                        'pos_register_id' => $activeSession->pos_register_id ?? $register->id,
                        'user_id' => $user->id,
                        'movement_type' => 'cash_in',
                        'amount' => $amount,
                        'reason' => "Customer Payment Collection #{$paymentNumber} ({$customer->name})",
                        'reference_number' => $paymentNumber,
                    ]);
                }
            }

            // Audit Trail Log
            AuditLog::create([
                'audit_uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'store_id' => $storeId,
                'module' => 'PAYMENTS',
                'event_type' => 'CUSTOMER_PAYMENT_COLLECTED',
                'auditable_type' => CustomerPayment::class,
                'auditable_id' => $customerPayment->id,
                'after_state' => json_encode($customerPayment->toArray()),
                'status' => 'SUCCESS',
                'reason_notes' => "Collected ₹{$amount} from Customer {$customer->name} via {$paymentMethod}",
            ]);

            return $customerPayment->load(['customer', 'invoice', 'store', 'collector']);
        });
    }

    /**
     * Process Transaction Refund
     */
    public function processRefund(array $data, User $user): CustomerRefund
    {
        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Refund amount must be greater than zero.');
        }

        $storeId = (int) ($data['store_id'] ?? 1);
        $refundMethod = strtolower(trim($data['refund_method'] ?? 'cash'));
        $reason = trim($data['reason'] ?? 'Customer Return');
        $transactionRef = isset($data['transaction_reference']) ? trim($data['transaction_reference']) : null;
        $notes = isset($data['notes']) ? trim($data['notes']) : null;

        $invoiceId = isset($data['invoice_id']) ? (int) $data['invoice_id'] : null;
        $invoice = null;
        $customerId = null;

        if ($invoiceId) {
            $invoice = Invoice::findOrFail($invoiceId);
            $customerId = $invoice->customer_id;

            if ($invoice->status === 'cancelled') {
                throw new \InvalidArgumentException("Invoice #{$invoice->invoice_number} is cancelled and cannot be refunded.");
            }

            $alreadyRefunded = (float) CustomerRefund::where('invoice_id', $invoice->id)->sum('amount');
            $maxRefundable = round((float) $invoice->paid_amount - $alreadyRefunded, 2);

            if ($maxRefundable <= 0) {
                throw new \InvalidArgumentException("Invoice #{$invoice->invoice_number} has no remaining refundable balance.");
            }

            if ($amount > $maxRefundable + 0.01) {
                throw new \InvalidArgumentException("Refund amount ₹{$amount} exceeds maximum refundable balance ₹{$maxRefundable}.");
            }
        } elseif (isset($data['customer_id'])) {
            $customerId = (int) $data['customer_id'];
        }

        return DB::transaction(function () use ($data, $amount, $storeId, $refundMethod, $reason, $transactionRef, $notes, $invoice, $customerId, $user) {
            $refundNumber = 'REF-'.date('Ymd').'-'.str_pad((string) (CustomerRefund::count() + 1), 5, '0', STR_PAD_LEFT);

            $activeSession = PosSession::where('store_id', $storeId)->where('status', 'open')->first();

            $refund = CustomerRefund::create([
                'refund_number' => $refundNumber,
                'store_id' => $storeId,
                'customer_id' => $customerId,
                'invoice_id' => $invoice?->id,
                'pos_session_id' => $activeSession?->id,
                'amount' => $amount,
                'refund_method' => $refundMethod,
                'transaction_reference' => $transactionRef,
                'reason' => $reason,
                'notes' => $notes,
                'processed_by' => $user->id,
            ]);

            // If refund is Cash, deduct cash from Cash Drawer
            if ($refundMethod === 'cash') {
                if ($activeSession) {
                    $register = \App\Models\PosRegister::firstOrCreate(
                        ['store_id' => $storeId],
                        ['code' => 'REG-01', 'name' => 'Main Counter Register', 'is_active' => true]
                    );

                    PosRegisterCashMovement::create([
                        'pos_session_id' => $activeSession->id,
                        'pos_register_id' => $activeSession->pos_register_id ?? $register->id,
                        'user_id' => $user->id,
                        'movement_type' => 'cash_out',
                        'amount' => $amount,
                        'reason' => "Customer Refund #{$refundNumber} for Invoice #".($invoice?->invoice_number ?? 'N/A'),
                        'reference_number' => $refundNumber,
                    ]);
                }
            } elseif ($refundMethod === 'store_credit' && $customerId) {
                // Issue store credit to customer
                $creditAccount = StoreCreditAccount::firstOrCreate(
                    ['customer_id' => $customerId],
                    ['current_balance' => 0.00, 'total_issued' => 0.00, 'total_used' => 0.00, 'status' => 'active']
                );

                $balBefore = (float) $creditAccount->current_balance;
                $balAfter = round($balBefore + $amount, 2);

                $creditAccount->update([
                    'current_balance' => $balAfter,
                    'total_issued' => round((float) $creditAccount->total_issued + $amount, 2),
                ]);

                StoreCreditTransaction::create([
                    'customer_id' => $customerId,
                    'store_id' => $storeId,
                    'transaction_type' => 'issue',
                    'amount' => $amount,
                    'balance_before' => $balBefore,
                    'balance_after' => $balAfter,
                    'reference_type' => CustomerRefund::class,
                    'reference_id' => $refund->id,
                    'performed_by' => $user->id,
                    'notes' => "Store Credit issued for Refund #{$refundNumber}",
                ]);
            }

            // Update Invoice status if fully refunded
            if ($invoice) {
                $alreadyRefundedNow = (float) CustomerRefund::where('invoice_id', $invoice->id)->sum('amount');
                if ($alreadyRefundedNow >= (float) $invoice->paid_amount) {
                    $invoice->update(['status' => 'returned']);
                } else {
                    $invoice->update(['status' => 'partially_returned']);
                }
            }

            // Audit Log
            AuditLog::create([
                'audit_uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'store_id' => $storeId,
                'module' => 'PAYMENTS',
                'event_type' => 'TRANSACTION_REFUNDED',
                'auditable_type' => CustomerRefund::class,
                'auditable_id' => $refund->id,
                'after_state' => json_encode($refund->toArray()),
                'status' => 'SUCCESS',
                'reason_notes' => "Processed ₹{$amount} refund via {$refundMethod} for Invoice #".($invoice?->invoice_number ?? 'N/A'),
            ]);

            return $refund->load(['customer', 'invoice', 'store', 'processor']);
        });
    }

    /**
     * Record Manual Cash Drawer Movement (Cash-In / Cash-Out / Float Drop)
     */
    public function recordCashMovement(array $data, User $user): PosRegisterCashMovement
    {
        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Cash movement amount must be greater than zero.');
        }

        $storeId = (int) ($data['store_id'] ?? 1);
        $movementType = strtolower(trim($data['movement_type'] ?? 'cash_in'));
        if (! in_array($movementType, ['cash_in', 'cash_out', 'drawer_drop'])) {
            throw new \InvalidArgumentException('Invalid movement type specified.');
        }

        $reason = trim($data['reason'] ?? 'Manual Cash Adjustment');
        $refNumber = isset($data['reference_number']) ? trim($data['reference_number']) : null;

        $activeSession = PosSession::where('store_id', $storeId)->where('status', 'open')->first();
        if (! $activeSession || ! $activeSession->pos_register_id) {
            throw new \InvalidArgumentException('No active open POS register session found for this store.');
        }

        return DB::transaction(function () use ($activeSession, $movementType, $amount, $reason, $refNumber, $storeId, $user) {
            $movement = PosRegisterCashMovement::create([
                'pos_session_id' => $activeSession->id,
                'pos_register_id' => $activeSession->pos_register_id,
                'user_id' => $user->id,
                'movement_type' => $movementType,
                'amount' => $amount,
                'reason' => $reason,
                'reference_number' => $refNumber,
            ]);

            AuditLog::create([
                'audit_uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'store_id' => $storeId,
                'module' => 'PAYMENTS',
                'event_type' => 'CASH_DRAWER_MOVEMENT',
                'auditable_type' => PosRegisterCashMovement::class,
                'auditable_id' => $movement->id,
                'after_state' => json_encode($movement->toArray()),
                'status' => 'SUCCESS',
                'reason_notes' => "Recorded {$movementType} of ₹{$amount} — Reason: {$reason}",
            ]);

            return $movement;
        });
    }

    /**
     * Get Real-Time Cash Drawer Status & Financial Breakdown
     */
    public function getCashDrawerStatus(int $storeId): array
    {
        $activeSession = PosSession::with(['posRegister', 'user'])
            ->where('store_id', $storeId)
            ->where('status', 'open')
            ->orderBy('id', 'desc')
            ->first();

        if (! $activeSession) {
            return [
                'has_active_session' => false,
                'opening_cash' => 0.00,
                'cash_sales' => 0.00,
                'cash_collections' => 0.00,
                'cash_supplier_payments' => 0.00,
                'cash_refunds' => 0.00,
                'cash_expenses' => 0.00,
                'manual_cash_in' => 0.00,
                'manual_cash_out' => 0.00,
                'expected_cash' => 0.00,
            ];
        }

        $sessionOpenedAt = $activeSession->opened_at;

        // Cash Sales from Invoices during session
        $invoiceIds = Invoice::where('store_id', $storeId)
            ->where('created_at', '>=', $sessionOpenedAt)
            ->where('status', '!=', 'cancelled')
            ->pluck('id');

        $cashSales = (float) InvoicePayment::whereIn('invoice_id', $invoiceIds)
            ->where('payment_method', 'cash')
            ->sum('amount');

        // Cash Customer Collections
        $cashCollections = (float) CustomerPayment::where('store_id', $storeId)
            ->where('payment_method', 'cash')
            ->where('created_at', '>=', $sessionOpenedAt)
            ->sum('amount');

        // Cash Supplier Payments
        $cashSupplierPayments = (float) SupplierPayment::where('payment_method', 'cash')
            ->where('created_at', '>=', $sessionOpenedAt)
            ->sum('amount');

        // Cash Refunds
        $cashRefunds = (float) CustomerRefund::where('store_id', $storeId)
            ->where('refund_method', 'cash')
            ->where('created_at', '>=', $sessionOpenedAt)
            ->sum('amount');

        // Cash Expenses
        $cashExpenses = (float) Expense::where('store_id', $storeId)
            ->where('payment_method', 'cash')
            ->where('created_at', '>=', $sessionOpenedAt)
            ->sum('amount');

        // Manual Cash-In / Cash-Out
        $manualCashIn = (float) PosRegisterCashMovement::where('pos_session_id', $activeSession->id)
            ->where('movement_type', 'cash_in')
            ->sum('amount');

        $manualCashOut = (float) PosRegisterCashMovement::where('pos_session_id', $activeSession->id)
            ->whereIn('movement_type', ['cash_out', 'drawer_drop'])
            ->sum('amount');

        $openingCash = (float) $activeSession->opening_cash;
        $expectedCash = round($openingCash + $cashSales + $cashCollections + $manualCashIn - $cashRefunds - $cashSupplierPayments - $cashExpenses - $manualCashOut, 2);

        return [
            'has_active_session' => true,
            'session' => $activeSession,
            'opening_cash' => $openingCash,
            'cash_sales' => $cashSales,
            'cash_collections' => $cashCollections,
            'cash_supplier_payments' => $cashSupplierPayments,
            'cash_refunds' => $cashRefunds,
            'cash_expenses' => $cashExpenses,
            'manual_cash_in' => $manualCashIn,
            'manual_cash_out' => $manualCashOut,
            'expected_cash' => $expectedCash,
        ];
    }

    /**
     * Get Comprehensive End-of-Day Financial Summary & Breakdown
     */
    public function getComprehensiveDayClosingSummary(int $storeId, string $closingDate): array
    {
        $store = Store::find($storeId);

        // 1. Invoices & Sales
        $invoices = Invoice::with(['customer', 'items', 'payments'])
            ->where('store_id', $storeId)
            ->whereDate('created_at', $closingDate)
            ->where('status', '!=', 'cancelled')
            ->get();

        $invoiceIds = $invoices->pluck('id');
        $payments = InvoicePayment::whereIn('invoice_id', $invoiceIds)->get();

        $grossSales = round((float) $invoices->sum(fn($i) => (float)$i->subtotal + (float)$i->total_tax), 2);
        $discountTotal = round((float) $invoices->sum('discount_amount'), 2);
        $taxTotal = round((float) $invoices->sum('total_tax'), 2);
        $netSales = round((float) $invoices->sum('grand_total'), 2);

        $salesCash = round((float) $payments->where('payment_method', 'cash')->sum('amount'), 2);
        $salesCard = round((float) $payments->where('payment_method', 'card')->sum('amount'), 2);
        $salesUpi = round((float) $payments->where('payment_method', 'upi')->sum('amount'), 2);
        $salesBank = round((float) $payments->where('payment_method', 'bank_transfer')->sum('amount'), 2);
        $salesOther = round((float) $payments->whereNotIn('payment_method', ['cash', 'card', 'upi', 'bank_transfer'])->sum('amount'), 2);
        $salesOtherDigital = round($salesBank + $salesOther, 2);
        $salesDigitalTotal = round($salesCard + $salesUpi + $salesOtherDigital, 2);

        $totalItemsSold = (int) \App\Models\InvoiceItem::whereIn('invoice_id', $invoiceIds)->sum('quantity');

        // Sales Returns
        $salesReturnsTotal = round((float) \App\Models\ReturnItem::whereIn('invoice_item_id', \App\Models\InvoiceItem::whereIn('invoice_id', $invoiceIds)->pluck('id'))->sum('subtotal'), 2);

        // 2. Customer Collections
        $collections = CustomerPayment::with(['customer', 'invoice', 'collector'])
            ->where('store_id', $storeId)
            ->whereDate('payment_date', $closingDate)
            ->get();

        $colCash = round((float) $collections->where('payment_method', 'cash')->sum('amount'), 2);
        $colCard = round((float) $collections->where('payment_method', 'card')->sum('amount'), 2);
        $colUpi = round((float) $collections->where('payment_method', 'upi')->sum('amount'), 2);
        $colBank = round((float) $collections->where('payment_method', 'bank_transfer')->sum('amount'), 2);
        $colOther = round((float) $collections->whereNotIn('payment_method', ['cash', 'card', 'upi', 'bank_transfer'])->sum('amount'), 2);
        $colTotal = round($colCash + $colCard + $colUpi + $colBank + $colOther, 2);

        // 3. Refunds
        $refunds = CustomerRefund::with(['customer', 'invoice', 'processor'])
            ->where('store_id', $storeId)
            ->whereDate('created_at', $closingDate)
            ->get();

        $refCash = round((float) $refunds->where('refund_method', 'cash')->sum('amount'), 2);
        $refCard = round((float) $refunds->where('refund_method', 'card')->sum('amount'), 2);
        $refUpi = round((float) $refunds->where('refund_method', 'upi')->sum('amount'), 2);
        $refStoreCredit = round((float) $refunds->where('refund_method', 'store_credit')->sum('amount'), 2);
        $refOther = round((float) $refunds->whereNotIn('refund_method', ['cash', 'card', 'upi', 'store_credit'])->sum('amount'), 2);
        $refTotal = round($refCash + $refCard + $refUpi + $refStoreCredit + $refOther, 2);

        // 4. Supplier Payments & Purchases
        $purchaseBills = \App\Models\PurchaseBill::where('store_id', $storeId)->whereDate('bill_date', $closingDate)->get();
        $supplierPayments = SupplierPayment::whereDate('payment_date', $closingDate)->get();
        $purchaseReturns = \App\Models\PurchaseReturn::where('store_id', $storeId)->whereDate('created_at', $closingDate)->get();

        $supCash = round((float) $supplierPayments->where('payment_method', 'cash')->sum('amount'), 2);
        $supCard = round((float) $supplierPayments->where('payment_method', 'card')->sum('amount'), 2);
        $supBank = round((float) $supplierPayments->where('payment_method', 'bank_transfer')->sum('amount'), 2);
        $supOther = round((float) $supplierPayments->whereNotIn('payment_method', ['cash', 'card', 'bank_transfer'])->sum('amount'), 2);
        $supTotal = round($supCash + $supCard + $supBank + $supOther, 2);

        // 5. Expenses & Category Breakdown
        $expenses = Expense::with(['category', 'creator'])
            ->where('store_id', $storeId)
            ->whereDate('expense_date', $closingDate)
            ->get();

        $expCash = round((float) $expenses->where('payment_method', 'cash')->sum('amount'), 2);
        $expCard = round((float) $expenses->where('payment_method', 'card')->sum('amount'), 2);
        $expUpi = round((float) $expenses->where('payment_method', 'upi')->sum('amount'), 2);
        $expBank = round((float) $expenses->where('payment_method', 'bank_transfer')->sum('amount'), 2);
        $expOther = round((float) $expenses->whereNotIn('payment_method', ['cash', 'card', 'upi', 'bank_transfer'])->sum('amount'), 2);
        $expTotal = round($expCash + $expCard + $expUpi + $expBank + $expOther, 2);

        $categoryBreakdown = [];
        $allCategories = \App\Models\ExpenseCategory::all();
        foreach ($allCategories as $cat) {
            $catExpenses = $expenses->where('expense_category_id', $cat->id);
            if ($catExpenses->count() > 0) {
                $categoryBreakdown[] = [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'count' => $catExpenses->count(),
                    'total_amount' => round((float) $catExpenses->sum('amount'), 2),
                ];
            }
        }
        $uncategorized = $expenses->whereNull('expense_category_id');
        if ($uncategorized->count() > 0) {
            $categoryBreakdown[] = [
                'id' => null,
                'name' => 'General / Uncategorized',
                'count' => $uncategorized->count(),
                'total_amount' => round((float) $uncategorized->sum('amount'), 2),
            ];
        }

        // 6. Cash Drawer Status
        $drawerStatus = $this->getCashDrawerStatus($storeId);

        // 7. Digital Reconciliation Matrix
        $matrix = [
            [
                'method' => 'Cash',
                'sales' => $salesCash,
                'collections' => $colCash,
                'refunds' => $refCash,
                'supplier_payments' => $supCash,
                'expenses' => $expCash,
                'net_movement' => round($salesCash + $colCash - $refCash - $supCash - $expCash, 2),
            ],
            [
                'method' => 'Card',
                'sales' => $salesCard,
                'collections' => $colCard,
                'refunds' => $refCard,
                'supplier_payments' => $supCard,
                'expenses' => $expCard,
                'net_movement' => round($salesCard + $colCard - $refCard - $supCard - $expCard, 2),
            ],
            [
                'method' => 'UPI',
                'sales' => $salesUpi,
                'collections' => $colUpi,
                'refunds' => $refUpi,
                'supplier_payments' => 0.00,
                'expenses' => $expUpi,
                'net_movement' => round($salesUpi + $colUpi - $refUpi - $expUpi, 2),
            ],
            [
                'method' => 'Bank / Other Digital',
                'sales' => round($salesBank + $salesOther, 2),
                'collections' => round($colBank + $colOther, 2),
                'refunds' => round($refOther + $refStoreCredit, 2),
                'supplier_payments' => round($supBank + $supOther, 2),
                'expenses' => round($expBank + $expOther, 2),
                'net_movement' => round(($salesBank + $salesOther + $colBank + $colOther) - ($refOther + $refStoreCredit + $supBank + $supOther + $expBank + $expOther), 2),
            ],
        ];

        // 8. High Level Financial Summary Card
        $highLevel = [
            'gross_sales' => $grossSales,
            'sales_returns' => $salesReturnsTotal,
            'net_sales' => $netSales,
            'customer_collections' => $colTotal,
            'refunds' => $refTotal,
            'supplier_payments' => $supTotal,
            'expenses' => $expTotal,
            'other_cash_movements' => round($drawerStatus['manual_cash_in'] - $drawerStatus['manual_cash_out'], 2),
            'net_cash_movement' => round($drawerStatus['expected_cash'] - $drawerStatus['opening_cash'], 2),
            'expected_closing_cash' => $drawerStatus['expected_cash'],
        ];

        // 9. Transaction Counts
        $counts = [
            'sales_count' => $invoices->count(),
            'sales_return_count' => \App\Models\ReturnItem::whereIn('invoice_item_id', \App\Models\InvoiceItem::whereIn('invoice_id', $invoiceIds)->pluck('id'))->count(),
            'customer_collection_count' => $collections->count(),
            'refund_count' => $refunds->count(),
            'purchase_bill_count' => $purchaseBills->count(),
            'supplier_payment_count' => $supplierPayments->count(),
            'expense_count' => $expenses->count(),
            'cash_adjustment_count' => PosRegisterCashMovement::where('pos_session_id', $drawerStatus['session']?->id ?? 0)->count(),
        ];

        // 10. Itemized Detail Lists
        $itemized = [
            'sales' => $invoices->map(fn($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'created_at' => $inv->created_at->toDateTimeString(),
                'customer_name' => $inv->customer?->name ?? 'Walk-in Customer',
                'payment_method' => $inv->payments->pluck('payment_method')->unique()->implode(', ') ?: 'cash',
                'subtotal' => (float) $inv->subtotal,
                'discount' => (float) $inv->discount_amount,
                'tax' => (float) $inv->total_tax,
                'grand_total' => (float) $inv->grand_total,
                'paid_amount' => (float) $inv->paid_amount,
            ]),
            'collections' => $collections->map(fn($c) => [
                'id' => $c->id,
                'payment_number' => $c->payment_number,
                'customer_name' => $c->customer?->name ?? 'N/A',
                'invoice_number' => $c->invoice?->invoice_number ?? 'Auto-Applied',
                'payment_method' => $c->payment_method,
                'amount' => (float) $c->amount,
                'payment_date' => $c->payment_date->toDateTimeString(),
            ]),
            'refunds' => $refunds->map(fn($r) => [
                'id' => $r->id,
                'refund_number' => $r->refund_number,
                'invoice_number' => $r->invoice?->invoice_number ?? 'N/A',
                'customer_name' => $r->customer?->name ?? 'Walk-in',
                'refund_method' => $r->refund_method,
                'amount' => (float) $r->amount,
                'reason' => $r->reason,
                'created_at' => $r->created_at->toDateTimeString(),
            ]),
            'expenses' => $expenses->map(fn($e) => [
                'id' => $e->id,
                'voucher_number' => $e->voucher_number ?? "EXP-{$e->id}",
                'category_name' => $e->category?->name ?? 'General',
                'description' => $e->description,
                'payment_method' => $e->payment_method,
                'amount' => (float) $e->amount,
                'expense_date' => $e->expense_date,
                'created_by' => $e->creator?->name ?? 'User',
            ]),
            'supplier_payments' => $supplierPayments->map(fn($sp) => [
                'id' => $sp->id,
                'payment_number' => $sp->payment_number ?? "SUP-PAY-{$sp->id}",
                'supplier_name' => $sp->supplier?->name ?? 'Supplier',
                'purchase_bill_number' => $sp->purchaseBill?->bill_number ?? 'Direct',
                'payment_method' => $sp->payment_method,
                'amount' => (float) $sp->amount,
                'payment_date' => $sp->payment_date,
            ]),
        ];

        return [
            'store' => $store,
            'closing_date' => $closingDate,
            'sales_summary' => [
                'gross_sales' => $grossSales,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'net_sales' => $netSales,
                'sales_returns_total' => $salesReturnsTotal,
                'cash_sales' => $salesCash,
                'card_sales' => $salesCard,
                'upi_sales' => $salesUpi,
                'other_digital_sales' => $salesOtherDigital,
                'total_digital_sales' => $salesDigitalTotal,
                'total_sales_count' => $invoices->count(),
                'total_items_sold' => $totalItemsSold,
            ],
            'customer_collections' => [
                'cash_collections' => $colCash,
                'card_collections' => $colCard,
                'upi_collections' => $colUpi,
                'other_digital_collections' => round($colBank + $colOther, 2),
                'total_collections' => $colTotal,
                'collections_count' => $collections->count(),
            ],
            'refunds_summary' => [
                'cash_refunds' => $refCash,
                'card_refunds' => $refCard,
                'upi_refunds' => $refUpi,
                'store_credit_refunds' => $refStoreCredit,
                'other_digital_refunds' => $refOther,
                'total_refunds' => $refTotal,
                'refunds_count' => $refunds->count(),
            ],
            'supplier_summary' => [
                'purchase_bills_count' => $purchaseBills->count(),
                'purchase_bills_total' => round((float)$purchaseBills->sum('grand_total'), 2),
                'cash_supplier_payments' => $supCash,
                'digital_supplier_payments' => round($supTotal - $supCash, 2),
                'total_supplier_payments' => $supTotal,
                'supplier_payments_count' => $supplierPayments->count(),
                'purchase_returns_count' => $purchaseReturns->count(),
                'purchase_returns_total' => round((float)$purchaseReturns->sum('total_return_amount'), 2),
            ],
            'expenses_summary' => [
                'cash_expenses' => $expCash,
                'digital_expenses' => round($expTotal - $expCash, 2),
                'total_expenses' => $expTotal,
                'expenses_count' => $expenses->count(),
                'category_breakdown' => $categoryBreakdown,
            ],
            'cash_drawer' => $drawerStatus,
            'digital_reconciliation_matrix' => $matrix,
            'high_level_financial_summary' => $highLevel,
            'transaction_counts' => $counts,
            'itemized_details' => $itemized,
        ];
    }

    /**
     * Perform End-Of-Day Day Closing & Snapshot
     */
    public function performDayClosing(array $data, User $user): DayClosing
    {
        $storeId = (int) ($data['store_id'] ?? 1);
        $closingDate = $data['closing_date'] ?? now()->toDateString();
        $actualCash = round((float) $data['actual_cash'], 2);
        $notes = isset($data['notes']) ? trim($data['notes']) : null;
        $denomination = $data['denomination_breakdown'] ?? null;

        // Fetch comprehensive daily summary to calculate system expected cash
        $summary = $this->getComprehensiveDayClosingSummary($storeId, $closingDate);
        $expectedCash = $summary['cash_drawer']['expected_cash'];
        $variance = round($actualCash - $expectedCash, 2);

        // Mandatory Discrepancy Explanation check when variance is not zero
        if ($variance != 0.00 && (empty($notes) || strlen($notes) < 3)) {
            throw new \InvalidArgumentException('Closing notes / discrepancy explanation is mandatory when cash variance is not zero.');
        }

        // Prevent Duplicate Day Closing for same store and closing_date
        $existingClosing = DayClosing::where('store_id', $storeId)
            ->where('closing_date', $closingDate)
            ->where('status', 'closed')
            ->first();

        if ($existingClosing) {
            throw new \InvalidArgumentException("Day Closing for Date {$closingDate} and Store #{$storeId} is already completed.");
        }

        return DB::transaction(function () use ($storeId, $closingDate, $actualCash, $expectedCash, $variance, $notes, $denomination, $summary, $user) {
            $closingNumber = 'CLO-'.date('Ymd', strtotime($closingDate)).'-'.str_pad((string) (DayClosing::count() + 1), 3, '0', STR_PAD_LEFT);

            $activeSession = PosSession::where('store_id', $storeId)
                ->where('status', 'open')
                ->orderBy('id', 'desc')
                ->first();

            $snapshot = array_merge($summary, [
                'denomination_breakdown' => $denomination,
                'closing_reconciliation' => [
                    'expected_cash' => $expectedCash,
                    'actual_cash' => $actualCash,
                    'variance' => $variance,
                    'notes' => $notes,
                ],
            ]);

            $dayClosing = DayClosing::create([
                'closing_number' => $closingNumber,
                'store_id' => $storeId,
                'pos_session_id' => $activeSession?->id,
                'closing_date' => $closingDate,
                'total_sales_cash' => $summary['sales_summary']['cash_sales'],
                'total_sales_card' => $summary['sales_summary']['card_sales'],
                'total_sales_upi' => $summary['sales_summary']['upi_sales'],
                'total_sales_bank' => $summary['sales_summary']['other_digital_sales'],
                'total_sales_other' => 0.00,
                'total_sales_grand' => $summary['sales_summary']['net_sales'],
                'total_collections_cash' => $summary['customer_collections']['cash_collections'],
                'total_collections_digital' => round($summary['customer_collections']['total_collections'] - $summary['customer_collections']['cash_collections'], 2),
                'total_collections_grand' => $summary['customer_collections']['total_collections'],
                'total_refunds_cash' => $summary['refunds_summary']['cash_refunds'],
                'total_refunds_digital' => round($summary['refunds_summary']['total_refunds'] - $summary['refunds_summary']['cash_refunds'], 2),
                'total_refunds_grand' => $summary['refunds_summary']['total_refunds'],
                'total_expenses' => $summary['expenses_summary']['total_expenses'],
                'opening_cash' => $summary['cash_drawer']['opening_cash'],
                'cash_received' => round($summary['sales_summary']['cash_sales'] + $summary['customer_collections']['cash_collections'] + $summary['cash_drawer']['manual_cash_in'], 2),
                'cash_paid_out' => round($summary['refunds_summary']['cash_refunds'] + $summary['expenses_summary']['cash_expenses'] + $summary['supplier_summary']['cash_supplier_payments'] + $summary['cash_drawer']['manual_cash_out'], 2),
                'expected_cash' => $expectedCash,
                'actual_cash' => $actualCash,
                'variance' => $variance,
                'notes' => $notes,
                'closed_by' => $user->id,
                'status' => 'closed',
                'snapshot_data' => $snapshot,
            ]);

            // Close active POS Session if present
            if ($activeSession) {
                $activeSession->update([
                    'status' => \App\Enums\PosSessionStatus::CLOSED,
                    'closed_at' => now(),
                    'closing_cash_system' => $expectedCash,
                    'closing_cash_actual' => $actualCash,
                    'cash_difference' => $variance,
                    'notes' => $notes ?? "Closed via Day Closing #{$closingNumber}",
                ]);
            }

            AuditLog::create([
                'audit_uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'store_id' => $storeId,
                'module' => 'PAYMENTS',
                'event_type' => 'DAY_CLOSING_COMPLETED',
                'auditable_type' => DayClosing::class,
                'auditable_id' => $dayClosing->id,
                'after_state' => json_encode($dayClosing->toArray()),
                'status' => 'SUCCESS',
                'reason_notes' => "Completed Day Closing for Date {$closingDate} — Expected: ₹{$expectedCash}, Actual: ₹{$actualCash}, Variance: ₹{$variance}",
            ]);

            return $dayClosing->load(['store', 'closer']);
        });
    }

    /**
     * Reopen a Closed Day
     */
    public function reopenDayClosing(int $id, string $reason, User $user): DayClosing
    {
        $dayClosing = DayClosing::findOrFail($id);

        if ($dayClosing->status === 'reopened') {
            throw new \InvalidArgumentException("Day Closing #{$dayClosing->closing_number} is already reopened.");
        }

        return DB::transaction(function () use ($dayClosing, $reason, $user) {
            $dayClosing->update([
                'status' => 'reopened',
                'reopened_by' => $user->id,
                'reopened_at' => now(),
                'reopen_reason' => $reason,
            ]);

            AuditLog::create([
                'audit_uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'store_id' => $dayClosing->store_id,
                'module' => 'PAYMENTS',
                'event_type' => 'DAY_CLOSING_REOPENED',
                'auditable_type' => DayClosing::class,
                'auditable_id' => $dayClosing->id,
                'after_state' => json_encode($dayClosing->toArray()),
                'status' => 'SUCCESS',
                'reason_notes' => "Reopened Day Closing #{$dayClosing->closing_number} — Reason: {$reason}",
            ]);

            return $dayClosing->load(['store', 'closer', 'reopener']);
        });
    }
}
