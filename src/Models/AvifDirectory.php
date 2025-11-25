<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class AvifDirectory extends Model
{
    protected $table = 'shop_avif_directories';

    protected $fillable = [
        'path',
        'type',
        'active',
        'is_default',
    ];

    protected $casts = [
        'active' => 'boolean',
        'is_default' => 'boolean',
    ];
}

