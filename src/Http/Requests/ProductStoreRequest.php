<?php

namespace RMS\Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductStoreRequest extends FormRequest
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
            'price' => ['nullable','numeric'],
            'sale_price' => ['nullable','numeric'],
            'cost_cny' => ['nullable','numeric'],
            'sale_price_cny' => ['nullable','numeric'],
            'stock_qty' => ['nullable','integer','min:0'],
            'point_per_unit' => ['nullable','integer','min:0'],
            'discount_type' => ['nullable','in:percent,amount'],
            'discount_value' => ['nullable','numeric','min:0'],
            'active' => ['nullable','boolean'],
            'short_desc' => ['nullable','string'],
            'description' => ['nullable','string'],
            'attributes_json' => ['nullable','string'],
            'combinations_json' => ['nullable','string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => trans('shop::shop.product.name'),
            'slug' => trans('shop::shop.product.slug'),
            'sku' => trans('shop::shop.product.sku'),
            'category_id' => trans('shop::shop.product.category'),
            'cost_cny' => trans('shop::shop.product.cost_cny'),
            'sale_price_cny' => trans('shop::shop.product.sale_price_cny'),
            'stock_qty' => trans('shop::shop.combinations.stock'),
            'point_per_unit' => trans('shop::shop.product.point_per_unit'),
        ];
    }
}

