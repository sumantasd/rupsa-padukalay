<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $code = $this->code ?: 'SUP-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
        $balances = method_exists($this->resource, 'calculateBalances') ? $this->calculateBalances() : [];

        $totalPurchases = isset($this->total_purchases_amount) ? (float) $this->total_purchases_amount : ($balances['total_purchases'] ?? 0.00);
        $totalPaid = isset($this->total_paid_amount) ? (float) $this->total_paid_amount : ($balances['total_paid'] ?? 0.00);
        $totalReturns = isset($this->total_returns_amount) ? (float) $this->total_returns_amount : ($balances['total_returns'] ?? 0.00);
        $currentDue = isset($this->current_due_amount) ? (float) $this->current_due_amount : ($balances['current_due'] ?? (float) $this->current_balance);
        $supplierCredit = isset($this->supplier_credit_amount) ? (float) $this->supplier_credit_amount : ($balances['supplier_credit'] ?? 0.00);

        return [
            'id' => $this->id,
            'code' => $code,
            'name' => $this->name,
            'company_name' => $this->company_name,
            'gstin' => $this->gstin,
            'pan' => $this->pan,
            'phone' => $this->phone,
            'alternate_mobile' => $this->alternate_mobile,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state ?: 'West Bengal',
            'pincode' => $this->pincode,
            'current_balance' => (float) $this->current_balance,
            'opening_balance' => (float) $this->opening_balance,
            'opening_balance_type' => $this->opening_balance_type ?: 'payable',
            'payment_terms' => $this->payment_terms ?: 'Net 30 Days',
            'credit_limit' => (float) $this->credit_limit,
            'notes' => $this->notes,
            'is_active' => (bool) ($this->is_active ?? true),
            'total_purchases_amount' => $totalPurchases,
            'total_paid_amount' => $totalPaid,
            'total_returns_amount' => $totalReturns,
            'current_due_amount' => $currentDue,
            'supplier_credit_amount' => $supplierCredit,
            'last_purchase_date' => $this->last_purchase_date ? (is_string($this->last_purchase_date) ? $this->last_purchase_date : $this->last_purchase_date->toIso8601String()) : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
