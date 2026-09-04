<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class RedeemLoyaltyPointsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'points' => ['required', 'integer', 'min:1'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
