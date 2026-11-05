<?php

namespace RMS\Shop\Models;

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
        'last_used_at',
    ];

    public const PERMISSION_READ = 'read';
    public const PERMISSION_READ_WRITE = 'read_write';

    protected $casts = [
        'token' => 'encrypted',
        'rate_limit' => 'integer',
        'allowed_ips' => 'array',
        'active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function canWrite(): bool
    {
        return $this->permission === self::PERMISSION_READ_WRITE;
    }

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
