<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreColorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', 'unique:colors,name'],
            'code' => ['required', 'string', 'max:20', 'unique:colors,code'],
            'hex_code' => ['nullable', 'string', 'max:10'],
        ];
    }
}
