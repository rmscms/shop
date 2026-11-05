<?php

namespace RMS\Shop\Ai;

use RMS\Shop\Models\Product;

/**
 * Task "slug_fa": replace an English product slug with a Persian one.
 */
class SlugTask
{
    public const KEY = 'slug_fa';

    /** Persian letters, ZWNJ, digits and hyphens only. */
    public static function clean(string $slug): string
    {
        $slug = trim($slug);
        $slug = preg_replace('/[\s_]+/u', '-', $slug);
        $slug = preg_replace('/[^\x{0600}-\x{06FF}\x{200C}0-9a-zA-Z-]+/u', '', $slug);
        $slug = preg_replace('/-{2,}/', '-', $slug);

        return mb_substr(trim($slug, '-'), 0, 190);
    }

    public static function hasPersian(string $slug): bool
    {
        return preg_match('/[\x{0600}-\x{06FF}]/u', $slug) === 1;
    }

    /** @return string|null error message, null when the slug can be used */
    public static function validate(Product $product, string $slug): ?string
    {
        if ($slug === '' || ! self::hasPersian($slug)) {
            return 'اسلاگ باید حداقل یک حرف فارسی داشته باشد.';
        }
        if ($slug === $product->slug) {
            return 'اسلاگ با مقدار فعلی یکی است.';
        }
        if (Product::query()->where('slug', $slug)->whereKeyNot($product->id)->exists()) {
            return 'این اسلاگ برای محصول دیگری استفاده شده است.';
        }

        return null;
    }
}
