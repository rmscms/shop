<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSuggestion extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_REJECTED = 'rejected';

    protected $table = 'shop_ai_suggestions';

    protected $fillable = [
        'product_id',
        'status',
        'driver',
        'model',
        'payload',
        'applied_fields',
        'error',
        'input_tokens',
        'output_tokens',
        'images_sent',
        'applied_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'applied_fields' => 'array',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'images_sent' => 'integer',
        'applied_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING, self::STATUS_READY], true);
    }
}
