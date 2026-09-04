<?php

namespace App\Http\Requests\Master;

use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = (int) ($this->route('id') ?? $this->route('product'));

        return [
            'image_path' => ['required_without_all:image,file', 'nullable', 'string', 'max:255'],
            'image' => ['required_without_all:image_path,file', 'nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'file' => ['required_without_all:image_path,image', 'nullable', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'product_variant_id' => [
                'nullable',
                'exists:product_variants,id',
                function ($attribute, $value, $fail) use ($productId) {
                    if ($value) {
                        $variant = ProductVariant::find($value);
                        if ($variant && (int) $variant->product_id !== $productId) {
                            $fail('The selected variant does not belong to this product.');
                        }
                    }
                },
            ],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
