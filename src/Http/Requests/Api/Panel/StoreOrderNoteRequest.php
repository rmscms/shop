<?php

namespace RMS\Shop\Http\Requests\Api\Panel;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = (int) config('shop.panel_api.orders.note_max_length', 3000);

        return [
            'note_text' => ['required', 'string', 'max:' . max(1, $max)],
        ];
    }
}

