<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStoreCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'not_in:0'],
            'transaction_type' => ['nullable', 'string', 'in:issue_refund,issue_adjustment,payment_used,reversal'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
