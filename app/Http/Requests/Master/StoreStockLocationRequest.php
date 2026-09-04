<?php

namespace App\Http\Requests\Master;

use App\Models\StockLocation;
use App\Models\Store;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;

class StoreStockLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $storeId = $this->input('store_id');
        $warehouseId = $this->input('warehouse_id');

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
            'code' => [
                'required',
                'string',
                'max:50',
                function ($attribute, $value, $fail) use ($storeId, $warehouseId) {
                    if (! $storeId && ! $warehouseId) {
                        $fail('Stock location must be assigned to either a store or a warehouse.');
                        return;
                    }

                    $query = StockLocation::where('code', trim($value));
                    if ($storeId) {
                        $query->where('store_id', $storeId);
                    } else {
                        $query->whereNull('store_id');
                    }
                    if ($warehouseId) {
                        $query->where('warehouse_id', $warehouseId);
                    } else {
                        $query->whereNull('warehouse_id');
                    }

                    if ($query->exists()) {
                        $fail('A stock location with this code already exists in the selected store/warehouse scope.');
                    }
                },
            ],
            'name' => ['required', 'string', 'max:100'],
        ];
    }
}
