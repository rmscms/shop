<?php

namespace RMS\Shop\Services;

class PricingService
{
    /**
     * Apply product-level discount to a base CNY price.
     * @param float|int|null $baseCny
     * @param string|null $type 'percent' or 'amount'
     * @param float|int|null $value
     * @return float discounted CNY (>= 0, 2 decimals)
     */
    public static function applyDiscount($baseCny, ?string $type, $value): float
    {
        $base = (float)($baseCny ?? 0);
        $val = (float)($value ?? 0);
        if ($base <= 0) return 0.0;
        if (!$type || $val <= 0) return round($base, 2);
        if ($type === 'percent') {
            $pct = max(0.0, min(100.0, $val));
            $res = $base * (1.0 - ($pct / 100.0));
            return round(max(0.0, $res), 2);
        }
        // amount (CNY)
        $res = $base - $val;
        return round(max(0.0, $res), 2);
    }

    /**
     * Build a simple label descriptor for UI (without currency conversion).
     * Returns [ 'kind' => 'percent'|'amount', 'value' => float ]
     */
    public static function discountMeta(?string $type, $value): ?array
    {
        $val = (float)($value ?? 0);
        if (!$type || $val <= 0) return null;
        if ($type === 'percent') return ['kind' => 'percent', 'value' => max(0.0, min(100.0, $val))];
        return ['kind' => 'amount', 'value' => max(0.0, $val)];
    }
}
