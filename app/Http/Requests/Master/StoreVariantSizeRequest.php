<?php

namespace App\Http\Requests\Master;

use App\Models\Size;
use Illuminate\Foundation\Http\FormRequest;

class StoreVariantSizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'size_id' => [
                'required',
                'exists:sizes,id',
                function ($attribute, $value, $fail) {
                    $size = Size::find($value);
                    if ($size && isset($size->is_active) && ! $size->is_active) {
                        $fail('The selected size is inactive.');
                    }
                },
            ],
            'sku' => ['nullable', 'string', 'max:100', 'unique:product_variant_sizes,sku'],
            'barcode' => ['nullable', 'string', 'max:100', 'unique:product_variant_sizes,barcode'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'mrp' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
