<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class AssignPosRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
