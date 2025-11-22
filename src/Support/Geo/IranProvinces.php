<?php

namespace RMS\Shop\Support\Geo;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class IranProvinces
{
    protected static ?Collection $cache = null;

    protected static function collection(): Collection
    {
        if (static::$cache instanceof Collection) {
            return static::$cache;
        }

        $items = collect(config('shop.geo.iran_provinces', []))
            ->mapWithKeys(function (array $province) {
                $id = (int) ($province['id'] ?? 0);

                return [$id => [
                    'id' => $id,
                    'name' => $province['name'] ?? null,
                    'code' => $province['code'] ?? null,
                    'slug' => $province['slug'] ?? null,
                ]];
            })
            ->filter(fn ($province) => !empty($province['name']) && $province['id'] > 0);

        return static::$cache = $items;
    }

    public static function all(): array
    {
        return static::collection()->values()->all();
    }

    public static function ids(): array
    {
        return static::collection()->keys()->all();
    }

    public static function find(int|string|null $id): ?array
    {
        if ($id === null) {
            return null;
        }

        return static::collection()->get((int) $id);
    }

    public static function label(int|string|null $id): ?string
    {
        return static::find($id)['name'] ?? null;
    }

    public static function toSelect(): array
    {
        return static::collection()
            ->map(fn ($province) => [
                'id' => $province['id'],
                'name' => $province['name'],
                'code' => $province['code'],
                'slug' => $province['slug'],
            ])
            ->values()
            ->all();
    }

    public static function matchByName(?string $name): ?array
    {
        if (!$name) {
            return null;
        }

        $needle = Str::lower(trim($name));

        return static::collection()
            ->first(fn ($province) => Str::lower($province['name']) === $needle);
    }
}

