<?php

namespace RMS\Shop\Support\Config;

class AddressFieldConfig
{
    protected static ?array $cache = null;

    protected static function all(): array
    {
        if (static::$cache !== null) {
            return static::$cache;
        }

        $fields = config('shop.addresses.fields', []);

        return static::$cache = collect($fields)
            ->mapWithKeys(function ($value, $key) {
                $required = (bool) (is_array($value) ? ($value['required'] ?? false) : $value);

                return [$key => ['required' => $required]];
            })
            ->all();
    }

    public static function isRequired(string $field): bool
    {
        return (bool) (static::all()[$field]['required'] ?? false);
    }
}

