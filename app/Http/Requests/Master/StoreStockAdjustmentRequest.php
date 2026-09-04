<?php

namespace App\Http\Requests\Master;

use App\Models\ProductVariantSize;
use App\Models\Store;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockAdjustmentRequest extends FormRequest
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
            'reason' => ['required', 'in:opening_stock,physical_count,damage,theft,return_to_vendor,other,Physical Stock Count,Damaged,Lost,Found,Correction,Stock Correction'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sku' => ['nullable', 'string', 'exists:product_variant_sizes,sku'],
            'items.*.product_variant_size_id' => ['nullable', 'integer', 'exists:product_variant_sizes,id'],
            'items.*.stock_location_id' => ['nullable', 'integer', 'exists:stock_locations,id'],
            'items.*.type' => ['nullable', 'in:add,deduct,set'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.quantity_adjusted' => ['nullable', 'integer'],
            'items.*.new_quantity' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (! $this->input('store_id') && ! $this->input('warehouse_id')) {
                $validator->errors()->add('store_id', 'Stock adjustment must specify either a store or a warehouse.');
            }

            $items = $this->input('items', []);
            foreach ($items as $index => $item) {
                if (empty($item['sku']) && empty($item['product_variant_size_id'])) {
                    $validator->errors()->add("items.{$index}.sku", 'Each adjustment item must specify a valid SKU or product_variant_size_id.');
                }
            }
        });
    }
}
