<?php

namespace App\Services;

use App\Enums\PosSessionStatus;
use App\Models\Expense;
use App\Models\InvoicePayment;
use App\Models\PosRegister;
use App\Models\PosRegisterCashMovement;
use App\Models\PosSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PosRegisterService
{
    public function openRegister(PosRegister $register, User $user, float $openingCash, ?string $notes = null, ?string $clientUuid = null): PosSession
    {
        if (! $register->is_active) {
            throw new \RuntimeException("POS Register '{$register->name}' is inactive.");
        }

        // Check active session for this register
        $activeRegisterSession = PosSession::where('pos_register_id', $register->id)
            ->where('status', PosSessionStatus::OPEN->value)
            ->first();

        if ($activeRegisterSession) {
            throw new \RuntimeException("POS Register '{$register->name}' already has an active open session.");
        }

        // Check active session for user
        $activeUserSession = PosSession::where('user_id', $user->id)
            ->where('status', PosSessionStatus::OPEN->value)
            ->first();

        if ($activeUserSession) {
            throw new \RuntimeException("User '{$user->name}' already has an active open POS session.");
        }

        if (! empty($clientUuid)) {
            $existing = PosSession::where('client_session_uuid', $clientUuid)->first();
            if ($existing) {
                return $existing;
            }
        }

        return PosSession::create([
            'client_session_uuid' => $clientUuid,
            'pos_register_id' => $register->id,
            'store_id' => $register->store_id,
            'user_id' => $user->id,
            'opened_at' => now(),
            'opening_cash' => $openingCash,
            'closing_cash_system' => $openingCash,
            'status' => PosSessionStatus::OPEN->value,
            'notes' => $notes,
        ]);
    }

    public function calculateDrawerSummary(PosSession $session): array
    {
        $openingCash = (float) $session->opening_cash;

        // Cash Sales from invoice_payments
        $cashSales = (float) InvoicePayment::whereHas('invoice', function ($q) use ($session) {
            $q->where('pos_session_id', $session->id);
        })
            ->where(function ($q) {
                $q->where('payment_method', 'cash')
                    ->orWhere('payment_method', 'CASH');
            })
            ->sum('amount');

        // Cash Movements
        $cashIn = (float) PosRegisterCashMovement::where('pos_session_id', $session->id)
            ->where('movement_type', 'cash_in')
            ->sum('amount');

        $cashOut = (float) PosRegisterCashMovement::where('pos_session_id', $session->id)
            ->where('movement_type', 'cash_out')
            ->sum('amount');

        $drawerDrops = (float) PosRegisterCashMovement::where('pos_session_id', $session->id)
            ->where('movement_type', 'drawer_drop')
            ->sum('amount');

        $cashExpenses = (float) Expense::where('pos_session_id', $session->id)
            ->where(function ($q) {
                $q->where('payment_method', 'cash')
                    ->orWhere('payment_method', 'CASH');
            })
            ->sum('amount');

        $cashRefunds = 0.0; // Currently return refunds outside cash drops or handled via store credit

        $expectedDrawerBalance = round(
            $openingCash + $cashSales + $cashIn - $cashOut - $drawerDrops - $cashRefunds - $cashExpenses,
            2
        );

        $statusStr = is_object($session->status) && property_exists($session->status, 'value')
            ? $session->status->value
            : (string) $session->status;

        $actualCount = $statusStr === 'closed' ? (float) $session->closing_cash_actual : null;
        $diff = $statusStr === 'closed' ? (float) $session->cash_difference : null;

        $varianceStatus = null;
        if ($diff !== null) {
            if ($diff > 0.01) {
                $varianceStatus = 'over';
            } elseif ($diff < -0.01) {
                $varianceStatus = 'short';
            } else {
                $varianceStatus = 'exact';
            }
        }

        return [
            'session_id' => $session->id,
            'pos_register_id' => $session->pos_register_id,
            'store_id' => $session->store_id,
            'cashier_id' => $session->user_id,
            'status' => $statusStr,
            'opened_at' => $session->opened_at ? $session->opened_at->toIso8601String() : null,
            'closed_at' => $session->closed_at ? $session->closed_at->toIso8601String() : null,
            'opening_cash' => $openingCash,
            'cash_sales' => $cashSales,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'drawer_drops' => $drawerDrops,
            'cash_refunds' => $cashRefunds,
            'cash_expenses' => $cashExpenses,
            'expected_drawer_balance' => $expectedDrawerBalance,
            'closing_cash_actual' => $actualCount,
            'cash_difference' => $diff,
            'variance_status' => $varianceStatus,
        ];
    }

    public function recordCashMovement(PosSession $session, User $user, string $movementType, float $amount, string $reason, ?string $referenceNumber = null, ?string $clientUuid = null): PosRegisterCashMovement
    {
        $statusStr = is_object($session->status) && property_exists($session->status, 'value')
            ? $session->status->value
            : (string) $session->status;

        if ($statusStr !== 'open') {
            throw new \RuntimeException('Cannot record cash movements on a closed POS session.');
        }

        if (! empty($clientUuid)) {
            $existing = PosRegisterCashMovement::where('client_trans_uuid', $clientUuid)->first();
            if ($existing) {
                return $existing;
            }
        }

        $summary = $this->calculateDrawerSummary($session);
        $currentBalance = $summary['expected_drawer_balance'];

        if (in_array($movementType, ['cash_out', 'drawer_drop']) && ($currentBalance - $amount < 0.0)) {
            throw new \RuntimeException("Insufficient drawer cash balance ({$currentBalance}) for requested {$movementType} amount ({$amount}).");
        }

        return PosRegisterCashMovement::create([
            'pos_session_id' => $session->id,
            'pos_register_id' => $session->pos_register_id,
            'user_id' => $user->id,
            'movement_type' => $movementType,
            'amount' => $amount,
            'reason' => $reason,
            'reference_number' => $referenceNumber,
            'client_trans_uuid' => $clientUuid,
        ]);
    }

    public function closeRegister(PosSession $session, float $actualCashCount, ?string $notes = null): PosSession
    {
        $statusStr = is_object($session->status) && property_exists($session->status, 'value')
            ? $session->status->value
            : (string) $session->status;

        if ($statusStr !== 'open') {
            throw new \RuntimeException('POS session is already closed.');
        }

        $summary = $this->calculateDrawerSummary($session);
        $expectedBalance = $summary['expected_drawer_balance'];
        $cashDiff = round($actualCashCount - $expectedBalance, 2);

        $session->update([
            'closing_cash_system' => $expectedBalance,
            'closing_cash_actual' => $actualCashCount,
            'cash_difference' => $cashDiff,
            'status' => PosSessionStatus::CLOSED->value,
            'closed_at' => now(),
            'notes' => $notes ? trim($session->notes." | Close Note: {$notes}") : $session->notes,
        ]);

        return $session;
    }
}
