<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $table = 'currencies';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code', 'name', 'symbol', 'decimals', 'is_base', 'active',
    ];

    protected $casts = [
        'is_base' => 'boolean',
        'active' => 'boolean',
        'decimals' => 'integer',
    ];
}
