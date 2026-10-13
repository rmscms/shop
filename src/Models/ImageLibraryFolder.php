<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImageLibraryFolder extends Model
{
    protected $table = 'image_library_folders';

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'path',
        'sort',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'sort' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort')
            ->orderBy('name');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ImageLibrary::class, 'folder_id');
    }

    public function descendants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->with('descendants');
    }

    public function isEmpty(): bool
    {
        if ($this->images()->exists()) {
            return false;
        }

        return !$this->children()->exists();
    }
}
