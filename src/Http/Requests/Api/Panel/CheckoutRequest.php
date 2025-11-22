<?php

namespace RMS\Shop\Http\Requests\Api\Panel;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address_id' => ['required', 'integer', 'min:1'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'payment_driver' => ['required', 'string', 'max:100'],
        ];
    }
}

