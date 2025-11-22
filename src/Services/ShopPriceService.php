<?php

namespace RMS\Shop\Services;

use Illuminate\Support\Facades\Cache;
use RMS\Shop\Models\CurrencyRate;

class ShopPriceService
{
    protected string $baseCurrency;
    protected string $displayCurrency;
    protected string $displayLabel;
    protected int $decimals;

    public function __construct()
    {
        $this->baseCurrency = strtoupper(config('shop.currency.base', 'CNY'));
        $this->displayCurrency = strtoupper(config('shop.currency.display', 'IRT'));
        $this->displayLabel = (string) config('shop.currency.label', 'تومان');
        $this->decimals = (int) config('shop.currency.decimals', 0);
    }

    public function baseCurrency(): string
    {
        return $this->baseCurrency;
    }

    public function displayCurrency(): string
    {
        return $this->displayCurrency;
    }

    public function displayLabel(): string
    {
        return $this->displayLabel;
    }

    /**
     * Convert amount from base currency (CNY) to display currency (IRT) with caching.
     */
    public function convertFromBase(?float $amount, string $context = ''): ?float
    {
        if ($amount === null) {
            return null;
        }

        $amount = (float) $amount;

        if ($this->baseCurrency === $this->displayCurrency) {
            return round($amount, $this->decimals);
        }

        $meta = $this->currentRateMeta();
        if (!$meta || $meta['rate'] <= 0) {
            return null;
        }

        $cacheKey = $this->cachedPriceKey($meta['version'], $amount, $context);

        return Cache::rememberForever($cacheKey, function () use ($meta, $amount) {
            return round($amount * $meta['rate'], $this->decimals);
        });
    }

    /**
     * Convert amount from display currency (IRT) back to base currency (CNY).
     */
    public function convertToBase(?float $amount, ?array $rateMeta = null): ?float
    {
        if ($amount === null) {
            return null;
        }

        if ($this->baseCurrency === $this->displayCurrency) {
            return round($amount, 4);
        }

        $meta = $rateMeta ?? $this->currentRateMeta();
        if (!$meta || $meta['rate'] <= 0) {
            return null;
        }

        return round((float) $amount / $meta['rate'], 4);
    }

    /**
     * Return the latest CNY→IRT rate metadata (rate, version id, effective_at).
     */
    public function currentRateMeta(): ?array
    {
        $cacheKey = sprintf('shop:rate_meta:%s_%s', $this->baseCurrency, $this->displayCurrency);

        return Cache::remember($cacheKey, 60, function () {
            $rate = CurrencyRate::query()
                ->where('base_code', $this->baseCurrency)
                ->where('quote_code', $this->displayCurrency)
                ->orderByDesc('effective_at')
                ->first();

            if (!$rate || !$rate->sell_rate) {
                return null;
            }

            return [
                'rate' => (float) $rate->sell_rate,
                'version' => (string) ($rate->id ?? optional($rate->effective_at)->timestamp ?? time()),
                'effective_at' => optional($rate->effective_at)->toDateTimeString(),
            ];
        });
    }

    protected function cachedPriceKey(string $version, float $amount, string $context): string
    {
        return sprintf(
            'shop:price_cache:%s_%s:%s:%s',
            $this->baseCurrency,
            $this->displayCurrency,
            $version,
            sha1($amount.'|'.$context)
        );
    }
}

