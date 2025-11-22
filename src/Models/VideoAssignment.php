<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class VideoAssignment extends Model
{
    protected $fillable = ['video_id', 'assignable_id', 'assignable_type', 'is_main', 'sort'];

    public function video()
    {
        return $this->belongsTo(VideoLibrary::class, 'video_id');
    }

    public function assignable()
    {
        return $this->morphTo();
    }
}

