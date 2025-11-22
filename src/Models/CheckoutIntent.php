<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class CheckoutIntent extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    protected $table = 'checkout_intents';

    protected $fillable = [
        'reference',
        'user_id',
        'cart_id',
        'cart_key',
        'payment_driver',
        'cart_snapshot',
        'address_snapshot',
        'pricing_snapshot',
        'customer_note',
        'status',
        'order_id',
    ];

    protected $casts = [
        'cart_snapshot' => 'array',
        'address_snapshot' => 'array',
        'pricing_snapshot' => 'array',
    ];
}

