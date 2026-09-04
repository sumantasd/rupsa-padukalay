<?php

namespace App\Http\Requests\Master;

use App\Models\Color;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'color_id' => [
                'required',
                'exists:colors,id',
                function ($attribute, $value, $fail) {
                    $color = Color::find($value);
                    if ($color && isset($color->is_active) && ! $color->is_active) {
                        $fail('The selected colour is inactive.');
                    }
                },
            ],
        ];
    }
}
