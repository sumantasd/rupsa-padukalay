<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id') ?? $this->route('customer');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'mobile_number' => ['nullable', 'string', 'max:20', "unique:customers,mobile_number,{$id}"],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
