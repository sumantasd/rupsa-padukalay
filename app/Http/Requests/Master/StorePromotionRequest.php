<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StorePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50', 'unique:promotions,code'],
            'promotion_type' => ['required', 'string', 'in:percentage,fixed_amount,bogo'],
            'discount_scope' => ['required', 'string', 'in:cart,product,category,brand'],
            'discount_value' => ['nullable', 'numeric', 'gte:0'],
            'buy_quantity' => ['nullable', 'integer', 'min:1'],
            'get_quantity' => ['nullable', 'integer', 'min:1'],
            'get_discount_percentage' => ['nullable', 'numeric', 'between:0,100'],
            'min_cart_amount' => ['nullable', 'numeric', 'gte:0'],
            'max_discount_amount' => ['nullable', 'numeric', 'gte:0'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'allow_stacking' => ['nullable', 'boolean'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'targets' => ['nullable', 'array'],
            'targets.*.target_type' => ['required_with:targets', 'string', 'in:product,category,brand'],
            'targets.*.target_id' => ['required_with:targets', 'integer'],
        ];
    }
}
