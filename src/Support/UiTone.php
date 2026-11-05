<?php

namespace RMS\Shop\Support;

/**
 * Status and score to label and tone, kept in one place so every admin page agrees.
 * Tones match the .tone-* classes in public/admin/css/shop-ui.css.
 */
class UiTone
{
    /** @var array<string, array<string, array{0: string, 1: string}>> [label, tone] */
    protected const STATUS = [
        'review' => [
            'requested' => ['در انتظار بررسی', 'warning'],
            'done' => ['انجام شد', 'success'],
            'dismissed' => ['لغو شد', 'muted'],
        ],
        'proposal' => [
            'pending' => ['در انتظار تأیید', 'warning'],
            'applied' => ['اعمال شد', 'success'],
            'rejected' => ['رد شد', 'muted'],
            'superseded' => ['جایگزین شد', 'muted'],
            'failed' => ['خطا', 'danger'],
        ],
        'suggestion' => [
            'pending' => ['در صف', 'muted'],
            'processing' => ['در حال پردازش', 'info'],
            'ready' => ['آمادهٔ بررسی', 'primary'],
            'failed' => ['خطا', 'danger'],
            'applied' => ['اعمال شد', 'success'],
            'rejected' => ['رد شد', 'muted'],
        ],
        'activity' => [
            'review' => ['بررسی', 'info'],
            'suggestion' => ['پیشنهاد', 'primary'],
            'apply' => ['اعمال مستقیم', 'danger'],
            'write' => ['تغییر', 'warning'],
            'note' => ['یادداشت', 'muted'],
        ],
    ];

    public const ACTIVITY_ICONS = [
        'review' => 'ph-chart-bar',
        'suggestion' => 'ph-lightbulb',
        'apply' => 'ph-check-circle',
        'write' => 'ph-pencil-simple',
        'note' => 'ph-note',
    ];

    public static function label(string $group, ?string $status): string
    {
        return self::STATUS[$group][$status ?? ''][0] ?? (string) $status;
    }

    public static function tone(string $group, ?string $status): string
    {
        return self::STATUS[$group][$status ?? ''][1] ?? 'muted';
    }

    /** Score 0-100: 75+ good, 50-74 middling, below 50 weak, null unknown. */
    public static function score(?int $score): string
    {
        if ($score === null) {
            return 'muted';
        }

        return $score >= 75 ? 'success' : ($score >= 50 ? 'warning' : 'danger');
    }
}
