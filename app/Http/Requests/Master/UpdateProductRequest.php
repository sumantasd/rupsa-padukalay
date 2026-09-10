<?php

namespace App\Http\Requests\Master;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CmsSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('id') ?? $this->route('product');
        $categoryRequired = CmsSetting::getSetting('product_field_category', '0') === '1';

        return [
            'article_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('products', 'article_number')->ignore($productId),
            ],
            'name' => ['nullable', 'string', 'max:150'],
            'brand_id' => [
                'nullable',
                'exists:brands,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $brand = Brand::find($value);
                        if ($brand && ! $brand->is_active) {
                            $fail('The selected brand is inactive.');
                        }
                    }
                },
            ],
            'category_id' => [
                $categoryRequired ? 'required' : 'nullable',
                'exists:categories,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $category = Category::find($value);
                        if ($category && ! $category->is_active) {
                            $fail('The selected category is inactive.');
                        }
                    }
                },
            ],
            'hsn_code_id' => ['nullable', 'exists:hsn_codes,id'],
            'size_chart_id' => ['nullable', 'exists:size_charts,id'],
            'gender' => ['nullable', 'string', 'in:men,women,boys,girls,unisex'],
            'upper_material' => ['nullable', 'string', 'max:100'],
            'sole_material' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'is_visible_on_web' => ['nullable', 'boolean'],
        ];
    }
}
