<?php

namespace App\Http\Requests\Master;

use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'expense_category_id' => ['sometimes', 'required', 'integer', 'exists:expense_categories,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'pos_session_id' => ['nullable', 'integer', 'exists:pos_sessions,id'],
            'amount' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'payment_method' => ['sometimes', 'required', 'string', 'in:cash,upi,card,bank_transfer'],
            'description' => ['nullable', 'string', 'max:500'],
            'voucher_number' => ['nullable', 'string', 'max:50'],
            'expense_date' => ['sometimes', 'required', 'date'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $storeId = $this->input('store_id');
            $warehouseId = $this->input('warehouse_id');

            if ($storeId && $warehouseId) {
                $warehouse = Warehouse::with('stores')->find($warehouseId);
                if ($warehouse && $warehouse->stores()->exists()) {
                    $assignedStoreIds = $warehouse->stores()->pluck('stores.id');
                    if (! $assignedStoreIds->contains((int) $storeId)) {
                        $validator->errors()->add('warehouse_id', 'The specified warehouse does not belong to the selected store.');
                    }
                }
            }
        });
    }
}
