<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImageSize extends Model
{
    protected $table = 'image_sizes';

    public $timestamps = true;

    protected $fillable = [
        'name',
        'slug',
        'width',
        'height',
        'quality',
        'active',
        'sort',
    ];

    protected $casts = [
        'width' => 'integer',
        'height' => 'integer',
        'quality' => 'integer',
        'active' => 'boolean',
        'sort' => 'integer',
    ];

    /**
     * Get all variants for this size
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ImageSizeVariant::class, 'image_size_id');
    }

    /**
     * Get active image sizes ordered by sort
     */
    public static function getActiveSizes()
    {
        return static::where('active', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }
}
