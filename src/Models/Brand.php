<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Brand extends Model
{
    protected $table = 'shop_brands';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo_path',
        'is_active',
        'sort',
        'meta_title',
        'meta_description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Brand $brand) {
            if (empty($brand->slug) && !empty($brand->name)) {
                $brand->slug = Str::slug($brand->name);
            }

            if (empty($brand->slug)) {
                $brand->slug = 'brand-'.Str::random(6);
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'brand_id');
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (empty($this->logo_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }
}

