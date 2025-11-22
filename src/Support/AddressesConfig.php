<?php

namespace RMS\Shop\Support;

use Illuminate\Support\Facades\Cache;

class AddressesConfig
{
    protected const CACHE_KEY = 'shop:addresses:config';

    protected static ?array $config = null;

    protected static function load(): array
    {
        if (static::$config !== null) {
            return static::$config;
        }

        $ttl = (int) config('shop.addresses.cache_ttl', 3600);

        if ($ttl > 0) {
            return static::$config = Cache::remember(static::CACHE_KEY, $ttl, fn () => static::generate());
        }

        return static::$config = static::generate();
    }

    protected static function generate(): array
    {
        $fields = config('shop.addresses.required_fields', []);

        return [
            'required_fields' => array_map(fn ($value) => (bool) $value, $fields),
        ];
    }

    public static function flush(): void
    {
        static::$config = null;
        Cache::forget(static::CACHE_KEY);
    }

    public static function requiredFields(): array
    {
        return static::load()['required_fields'] ?? [];
    }

    public static function isFieldRequired(string $field): bool
    {
        return (bool) (static::requiredFields()[$field] ?? false);
    }
}

