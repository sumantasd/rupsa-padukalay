<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $paymentMethodVal = is_object($this->payment_method) && property_exists($this->payment_method, 'value')
            ? $this->payment_method->value
            : (string) $this->payment_method;

        return [
            'id' => $this->id,
            'expense_number' => $this->expense_number || ("EXP-" . str_pad($this->id, 6, '0', STR_PAD_LEFT)),
            'expense_category_id' => $this->expense_category_id,
            'expense_category_name' => $this->category?->name ?? 'Uncategorized',
            'payee_name' => $this->payee_name,
            'store_id' => $this->store_id,
            'store_name' => $this->store?->name ?? 'All Outlets',
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->warehouse?->name,
            'pos_session_id' => $this->pos_session_id,
            'amount' => (float) $this->amount,
            'payment_method' => $paymentMethodVal,
            'status' => $this->status ?? 'paid',
            'description' => $this->description,
            'voucher_number' => $this->voucher_number,
            'attachment_url' => $this->attachment_url,
            'notes' => $this->notes,
            'expense_date' => $this->expense_date ? (is_string($this->expense_date) ? $this->expense_date : $this->expense_date->format('Y-m-d')) : null,
            'created_by' => $this->created_by,
            'created_by_name' => $this->creator?->name ?? 'System User',
            'created_at' => $this->created_at ? $this->created_at->toIso8601String() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toIso8601String() : null,
        ];
    }
}
