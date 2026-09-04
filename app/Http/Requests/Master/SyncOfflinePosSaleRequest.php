<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class SyncOfflinePosSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // If 'sales' key is present in payload (batch request)
        if ($this->has('sales') && is_array($this->input('sales'))) {
            return [
                'sales' => ['required', 'array', 'min:1'],
                'sales.*.client_trans_uuid' => ['required', 'string', 'max:36'],
                'sales.*.store_id' => ['nullable', 'integer', 'exists:stores,id'],
                'sales.*.pos_session_id' => ['nullable', 'integer', 'exists:pos_sessions,id'],
                'sales.*.customer_id' => ['nullable', 'integer', 'exists:customers,id'],
                'sales.*.items' => ['required', 'array', 'min:1'],
                'sales.*.items.*.quantity' => ['required', 'integer', 'gt:0'],
                'sales.*.items.*.product_variant_size_id' => ['nullable', 'integer', 'exists:product_variant_sizes,id'],
                'sales.*.items.*.sku' => ['nullable', 'string', 'max:100'],
                'sales.*.items.*.unit_price' => ['nullable', 'numeric', 'gte:0'],
                'sales.*.items.*.discount_amount' => ['nullable', 'numeric', 'gte:0'],
                'sales.*.payments' => ['nullable', 'array'],
            ];
        }

        // Single transaction object payload
        return [
            'client_trans_uuid' => ['required', 'string', 'max:36'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'pos_session_id' => ['nullable', 'integer', 'exists:pos_sessions,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'gt:0'],
            'items.*.product_variant_size_id' => ['nullable', 'integer', 'exists:product_variant_sizes,id'],
            'items.*.sku' => ['nullable', 'string', 'max:100'],
            'items.*.unit_price' => ['nullable', 'numeric', 'gte:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'gte:0'],
            'payments' => ['nullable', 'array'],
        ];
    }
}
