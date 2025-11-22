<?php

namespace RMS\Shop\Http\Requests\Api\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $transitions = $this->statusTransitions();
        $allowed = array_keys($transitions);

        return [
            'status' => [
                'required',
                'string',
                Rule::in($allowed),
            ],
        ];
    }

    public function allowedTransitionsFor(string $status): array
    {
        $transitions = $this->statusTransitions();
        return $transitions[$status] ?? [];
    }

    protected function statusTransitions(): array
    {
        return config('shop.panel_api.orders.customer_status_transitions', []);
    }
}

