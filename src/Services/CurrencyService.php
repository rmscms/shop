<?php

namespace RMS\Shop\Services;

use RMS\Shop\Models\CurrencyRate;
use Illuminate\Support\Facades\Cache;

class CurrencyService
{
    /**
     * Static generic getter for any currency pair, cached for 5 minutes.
     */
    public static function getExchangeRate(string $base, string $quote): ?float
    {
        $base = strtoupper(trim($base));
        $quote = strtoupper(trim($quote));
        return Cache::remember('shop:rate:'.$base.'_'.$quote, 300, function () use ($base, $quote) {
            $rate = CurrencyRate::query()
                ->where('base_code', $base)
                ->where('quote_code', $quote)
                ->orderByDesc('effective_at')
                ->value('sell_rate');
            return $rate ? (float)$rate : null;
        });
    }

    /**
     * Static converter between currencies
     */
    public static function convert(string $base, string $quote, ?float $amount): ?float
    {
        if ($amount === null) { return null; }
        $rate = self::getExchangeRate($base, $quote);
        if ($rate === null || $rate <= 0) { return null; }
        return (float)($amount * $rate);
    }

    /**
     * Get latest CNY→IRT sell rate with short cache.
     */
    public function getCnyToIrtRate(): ?float
    {
        return Cache::remember('shop:rate:cny_irt', 300, function () {
            $rate = CurrencyRate::query()
                ->where('base_code', 'CNY')
                ->where('quote_code', 'IRT')
                ->orderByDesc('effective_at')
                ->value('sell_rate');
            return $rate ? (float)$rate : null;
        });
    }

    /**
     * Convert amount in CNY to IRT using latest rate; returns null if rate missing.
     */
    public function cnyToIrt(?float $amountCny): ?float
    {
        if ($amountCny === null) { return null; }
        $rate = $this->getCnyToIrtRate();
        if ($rate === null || $rate <= 0) { return null; }
        return (float)($amountCny * $rate);
    }
}


