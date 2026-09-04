<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class OpenPosRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'opening_cash' => ['required', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:500'],
            'client_session_uuid' => ['nullable', 'string', 'max:36'],
        ];
    }
}
