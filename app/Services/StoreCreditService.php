<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\StoreCreditAccount;
use App\Models\StoreCreditTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StoreCreditService
{
    public function getOrCreateAccount(Customer $customer): StoreCreditAccount
    {
        return StoreCreditAccount::firstOrCreate(
            ['customer_id' => $customer->id],
            [
                'current_balance' => 0.00,
                'total_issued' => 0.00,
                'total_used' => 0.00,
                'status' => 'active',
            ]
        );
    }

    public function issueOrAdjustCredit(
        Customer $customer,
        float $amount,
        string $type = 'issue_adjustment',
        ?User $user = null,
        ?int $storeId = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $clientUuid = null,
        ?string $notes = null
    ): StoreCreditTransaction {
        if ($amount == 0.0) {
            throw new \InvalidArgumentException('Store credit amount must not be zero.');
        }

        return DB::transaction(function () use ($customer, $amount, $type, $user, $storeId, $referenceType, $referenceId, $clientUuid, $notes) {
            if (! empty($clientUuid)) {
                $existing = StoreCreditTransaction::where('client_trans_uuid', $clientUuid)->first();
                if ($existing) {
                    return $existing;
                }
            }

            if ($referenceType && $referenceId && $type === 'issue_refund') {
                $refCheck = StoreCreditTransaction::where('reference_type', $referenceType)
                    ->where('reference_id', $referenceId)
                    ->where('transaction_type', 'issue_refund')
                    ->first();
                if ($refCheck) {
                    return $refCheck;
                }
            }

            $account = StoreCreditAccount::lockForUpdate()->firstOrCreate(
                ['customer_id' => $customer->id],
                [
                    'current_balance' => 0.00,
                    'total_issued' => 0.00,
                    'total_used' => 0.00,
                    'status' => 'active',
                ]
            );

            if ($account->status !== 'active') {
                throw new \RuntimeException('Customer store credit account is not active.');
            }

            $before = (float) $account->current_balance;
            $after = round($before + $amount, 2);

            if ($after < 0.0) {
                throw new \RuntimeException("Insufficient store credit balance. Current: ₹{$before}, Requested: ₹".abs($amount));
            }

            $account->current_balance = $after;
            if ($amount > 0) {
                $account->total_issued = round((float) $account->total_issued + $amount, 2);
            } else {
                $account->total_used = round((float) $account->total_used + abs($amount), 2);
            }
            $account->save();

            $creditTx = StoreCreditTransaction::create([
                'customer_id' => $customer->id,
                'store_id' => $storeId,
                'transaction_type' => $type,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => $referenceType ?? 'manual_adjustment',
                'reference_id' => $referenceId,
                'client_trans_uuid' => $clientUuid,
                'performed_by' => $user?->id,
                'notes' => $notes ?? 'Store credit balance adjustment',
            ]);

            app(\App\Services\AuditService::class)->logEvent([
                'user_id' => $user?->id,
                'store_id' => $storeId,
                'module' => 'store_credit',
                'event_type' => $amount > 0 ? 'credit_issued' : 'credit_redeemed',
                'auditable_type' => StoreCreditTransaction::class,
                'auditable_id' => $creditTx->id,
                'client_trans_uuid' => $clientUuid,
                'before_state' => ['balance' => $before],
                'after_state' => ['balance' => $after, 'amount' => $amount],
                'reason_notes' => $notes ?? 'Store credit balance transaction',
            ]);

            return $creditTx;
        });
    }
}
