<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoLibraryFolder extends Model
{
    protected $table = 'video_library_folders';

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

    public function videos(): HasMany
    {
        return $this->hasMany(VideoLibrary::class, 'folder_id');
    }

    public function isEmpty(): bool
    {
        if ($this->videos()->exists()) {
            return false;
        }

        return ! $this->children()->exists();
    }
}
