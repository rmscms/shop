<?php

namespace RMS\Shop\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class OrderApplyPointsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'points' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

