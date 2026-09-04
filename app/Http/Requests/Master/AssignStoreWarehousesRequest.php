<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class AssignStoreWarehousesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_ids' => ['required', 'array'],
            'warehouse_ids.*' => ['exists:warehouses,id'],
        ];
    }
}
