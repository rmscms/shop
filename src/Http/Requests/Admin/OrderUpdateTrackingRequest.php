<?php

namespace RMS\Shop\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class OrderUpdateTrackingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'tracking_code' => ['nullable', 'string', 'max:191'],
            'tracking_url' => ['nullable', 'url', 'max:500'],
        ];
    }
}

