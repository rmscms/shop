<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $table = 'carts';

    protected $fillable = [
        'user_id', 'status'
    ];
}
