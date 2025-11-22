<?php

namespace RMS\Shop\Http\Requests\Api\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCurrencyRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'base_code' => ['required', 'string', 'max:10', Rule::exists('currencies', 'code')],
            'quote_code' => ['required', 'string', 'max:10', Rule::exists('currencies', 'code'), 'different:base_code'],
            'sell_rate' => ['required', 'numeric', 'min:0.000001'],
            'effective_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}

