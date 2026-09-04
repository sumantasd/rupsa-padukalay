<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class ResolvePosSyncConflictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:reprocess,force_override,dismiss'],
            'resolution_notes' => ['nullable', 'string', 'max:500'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'pos_session_id' => ['nullable', 'integer', 'exists:pos_sessions,id'],
        ];
    }
}
