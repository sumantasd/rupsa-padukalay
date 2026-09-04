<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalesReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_return_uuid' => ['nullable', 'string', 'max:36', 'unique:returns,client_return_uuid'],
            'refund_mode' => ['nullable', 'string', 'in:cash,upi,store_credit,exchange_offset'],
            'reason' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.invoice_item_id' => ['nullable', 'integer', 'exists:invoice_items,id'],
            'items.*.product_variant_size_id' => ['nullable', 'integer', 'exists:product_variant_sizes,id'],
            'items.*.sku' => ['nullable', 'string', 'exists:product_variant_sizes,sku'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.restock_condition' => ['nullable', 'string', 'in:resellable,damaged'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            foreach ($items as $index => $item) {
                if (empty($item['invoice_item_id']) && empty($item['product_variant_size_id']) && empty($item['sku'])) {
                    $validator->errors()->add("items.{$index}", 'Each return item must specify an invoice_item_id, product_variant_size_id, or sku.');
                }
            }
        });
    }
}
