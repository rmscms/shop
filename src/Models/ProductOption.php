<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOption extends Model
{
    protected $table = 'product_options';

    protected $fillable = [
        'product_id', 'name', 'sort'
    ];

    protected $casts = [
        'sort' => 'integer',
    ];
}
