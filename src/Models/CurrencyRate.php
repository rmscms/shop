<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyRate extends Model
{
    protected $table = 'currency_rates';

    protected $fillable = [
        'base_code', 'quote_code', 'sell_rate', 'effective_at', 'notes'
    ];

    protected $casts = [
        'sell_rate' => 'decimal:6',
        'effective_at' => 'datetime',
    ];
}
