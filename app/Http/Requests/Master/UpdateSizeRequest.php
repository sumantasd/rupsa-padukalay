<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sizeId = $this->route('id') ?? $this->route('size');
        $system = $this->input('size_system', 'UK/IND');

        return [
            'size_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('sizes')->where(function ($query) use ($system) {
                    return $query->where('size_system', $system);
                })->ignore($sizeId),
            ],
            'size_system' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
