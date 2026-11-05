<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/** Timeline of everything an API token did on a product (reviews, suggestions, changes, notes). */
class AiActivity extends Model
{
    public const TYPES = [
        'review' => 'بررسی',
        'suggestion' => 'پیشنهاد',
        'apply' => 'اعمال مستقیم',
        'write' => 'تغییر',
        'note' => 'یادداشت',
    ];

    protected $table = 'shop_ai_activities';

    protected $fillable = ['product_id', 'resource_type', 'resource_id', 'access_id', 'actor', 'type', 'title', 'details'];

    protected $casts = ['details' => 'array'];

    /** Never lets logging break the request that is being logged. */
    public static function record(?ShopApiAccess $access, ?int $productId, string $type, string $title, array $details = [], ?string $resourceType = null, ?int $resourceId = null): void
    {
        try {
            if (! Schema::hasTable('shop_ai_activities')) {
                return;
            }
            // Product history keeps using product_id; other resources use resource_type/resource_id.
            if ($resourceType === 'product' && $productId === null) {
                $productId = $resourceId;
            }
            static::query()->create([
                'product_id' => $productId,
                'resource_type' => $resourceType ?? ($productId ? 'product' : null),
                'resource_id' => $resourceId ?? $productId,
                'access_id' => $access?->id,
                'actor' => $access?->name,
                'type' => $type,
                'title' => mb_substr($title, 0, 255),
                'details' => $details ?: null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
