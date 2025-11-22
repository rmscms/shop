<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class UserAddress extends Model
{
    protected $table = 'user_addresses';

    protected $fillable = [
        'user_id',
        'full_name',
        'mobile',
        'phone',
        'province_id',
        'province',
        'city',
        'postal_code',
        'address_line',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'province_id' => 'integer',
    ];
}