<?php

namespace RMS\Shop\Http\Requests\Admin\Shop;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePricingRequest extends FormRequest
{
    public function authorize(): bool 
    { 
        return auth('admin')->check() || auth()->check(); 
    }

    public function rules(): array
    {
        return [
            'price' => ['nullable','numeric'],
            'sale_price' => ['nullable','numeric'],
            'cost_cny' => ['nullable','numeric'],
            'sale_price_cny' => ['nullable','numeric'],
            'discount_type' => ['nullable','in:percent,amount'],
            'discount_value' => ['nullable','numeric','min:0'],
            'stock_qty' => ['nullable','integer','min:0'],
            'point_per_unit' => ['nullable','integer','min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'price' => trans('shop.product.price'),
            'sale_price' => trans('shop.product.sale_price'),
            'cost_cny' => trans('shop.product.cost_cny'),
            'sale_price_cny' => trans('shop.product.sale_price_cny'),
            'discount_type' => trans('shop.product.discount_type'),
            'discount_value' => trans('shop.product.discount_value'),
        ];
    }
}

