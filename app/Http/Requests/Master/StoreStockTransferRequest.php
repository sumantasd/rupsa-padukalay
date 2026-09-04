<?php

namespace App\Http\Requests\Master;

use App\Models\Store;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_store_id' => [
                'nullable',
                'exists:stores,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $store = Store::find($value);
                        if ($store && ! $store->is_active) {
                            $fail('The selected source store is inactive.');
                        }
                    }
                },
            ],
            'from_warehouse_id' => [
                'nullable',
                'exists:warehouses,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $warehouse = Warehouse::find($value);
                        if ($warehouse && ! $warehouse->is_active) {
                            $fail('The selected source warehouse is inactive.');
                        }
                    }
                },
            ],
            'to_store_id' => [
                'nullable',
                'exists:stores,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $store = Store::find($value);
                        if ($store && ! $store->is_active) {
                            $fail('The selected destination store is inactive.');
                        }
                    }
                },
            ],
            'to_warehouse_id' => [
                'nullable',
                'exists:warehouses,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $warehouse = Warehouse::find($value);
                        if ($warehouse && ! $warehouse->is_active) {
                            $fail('The selected destination warehouse is inactive.');
                        }
                    }
                },
            ],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sku' => ['nullable', 'string', 'exists:product_variant_sizes,sku'],
            'items.*.product_variant_size_id' => ['nullable', 'integer', 'exists:product_variant_sizes,id'],
            'items.*.from_stock_location_id' => ['nullable', 'integer', 'exists:stock_locations,id'],
            'items.*.to_stock_location_id' => ['nullable', 'integer', 'exists:stock_locations,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $fromStore = (int) $this->input('from_store_id', 0);
            $fromWh = (int) $this->input('from_warehouse_id', 0);
            $toStore = (int) $this->input('to_store_id', 0);
            $toWh = (int) $this->input('to_warehouse_id', 0);

            if ($fromStore === 0 && $fromWh === 0) {
                $validator->errors()->add('from_store_id', 'Transfer must specify a valid source store or warehouse.');
            }

            if ($toStore === 0 && $toWh === 0) {
                $validator->errors()->add('to_store_id', 'Transfer must specify a valid destination store or warehouse.');
            }

            if ($fromStore === $toStore && $fromWh === $toWh && $fromStore > 0) {
                $items = $this->input('items', []);
                foreach ($items as $index => $item) {
                    $fromLoc = (int) ($item['from_stock_location_id'] ?? 0);
                    $toLoc = (int) ($item['to_stock_location_id'] ?? 0);
                    if ($fromLoc === $toLoc) {
                        $validator->errors()->add("items.{$index}", 'Source and destination locations cannot be identical.');
                    }
                }
            }

            $items = $this->input('items', []);
            foreach ($items as $index => $item) {
                if (empty($item['sku']) && empty($item['product_variant_size_id'])) {
                    $validator->errors()->add("items.{$index}.sku", 'Each transfer item must specify a valid SKU or product_variant_size_id.');
                }
            }
        });
    }
}
