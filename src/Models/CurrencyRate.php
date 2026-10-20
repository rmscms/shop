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
        // Avoid forcing trailing zeros (IRT has 0 decimals; display formats per quote).
        'sell_rate' => 'float',
        'effective_at' => 'datetime',
    ];
}
