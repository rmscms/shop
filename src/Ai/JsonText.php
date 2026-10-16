<?php

namespace RMS\Shop\Ai;

final class JsonText
{
    /**
     * Read one JSON object from model text, even when it is wrapped in prose or a code fence.
     *
     * @return array<string, mixed>|null
     */
    public static function decode(string $raw): ?array
    {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $decoded = json_decode(substr($raw, $start, $end - $start + 1), true);

        return is_array($decoded) ? $decoded : null;
    }
}
