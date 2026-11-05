<?php

namespace RMS\Shop\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\HtmlString;

/**
 * Admin dates: Jalali (Shamsi) in Persian digits plus a human "how long ago".
 * Times are shown in config('shop.display_timezone') (default Asia/Tehran), whatever the app stores.
 */
class UiDate
{
    protected static function local(mixed $date): ?CarbonInterface
    {
        if ($date === null || $date === '') {
            return null;
        }
        $carbon = $date instanceof CarbonInterface ? $date->copy() : \Illuminate\Support\Carbon::parse($date);

        return $carbon->timezone((string) config('shop.display_timezone', 'Asia/Tehran'));
    }

    protected static function digits(string $text): string
    {
        return class_exists(\Morilog\Jalali\CalendarUtils::class)
            ? \Morilog\Jalali\CalendarUtils::convertNumbers($text)
            : $text;
    }

    /** 1405/08/14 10:16 (Persian digits); falls back to Gregorian if the Jalali library is missing. */
    public static function jalali(mixed $date, bool $withTime = true): string
    {
        $carbon = static::local($date);
        if (! $carbon) {
            return '—';
        }
        $format = $withTime ? 'Y/m/d H:i' : 'Y/m/d';
        $text = class_exists(\Morilog\Jalali\Jalalian::class)
            ? static::digits(\Morilog\Jalali\Jalalian::fromCarbon($carbon)->format($format))
            : $carbon->format($withTime ? 'Y-m-d H:i' : 'Y-m-d');

        // Left-to-right isolate: inside right-to-left text the slashes and digits would otherwise get reordered.
        return "\u{2066}".$text."\u{2069}";
    }

    /** "5 minutes ago", "yesterday", "in 2 hours" in Persian. */
    public static function human(mixed $date): string
    {
        $carbon = static::local($date);
        if (! $carbon) {
            return '—';
        }

        $secs = $carbon->getTimestamp() - time();
        $future = $secs > 0;
        $abs = abs($secs);

        if ($abs < 45) {
            return $future ? 'چند لحظهٔ دیگر' : 'همین الان';
        }
        $units = [
            [31536000, 'سال'], [2592000, 'ماه'], [604800, 'هفته'], [86400, 'روز'], [3600, 'ساعت'], [60, 'دقیقه'],
        ];
        foreach ($units as [$size, $name]) {
            if ($abs >= $size) {
                $n = (int) floor($abs / $size);
                if ($name === 'روز' && $n === 1) {
                    return $future ? 'فردا' : 'دیروز';
                }

                return static::digits($n.' '.$name).($future ? ' دیگر' : ' پیش');
            }
        }

        return 'همین الان';
    }

    /** "2 hours ago · 1405/08/14 10:16" as plain text. */
    public static function both(mixed $date): string
    {
        return static::local($date) ? static::human($date).' · '.static::jalali($date) : '—';
    }

    /**
     * Markup for a table cell or a meta line.
     * stack: human text on top, Jalali date small below. inline: both on one line.
     */
    public static function html(mixed $date, string $mode = 'stack'): HtmlString
    {
        $carbon = static::local($date);
        if (! $carbon) {
            return new HtmlString('<span class="text-muted">—</span>');
        }
        $human = e(static::human($date));
        $full = e(static::jalali($date));
        $iso = e($carbon->toIso8601String());

        if ($mode === 'inline') {
            return new HtmlString('<time datetime="'.$iso.'" title="'.$full.'">'.$human.' <span class="opacity-75">· '.$full.'</span></time>');
        }

        return new HtmlString('<time datetime="'.$iso.'" title="'.$full.'" class="shop-when"><span class="shop-when__human">'.$human.'</span><span class="shop-when__abs">'.$full.'</span></time>');
    }
}
