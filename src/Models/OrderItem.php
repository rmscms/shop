<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'product_id',
        'combination_id',
        'qty',
        'unit_price',
        'total',
        'unit_price_cny',
        'rate_cny_to_irt',
        'points_awarded',
        'item_name',
        'item_attributes',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function combination(): BelongsTo
    {
        return $this->belongsTo(ProductCombination::class, 'combination_id');
    }
}