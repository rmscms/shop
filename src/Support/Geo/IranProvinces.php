<?php

namespace RMS\Shop\Support\Geo;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Iranian provinces from package JSON (no DB table).
 * Path: resources/data/iran-provinces.json
 */
class IranProvinces
{
    protected static ?Collection $cache = null;

    public static function jsonPath(): string
    {
        $configured = config('shop.geo.iran_provinces_path');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return dirname(__DIR__, 3).DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'iran-provinces.json';
    }

    protected static function rawList(): array
    {
        // Legacy: published config may still ship the full array.
        $fromConfig = config('shop.geo.iran_provinces');
        if (is_array($fromConfig) && $fromConfig !== []) {
            return $fromConfig;
        }

        $path = static::jsonPath();
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    protected static function collection(): Collection
    {
        if (static::$cache instanceof Collection) {
            return static::$cache;
        }

        $items = collect(static::rawList())
            ->mapWithKeys(function ($province) {
                if (!is_array($province)) {
                    return [];
                }

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

    /** Clear in-memory cache (tests / config swap). */
    public static function flush(): void
    {
        static::$cache = null;
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
        if ($id === null || $id === '') {
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
            ->first(fn ($province) => Str::lower((string) $province['name']) === $needle);
    }
}
