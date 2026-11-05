<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use RMS\Shop\Models\Concerns\HasRound;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Log of product changes an agent applied directly on an admin's explicit order. */
class AiAction extends Model
{
    use HasRound;

    protected $table = 'shop_ai_actions';

    protected $fillable = [
        'product_id', 'access_id', 'source', 'fields', 'before', 'after', 'operator_instruction', 'suggestion_id',
    ];

    protected $casts = [
        'fields' => 'array',
        'before' => 'array',
        'after' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
