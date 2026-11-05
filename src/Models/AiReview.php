<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiReview extends Model
{
    public const STATUS_REQUESTED = 'requested';
    public const STATUS_DONE = 'done';
    public const STATUS_DISMISSED = 'dismissed';

    /** Review aspects an admin can ask for; the agent answers per key. */
    public const ASPECTS = [
        'seo' => 'سئو',
        'content' => 'محتوا',
        'images' => 'تصاویر',
        'price' => 'قیمت',
        'competition' => 'رقابت',
    ];

    protected $table = 'shop_ai_reviews';

    protected $fillable = [
        'product_id', 'status', 'aspects', 'note', 'requested_by', 'requested_at',
        'result', 'summary', 'score', 'source', 'reviewed_at',
    ];

    protected $casts = [
        'aspects' => 'array',
        'result' => 'array',
        'score' => 'integer',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
