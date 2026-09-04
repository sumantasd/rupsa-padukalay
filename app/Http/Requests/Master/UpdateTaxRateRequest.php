<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaxRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'rate_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'cgst_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sgst_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'igst_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
