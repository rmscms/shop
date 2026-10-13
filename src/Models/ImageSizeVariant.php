<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ImageSizeVariant extends Model
{
    protected $table = 'image_size_variants';

    public $timestamps = true;

    protected $fillable = [
        'image_id',
        'image_size_id',
        'path',
        'width',
        'height',
        'size_bytes',
    ];

    protected $casts = [
        'image_id' => 'integer',
        'image_size_id' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'size_bytes' => 'integer',
    ];

    /**
     * Get the image library entry
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(ImageLibrary::class, 'image_id');
    }

    /**
     * Get the image size definition
     */
    public function size(): BelongsTo
    {
        return $this->belongsTo(ImageSize::class, 'image_size_id');
    }

    /**
     * Get full URL for the variant
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
