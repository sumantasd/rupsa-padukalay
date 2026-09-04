<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class RecordCashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pos_register_id' => ['nullable', 'integer', 'exists:pos_registers,id'],
            'pos_session_id' => ['nullable', 'integer', 'exists:pos_sessions,id'],
            'movement_type' => ['required', 'string', 'in:cash_in,cash_out,drawer_drop'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'client_trans_uuid' => ['nullable', 'string', 'max:36'],
        ];
    }
}
