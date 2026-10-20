<?php

namespace RMS\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RMS\Shop\Support\Geo\IranProvinces;

class Order extends Model
{
    protected $table = 'orders';

    protected $fillable = [
        'user_id', 'user_address_id', 'status', 'subtotal', 'discount', 'shipping_cost', 'total', 'paid_at', 'refunded_at',
        'shipping_name', 'shipping_province_id', 'shipping_province', 'shipping_city',
        'shipping_mobile', 'shipping_postal_code', 'shipping_address', 'customer_note',
        'payment_driver', 'payment_reference',
        'tracking_code', 'tracking_url', 'finance_id',
    ];

    protected $casts = [
        'finance_id' => 'integer',
        'shipping_province_id' => 'integer',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    public function address(): BelongsTo
    {
        return $this->belongsTo(UserAddress::class, 'user_address_id');
    }

    /** Province name snapshot, with fallbacks for older orders. */
    public function shippingProvinceName(): ?string
    {
        if (filled($this->shipping_province)) {
            return (string) $this->shipping_province;
        }

        $fromId = IranProvinces::label($this->shipping_province_id);
        if ($fromId) {
            return $fromId;
        }

        $addr = $this->relationLoaded('address') ? $this->address : $this->address()->first();
        if ($addr) {
            return $addr->province ?: IranProvinces::label($addr->province_id);
        }

        return null;
    }

    /** "استان، شهر" for UI / PDF lines. */
    public function shippingPlaceLabel(): string
    {
        return trim(implode('، ', array_filter([
            $this->shippingProvinceName(),
            filled($this->shipping_city) ? (string) $this->shipping_city : null,
        ])));
    }

    /**
     * Unified order statuses for use across admin/panel/frontend.
     * @return array<string, object> code => (object){ label, class, icon }
     */
    public static function statuses(): array
    {
        return [
            'pending'   => (object)['label' => 'در انتظار',         'class' => 'bg-warning text-dark', 'icon' => 'clock'],
            'preparing' => (object)['label' => 'در حال آماده‌سازی',  'class' => 'bg-info',              'icon' => 'arrow-clockwise'],
            'shipped'   => (object)['label' => 'ارسال شده',          'class' => 'bg-primary',           'icon' => 'truck'],
            'delivered' => (object)['label' => 'تحویل شده',          'class' => 'bg-success',           'icon' => 'check-circle'],
            'returned'  => (object)['label' => 'برگشت خورده',        'class' => 'bg-secondary',         'icon' => 'arrow-u-up-left'],
            'rejected'  => (object)['label' => 'رد شده',             'class' => 'bg-danger',            'icon' => 'x-circle'],
            // compatibility/legacy
            'paid'      => (object)['label' => 'پرداخت‌شده',         'class' => 'bg-success',           'icon' => 'check-circle'],
            'cancelled' => (object)['label' => 'لغو شده',            'class' => 'bg-danger',            'icon' => 'x-circle'],
        ];
    }

    /**
     * Options for selects: code => label
     */
    public static function statusOptions(): array
    {
        return collect(self::statuses())
            ->mapWithKeys(fn($o, $k) => [$k => $o->label])
            ->all();
    }

    /** Get info object for current status */
    public function statusInfo(): object
    {
        $all = self::statuses();
        return $all[$this->status] ?? (object)['label' => (string)$this->status, 'class' => 'bg-secondary', 'icon' => 'question'];
    }

    /** Convenience accessors */
    public function getStatusLabelAttribute(): string { return $this->statusInfo()->label; }
    public function getStatusClassAttribute(): string { return $this->statusInfo()->class; }

    /** Relations */
    public function notes(): HasMany
    {
        return $this->hasMany(OrderAdminNote::class, 'order_id');
    }

    public function finance(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Finance::class, 'finance_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function visibleNotes(): HasMany
    {
        return $this->notes()->where('visible_to_user', true)->orderByDesc('id');
    }
}
