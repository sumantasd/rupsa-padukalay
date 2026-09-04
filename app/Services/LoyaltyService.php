<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyRule;
use App\Models\LoyaltyTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    public function getOrCreateAccount(Customer $customer): LoyaltyAccount
    {
        return LoyaltyAccount::firstOrCreate(
            ['customer_id' => $customer->id],
            [
                'available_points' => 0,
                'lifetime_earned_points' => 0,
                'lifetime_redeemed_points' => 0,
                'status' => 'active',
            ]
        );
    }

    public function getActiveRule(): LoyaltyRule
    {
        $rule = LoyaltyRule::where('is_active', true)->first();

        if (! $rule) {
            $rule = LoyaltyRule::create([
                'rule_name' => 'Standard Footwear Loyalty',
                'earn_rate_amount' => 100.00,
                'earn_points' => 1,
                'redeem_point_value' => 1.00,
                'min_qualifying_amount' => 0.00,
                'min_redemption_points' => 10,
                'is_active' => true,
            ]);
        }

        return $rule;
    }

    public function earnPoints(Customer $customer, Invoice $invoice, ?User $user = null): ?LoyaltyTransaction
    {
        if ($customer->name === 'Walk-in Customer' || ! $customer->mobile_number) {
            return null;
        }

        return DB::transaction(function () use ($customer, $invoice, $user) {
            // Idempotency check: prevent duplicate points for same invoice
            $existing = LoyaltyTransaction::where('reference_type', 'invoice')
                ->where('reference_id', $invoice->id)
                ->where('transaction_type', 'earn')
                ->first();

            if ($existing) {
                return $existing;
            }

            if (! empty($invoice->client_trans_uuid)) {
                $uuidCheck = LoyaltyTransaction::where('client_trans_uuid', $invoice->client_trans_uuid)
                    ->where('transaction_type', 'earn')
                    ->first();
                if ($uuidCheck) {
                    return $uuidCheck;
                }
            }

            $rule = $this->getActiveRule();

            if ($invoice->grand_total < $rule->min_qualifying_amount) {
                return null;
            }

            $earnedPoints = (int) floor(($invoice->grand_total / (float) $rule->earn_rate_amount) * $rule->earn_points);

            if ($earnedPoints <= 0) {
                return null;
            }

            $account = LoyaltyAccount::lockForUpdate()->firstOrCreate(
                ['customer_id' => $customer->id],
                [
                    'available_points' => 0,
                    'lifetime_earned_points' => 0,
                    'lifetime_redeemed_points' => 0,
                    'status' => 'active',
                ]
            );

            if ($account->status !== 'active') {
                return null;
            }

            $before = (int) $account->available_points;
            $after = $before + $earnedPoints;

            $account->available_points = $after;
            $account->lifetime_earned_points += $earnedPoints;
            $account->save();

            // Also update customer model for backwards compatibility
            $customer->increment('reward_points', $earnedPoints);

            return LoyaltyTransaction::create([
                'customer_id' => $customer->id,
                'store_id' => $invoice->store_id,
                'transaction_type' => 'earn',
                'points' => $earnedPoints,
                'points_balance_before' => $before,
                'points_balance_after' => $after,
                'reference_type' => 'invoice',
                'reference_id' => $invoice->id,
                'client_trans_uuid' => $invoice->client_trans_uuid,
                'performed_by' => $user?->id ?? $invoice->created_by,
                'notes' => "Earned {$earnedPoints} points from invoice #{$invoice->invoice_number}",
            ]);
        });
    }

    public function redeemPoints(Customer $customer, int $points, ?Invoice $invoice = null, ?User $user = null, ?int $storeId = null, ?string $notes = null): array
    {
        if ($points <= 0) {
            throw new \InvalidArgumentException('Requested redemption points must be greater than zero.');
        }

        return DB::transaction(function () use ($customer, $points, $invoice, $user, $storeId, $notes) {
            $rule = $this->getActiveRule();

            if ($points < $rule->min_redemption_points) {
                throw new \InvalidArgumentException("Minimum redemption threshold is {$rule->min_redemption_points} points.");
            }

            if ($rule->max_redemption_points && $points > $rule->max_redemption_points) {
                throw new \InvalidArgumentException("Maximum redemption limit is {$rule->max_redemption_points} points.");
            }

            $account = LoyaltyAccount::lockForUpdate()->firstOrCreate(
                ['customer_id' => $customer->id],
                [
                    'available_points' => 0,
                    'lifetime_earned_points' => 0,
                    'lifetime_redeemed_points' => 0,
                    'status' => 'active',
                ]
            );

            if ($account->status !== 'active') {
                throw new \RuntimeException('Customer loyalty account is not active.');
            }

            if ($account->available_points < $points) {
                throw new \RuntimeException("Insufficient loyalty points. Available: {$account->available_points}, Requested: {$points}");
            }

            if ($invoice) {
                $existing = LoyaltyTransaction::where('reference_type', 'invoice')
                    ->where('reference_id', $invoice->id)
                    ->where('transaction_type', 'redeem')
                    ->first();

                if ($existing) {
                    return [
                        'transaction' => $existing,
                        'discount_value' => (float) ($existing->points * $rule->redeem_point_value),
                    ];
                }
            }

            $before = (int) $account->available_points;
            $after = $before - $points;

            $account->available_points = $after;
            $account->lifetime_redeemed_points += $points;
            $account->save();

            // Sync legacy reward_points column
            $customer->reward_points = $after;
            $customer->save();

            $discountValue = round($points * (float) $rule->redeem_point_value, 2);

            $trans = LoyaltyTransaction::create([
                'customer_id' => $customer->id,
                'store_id' => $storeId ?? $invoice?->store_id,
                'transaction_type' => 'redeem',
                'points' => -$points,
                'points_balance_before' => $before,
                'points_balance_after' => $after,
                'reference_type' => $invoice ? 'invoice' : 'manual_redemption',
                'reference_id' => $invoice?->id,
                'client_trans_uuid' => $invoice?->client_trans_uuid,
                'performed_by' => $user?->id,
                'notes' => $notes ?? "Redeemed {$points} points for ₹{$discountValue} discount.",
            ]);

            return [
                'transaction' => $trans,
                'discount_value' => $discountValue,
            ];
        });
    }
}
