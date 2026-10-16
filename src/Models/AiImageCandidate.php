<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiImageCandidate extends Model
{
    public const STATUS_CANDIDATE = 'candidate';
    public const STATUS_ATTACHED = 'attached';
    public const STATUS_REJECTED = 'rejected';

    protected $table = 'shop_ai_image_candidates';

    protected $fillable = [
        'product_id',
        'catalog_item_id',
        'url',
        'source_url',
        'title',
        'status',
        'image_id',
        'error',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function sourceHost(): string
    {
        return (string) (parse_url((string) ($this->source_url ?: $this->url), PHP_URL_HOST) ?? '');
    }
}
