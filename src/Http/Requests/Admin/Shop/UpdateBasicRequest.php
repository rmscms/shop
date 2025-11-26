<?php

namespace RMS\Shop\Http\Requests\Admin\Shop;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBasicRequest extends FormRequest
{
    public function authorize(): bool 
    { 
        return auth('admin')->check() || auth()->check(); 
    }

    public function rules(): array
    {
        return [
            'name' => ['required','string','max:190'],
            'slug' => ['required','string','max:190'],
            'sku'  => ['nullable','string','max:190'],
            'category_id' => ['nullable','integer'],
            'brand_id' => ['required','integer','exists:shop_brands,id'],
            'active' => ['nullable','boolean'],
            'point_per_unit' => ['nullable','integer','min:0'],
            'cost_cny' => ['nullable','numeric'],
            'sale_price_cny' => ['nullable','numeric'],
            'discount_type' => ['nullable','in:percent,amount'],
            'discount_value' => ['nullable','numeric','min:0'],
            'stock_qty' => ['nullable','integer','min:0'],
            'short_desc' => ['nullable','string'],
            'description' => ['nullable','string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => trans('shop.product.name'),
            'slug' => trans('shop.product.slug'),
            'sku' => trans('shop.product.sku'),
            'category_id' => trans('shop.product.category'),
            'brand_id' => trans('shop.product.brand'),
            'cost_cny' => trans('shop.product.cost_cny'),
            'sale_price_cny' => trans('shop.product.sale_price_cny'),
            'stock_qty' => trans('shop.combinations.stock'),
            'point_per_unit' => trans('shop.product.point_per_unit'),
        ];
    }
}

