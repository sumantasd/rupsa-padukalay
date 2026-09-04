<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreExchangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_exchange_uuid' => ['nullable', 'string', 'max:36', 'unique:returns,client_return_uuid'],
            'reason' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['nullable', 'string', 'in:cash,upi,card,store_credit,other'],
            'refund_mode' => ['nullable', 'string', 'in:cash,upi,store_credit,exchange_offset'],
            'returned_items' => ['required', 'array', 'min:1'],
            'returned_items.*.invoice_item_id' => ['nullable', 'integer', 'exists:invoice_items,id'],
            'returned_items.*.product_variant_size_id' => ['nullable', 'integer', 'exists:product_variant_sizes,id'],
            'returned_items.*.sku' => ['nullable', 'string', 'exists:product_variant_sizes,sku'],
            'returned_items.*.quantity' => ['required', 'integer', 'min:1'],
            'returned_items.*.restock_condition' => ['nullable', 'string', 'in:resellable,damaged'],
            'replacement_items' => ['required', 'array', 'min:1'],
            'replacement_items.*.product_variant_size_id' => ['nullable', 'integer', 'exists:product_variant_sizes,id'],
            'replacement_items.*.sku' => ['nullable', 'string', 'exists:product_variant_sizes,sku'],
            'replacement_items.*.quantity' => ['required', 'integer', 'min:1'],
            'replacement_items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $returnedItems = $this->input('returned_items', []);
            foreach ($returnedItems as $index => $item) {
                if (empty($item['invoice_item_id']) && empty($item['product_variant_size_id']) && empty($item['sku'])) {
                    $validator->errors()->add("returned_items.{$index}", 'Each returned item must specify an invoice_item_id, product_variant_size_id, or sku.');
                }
            }

            $replacementItems = $this->input('replacement_items', []);
            foreach ($replacementItems as $index => $item) {
                if (empty($item['product_variant_size_id']) && empty($item['sku'])) {
                    $validator->errors()->add("replacement_items.{$index}", 'Each replacement item must specify a product_variant_size_id or sku.');
                }
            }
        });
    }
}
