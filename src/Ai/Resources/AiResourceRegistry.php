<?php

namespace RMS\Shop\Ai\Resources;

/**
 * Resources an agent may propose changes for. Add a class here (or list it in
 * config('shop.ai.resources')) to open a new area, e.g. blog posts.
 */
class AiResourceRegistry
{
    /** @var array<string, AiResource>|null */
    protected static ?array $cache = null;

    /** @return array<string, AiResource> */
    public static function all(): array
    {
        if (static::$cache === null) {
            $classes = array_merge(
                [ProductAiResource::class, CategoryAiResource::class, BrandAiResource::class],
                (array) config('shop.ai.resources', [])
            );
            static::$cache = [];
            foreach ($classes as $class) {
                $resource = new $class();
                static::$cache[$resource->key()] = $resource;
            }
        }

        return static::$cache;
    }

    public static function get(string $key): ?AiResource
    {
        return static::all()[$key] ?? null;
    }
}
