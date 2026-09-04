<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateColorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $colorId = $this->route('id') ?? $this->route('color');

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('colors', 'name')->ignore($colorId),
            ],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('colors', 'code')->ignore($colorId),
            ],
            'hex_code' => ['nullable', 'string', 'max:10'],
        ];
    }
}
