<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class ClosePosRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'closing_cash_actual' => ['required', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
