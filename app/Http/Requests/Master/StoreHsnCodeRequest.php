<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreHsnCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'unique:hsn_codes,code'],
            'description' => ['nullable', 'string'],
            'default_gst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
