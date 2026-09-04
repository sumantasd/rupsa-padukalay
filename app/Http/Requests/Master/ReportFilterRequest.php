<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'cashier_id' => ['nullable', 'integer', 'exists:users,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'payment_status' => ['nullable', 'string', 'in:paid,partial,unpaid'],
            'status' => ['nullable', 'string', 'in:completed,returned,partially_returned,cancelled'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'group_by' => ['nullable', 'string', 'in:day,week,month,year'],
            'sort_by' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
