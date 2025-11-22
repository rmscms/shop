<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $table = 'currencies';

    protected $fillable = [
        'code', 'name', 'symbol', 'decimals', 'is_base'
    ];

    protected $casts = [
        'is_base' => 'boolean',
        'decimals' => 'integer'
    ];
}
