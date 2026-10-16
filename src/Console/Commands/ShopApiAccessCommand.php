<?php

namespace RMS\Shop\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RMS\Shop\Models\ShopApiAccess;

class ShopApiAccessCommand extends Command
{
    protected $signature = 'shop:api-access
        {action : create or revoke}
        {name : Access name}
        {--rate=0 : Requests per minute. Zero means no cap}
        {--ip=* : Allowed IP address}';

    protected $description = 'Create or revoke a shop admin API access token';

    public function handle(): int
    {
        $action = strtolower((string) $this->argument('action'));

        return match ($action) {
            'create' => $this->createAccess(),
            'revoke' => $this->revokeAccess(),
            default => $this->failAction(),
        };
    }

    protected function createAccess(): int
    {
        $name = trim((string) $this->argument('name'));
        $ips = array_values(array_filter(array_map(
            static fn ($ip) => trim((string) $ip),
            (array) $this->option('ip')
        )));
        $rate = (int) $this->option('rate');

        if ($name === '') {
            $this->error('نام دسترسی لازم است.');

            return self::FAILURE;
        }

        if ($rate < 0) {
            $this->error('ریت‌لیمیت نمی‌تواند منفی باشد. صفر یعنی بدون سقف.');

            return self::FAILURE;
        }

        if (ShopApiAccess::query()->where('name', $name)->exists()) {
            $this->error('این نام قبلاً ثبت شده است.');

            return self::FAILURE;
        }

        $plain = Str::random(48);

        ShopApiAccess::query()->create([
            'name' => $name,
            'token_hash' => ShopApiAccess::hashToken($plain),
            'token' => $plain,
            'rate_limit' => $rate,
            'allowed_ips' => $ips,
            'active' => true,
        ]);

        $this->info('دسترسی ساخته شد. این توکن فقط همین یک‌بار نشان داده می‌شود:');
        $this->line($plain);

        return self::SUCCESS;
    }

    protected function revokeAccess(): int
    {
        $name = trim((string) $this->argument('name'));
        $access = ShopApiAccess::query()->where('name', $name)->first();

        if (!$access) {
            $this->error('دسترسی پیدا نشد.');

            return self::FAILURE;
        }

        $access->forceFill(['active' => false])->save();
        $this->info('دسترسی غیرفعال شد.');

        return self::SUCCESS;
    }

    protected function failAction(): int
    {
        $this->error('عملیات باید create یا revoke باشد.');

        return self::FAILURE;
    }
}
