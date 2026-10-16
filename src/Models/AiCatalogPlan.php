<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class AiCatalogPlan extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY = 'ready';
    public const STATUS_FAILED = 'failed';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_REJECTED = 'rejected';

    protected $table = 'shop_ai_catalog_plans';

    protected $fillable = [
        'batch',
        'status',
        'driver',
        'model',
        'payload',
        'result',
        'error',
        'input_tokens',
        'output_tokens',
        'applied_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'applied_at' => 'datetime',
    ];
}
