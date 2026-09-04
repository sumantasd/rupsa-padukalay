<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHsnCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $hsnId = $this->route('id') ?? $this->route('hsn_code');

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('hsn_codes', 'code')->ignore($hsnId),
            ],
            'description' => ['nullable', 'string'],
            'default_gst_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
