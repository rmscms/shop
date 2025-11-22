<?php

namespace RMS\Shop\Http\Requests\Api\Panel;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stock_qty' => ['nullable', 'integer', 'min:0'],
            'combinations' => ['nullable', 'array'],
            'combinations.*.id' => ['required', 'integer', 'min:1'],
            'combinations.*.stock_qty' => ['required', 'integer', 'min:0'],
        ];
    }
}

