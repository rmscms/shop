<?php

namespace RMS\Shop\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class OrderApplyDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'string', 'max:64'],
            'amount_display' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

