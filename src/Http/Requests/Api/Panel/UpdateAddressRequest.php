<?php

namespace RMS\Shop\Http\Requests\Api\Panel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use RMS\Shop\Support\AddressesConfig;
use RMS\Shop\Support\Geo\IranProvinces;

class UpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_default')) {
            $this->merge([
                'is_default' => filter_var($this->input('is_default'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if ($this->filled('province_id')) {
            $this->merge([
                'province_id' => (int) $this->input('province_id'),
            ]);
        }
    }

    public function rules(): array
    {
        $provinceRule = Rule::in(IranProvinces::ids());

        return [
            'full_name' => $this->updateRule('full_name', ['string', 'max:190']),
            'mobile' => $this->updateRule('mobile', ['string', 'max:50']),
            'phone' => $this->updateRule('phone', ['string', 'max:50']),
            'province_id' => $this->updateRule('province_id', ['integer', $provinceRule]),
            'province' => ['nullable', 'string', 'max:190'],
            'city' => $this->updateRule('city', ['string', 'max:190']),
            'postal_code' => $this->updateRule('postal_code', ['string', 'max:20']),
            'address_line' => $this->updateRule('address_line', ['string', 'max:500']),
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    protected function updateRule(string $field, array $rules): array
    {
        $required = AddressesConfig::isFieldRequired($field);

        return array_merge([$required ? 'required' : 'nullable'], $rules);
    }
}

