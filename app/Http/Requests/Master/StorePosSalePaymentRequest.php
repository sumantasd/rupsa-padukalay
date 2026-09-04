<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StorePosSalePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pos_session_id' => ['nullable', 'integer', 'exists:pos_sessions,id'],
            'payment_method' => ['nullable', 'string', 'in:cash,upi,card,store_credit,other'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'transaction_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:255'],
            'payments' => ['nullable', 'array', 'min:1'],
            'payments.*.payment_method' => ['required_with:payments', 'string', 'in:cash,upi,card,store_credit,other'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'gt:0'],
            'payments.*.transaction_reference' => ['nullable', 'string', 'max:100'],
            'payments.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $hasSingle = $this->has('amount') || $this->has('payment_method');
            $hasArray = $this->has('payments') && is_array($this->input('payments')) && count($this->input('payments')) > 0;

            if (! $hasSingle && ! $hasArray) {
                $validator->errors()->add('payments', 'Payment details must be provided as either single payment parameters or a payments array.');
            }

            if ($this->has('amount') && (float) $this->input('amount') <= 0) {
                $validator->errors()->add('amount', 'Payment amount must be greater than zero.');
            }

            if ($hasArray) {
                foreach ($this->input('payments', []) as $index => $payment) {
                    $amt = (float) ($payment['amount'] ?? 0);
                    if ($amt <= 0) {
                        $validator->errors()->add("payments.{$index}.amount", 'Payment amount must be greater than zero.');
                    }
                }
            }
        });
    }
}
