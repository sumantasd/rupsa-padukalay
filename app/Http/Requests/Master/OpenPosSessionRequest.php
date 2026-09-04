<?php

namespace App\Http\Requests\Master;

use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;

class OpenPosSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => [
                'nullable',
                'integer',
                'exists:stores,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $store = Store::find($value);
                        if (! $store || ! $store->is_active) {
                            $fail('The selected store is inactive or invalid.');
                        }
                    }
                },
            ],

            'opening_cash' => ['required', 'numeric', 'min:0'],
            'client_session_uuid' => ['nullable', 'string', 'max:36', 'unique:pos_sessions,client_session_uuid'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
