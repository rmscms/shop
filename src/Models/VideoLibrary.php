<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class VideoLibrary extends Model
{
    protected $table = 'video_library';
    
    protected $fillable = ['filename', 'title', 'path', 'hls_path', 'poster_path', 'duration_seconds', 'size_bytes', 'folder_id'];

    protected $appends = ['url', 'hls_url', 'poster_url', 'is_transcoded', 'display_name'];

    protected $casts = [
        'folder_id' => 'integer',
        'duration_seconds' => 'integer',
        'size_bytes' => 'integer',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(VideoLibraryFolder::class, 'folder_id');
    }

    public function assignments()
    {
        return $this->hasMany(VideoAssignment::class, 'video_id');
    }

    public function getUrlAttribute(): ?string
    {
        return $this->path ? Storage::disk('public')->url($this->path) : null;
    }

    public function getHlsUrlAttribute(): ?string
    {
        return $this->hls_path ? Storage::disk('public')->url($this->hls_path) : null;
    }

    public function getPosterUrlAttribute(): ?string
    {
        return $this->poster_path ? Storage::disk('public')->url($this->poster_path) : null;
    }

    public function getIsTranscodedAttribute(): bool
    {
        return !empty($this->hls_path);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->title ?: $this->filename;
    }
}

