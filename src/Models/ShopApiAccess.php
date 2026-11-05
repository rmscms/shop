<?php

namespace RMS\Shop\Models;

use Carbon\Carbon;
use DateInterval;
use Illuminate\Database\Eloquent\Model;

class ShopApiAccess extends Model
{
    protected $table = 'shop_api_accesses';

    protected $fillable = [
        'name',
        'token_hash',
        'token',
        'rate_limit',
        'allowed_ips',
        'active',
        'permission',
        'expires_at',
        'last_used_at',
    ];

    public const PERMISSION_READ = 'read';
    public const PERMISSION_READ_WRITE = 'read_write';

    /** How long a new token stays valid, so a forgotten token stops working by itself. */
    public const EXPIRY_PRESETS = [
        '1h' => ['label' => '۱ ساعت', 'interval' => 'PT1H'],
        '2h' => ['label' => '۲ ساعت', 'interval' => 'PT2H'],
        '4h' => ['label' => '۴ ساعت', 'interval' => 'PT4H'],
        '1d' => ['label' => '۱ روز', 'interval' => 'P1D'],
        '7d' => ['label' => '۷ روز', 'interval' => 'P7D'],
        '30d' => ['label' => '۱ ماه', 'interval' => 'P1M'],
        '60d' => ['label' => '۲ ماه', 'interval' => 'P2M'],
        'never' => ['label' => 'بدون انقضا', 'interval' => null],
    ];

    /** Pre-selected when making a token: long enough to work, short enough to be safe. */
    public const EXPIRY_DEFAULT = '7d';

    protected $casts = [
        'token' => 'encrypted',
        'rate_limit' => 'integer',
        'allowed_ips' => 'array',
        'active' => 'boolean',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function canWrite(): bool
    {
        return $this->permission === self::PERMISSION_READ_WRITE;
    }

    /** Expiry time for a preset key counted from now; null means it never expires. */
    public static function expiresAtFor(?string $key): ?Carbon
    {
        $interval = self::EXPIRY_PRESETS[$key ?? self::EXPIRY_DEFAULT]['interval'] ?? null;

        return $interval ? Carbon::now()->add(new DateInterval($interval)) : null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->getTimestamp() <= time();
    }

    /** True when the token can be used right now. */
    public function isUsable(): bool
    {
        return $this->active && ! $this->isExpired();
    }

    /** Short remaining-time text, e.g. "۲ روز دیگر", for the admin page. */
    public function expiryLabel(): string
    {
        if ($this->expires_at === null) {
            return 'بدون انقضا';
        }
        if ($this->isExpired()) {
            return 'منقضی شده';
        }

        $secs = $this->expires_at->getTimestamp() - time();
        $mins = intdiv($secs, 60);
        if ($mins < 60) {
            return max(1, $mins).' دقیقه دیگر';
        }
        if ($mins < 1440) {
            $h = intdiv($mins, 60);
            $m = $mins % 60;

            return $h.' ساعت'.($m ? ' و '.$m.' دقیقه' : '').' دیگر';
        }
        $days = intdiv($mins, 1440);
        if ($days < 60) {
            return $days.' روز دیگر';
        }

        return intdiv($days, 30).' ماه دیگر';
    }

    /** Whether the token expires within a day (the admin page warns on it). */
    public function expiresSoon(): bool
    {
        return $this->expires_at !== null && ! $this->isExpired() && ($this->expires_at->getTimestamp() - time()) < 86400;
    }

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
