<?php

namespace App\Http\Requests\Master;

use App\Models\Store;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;

class ReceivePurchaseOrderRequest extends FormRequest
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
                'exists:stores,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $store = Store::find($value);
                        if ($store && ! $store->is_active) {
                            $fail('The selected store is inactive.');
                        }
                    }
                },
            ],
            'warehouse_id' => [
                'nullable',
                'exists:warehouses,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $warehouse = Warehouse::find($value);
                        if ($warehouse && ! $warehouse->is_active) {
                            $fail('The selected warehouse is inactive.');
                        }
                    }
                },
            ],
            'stock_location_id' => ['nullable', 'exists:stock_locations,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['nullable', 'integer', 'exists:purchase_order_items,id'],
            'items.*.sku' => ['nullable', 'string', 'exists:product_variant_sizes,sku'],
            'items.*.product_variant_size_id' => ['nullable', 'integer', 'exists:product_variant_sizes,id'],
            'items.*.quantity_received' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            foreach ($items as $index => $item) {
                if (empty($item['purchase_order_item_id']) && empty($item['sku']) && empty($item['product_variant_size_id'])) {
                    $validator->errors()->add("items.{$index}", 'Each item must specify a purchase_order_item_id, SKU, or product_variant_size_id.');
                }
            }
        });
    }
}
