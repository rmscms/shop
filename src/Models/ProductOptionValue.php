<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class ProductOptionValue extends Model
{
    protected $table = 'product_option_values';

    protected $fillable = [
        'option_id', 'value', 'image_path', 'sort'
    ];

    protected $casts = [
        'sort' => 'integer',
    ];
}
