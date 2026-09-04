<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $loyaltyPts = (int) ($this->loyaltyAccount?->available_points ?? $this->reward_points ?? 0);
        $spent = (float) ($this->total_spent_amount ?? 0.00);
        $invPaid = (float) ($this->invoice_paid_amount ?? 0.00);
        $dirPaid = (float) ($this->direct_paid_amount ?? 0.00);

        // Fallback for single model load without withSum if needed
        if ($this->relationLoaded('invoices') && ! isset($this->attributes['invoice_paid_amount'])) {
            $invPaid = (float) $this->invoices->whereNotIn('status', ['cancelled', 'CANCELLED'])->sum('paid_amount');
        }

        $paid = round($invPaid + $dirPaid, 2);
        $due = max(0.0, round($spent - $paid, 2));

        return [
            'id' => $this->id,
            'mobile_number' => $this->mobile_number,
            'phone' => $this->mobile_number,
            'name' => $this->name,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'pincode' => $this->pincode,
            'reward_points' => $loyaltyPts,
            'loyalty_points' => $loyaltyPts,
            'points_balance' => $loyaltyPts,
            'total_purchases_count' => (int) ($this->total_purchases_count ?? 0),
            'invoices_count' => (int) ($this->total_purchases_count ?? 0),
            'total_spent_amount' => $spent,
            'total_purchase_amount' => $spent,
            'paid_amount' => $paid,
            'outstanding_balance' => $due,
            'outstanding' => $due,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
