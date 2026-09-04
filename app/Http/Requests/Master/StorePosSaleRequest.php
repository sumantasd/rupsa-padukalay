<?php

namespace App\Http\Requests\Master;

use App\Models\Customer;
use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;

class StorePosSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => [
                'nullable',
                'integer',
                'exists:stores,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $store = Store::find($value);
                        if (! $store || ! $store->is_active) {
                            $fail('The selected store is inactive or invalid.');
                        }
                    }
                },
            ],
            'pos_session_id' => ['nullable', 'integer', 'exists:pos_sessions,id'],
            'customer_id' => [
                'nullable',
                'integer',
                'exists:customers,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $customer = Customer::find($value);
                        if (! $customer) {
                            $fail('The selected customer is invalid.');
                        }
                    }
                },
            ],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'client_trans_uuid' => ['nullable', 'string', 'max:36', 'unique:invoices,client_trans_uuid'],
            'payment_method' => ['nullable', 'string', 'in:cash,upi,card,store_credit,other'],
            'transaction_reference' => ['nullable', 'string'],
            'payments' => ['nullable', 'array', 'min:1'],
            'payments.*.payment_method' => ['required_with:payments', 'string', 'in:cash,upi,card,store_credit,other'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'gt:0'],
            'payments.*.transaction_reference' => ['nullable', 'string', 'max:100'],
            'payments.*.notes' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sku' => ['nullable', 'string', 'exists:product_variant_sizes,sku'],
            'items.*.product_variant_size_id' => ['nullable', 'integer', 'exists:product_variant_sizes,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            foreach ($items as $index => $item) {
                if (empty($item['sku']) && empty($item['product_variant_size_id'])) {
                    $validator->errors()->add("items.{$index}", 'Each line item must specify a SKU or product_variant_size_id.');
                }
            }

            if ($this->has('payments') && is_array($this->input('payments'))) {
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
