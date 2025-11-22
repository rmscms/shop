<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPurchaseStats extends Model
{
    protected $table = 'product_purchase_stats';

    protected $fillable = [
        'user_id',
        'product_id',
        'purchase_date',
        'total_quantity',
        'total_amount',
        'order_count',
        'order_ids',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'product_id' => 'integer',
        'purchase_date' => 'date',
        'total_quantity' => 'integer',
        'total_amount' => 'decimal:2',
        'order_count' => 'integer',
        'order_ids' => 'array',
    ];

    /**
     * Relationship to User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Relationship to Product
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get popular products (most purchased) ordered by total quantity
     * 
     * @param int|null $limit
     * @param \Carbon\Carbon|null $since
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getPopularProducts(?int $limit = null, ?\Carbon\Carbon $since = null)
    {
        $query = static::query()
            ->selectRaw('product_id, SUM(total_quantity) as total_purchases, SUM(total_amount) as total_revenue, COUNT(DISTINCT user_id) as unique_buyers')
            ->groupBy('product_id')
            ->orderByDesc('total_purchases');
            
        if ($since) {
            $query->where('purchase_date', '>=', $since->toDateString());
        }
        
        if ($limit) {
            $query->limit($limit);
        }
        
        return $query->get();
    }
}

