<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Category extends Model
{
    protected $table = 'categories';

    protected $fillable = [
        'name', 'slug', 'parent_id', 'active', 'sort'
    ];

    protected $casts = [
        'active' => 'boolean',
        'sort' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(function (): void {
            static::flushTreeCache();
        });

        static::deleted(function (): void {
            static::flushTreeCache();
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort')->orderBy('name');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('parent_id')->orderBy('sort')->orderBy('name');
    }

    public static function flushTreeCache(): void
    {
        Cache::forget('shop:categories:tree:active');
        Cache::forget('shop:categories:tree:all');
    }

    public static function ensureDefault(): self
    {
        $defaultSlug = (string) config('shop.categories.default_slug', 'uncategorized');
        $defaultName = (string) config('shop.categories.default_name', 'دسته‌بندی نشده');

        /** @var self $category */
        $category = static::query()->firstOrCreate(
            ['slug' => $defaultSlug],
            [
                'name' => $defaultName,
                'parent_id' => null,
                'active' => true,
                'sort' => 0,
            ]
        );

        return $category;
    }
}
