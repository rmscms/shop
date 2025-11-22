<?php

namespace RMS\Shop\Services;

class MediaToken
{
    public static function generate(array $payload, int $ttlSeconds = 1800): string
    {
        $data = $payload;
        $data['exp'] = time() + $ttlSeconds;
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        $b64 = self::b64($json);
        $sig = hash_hmac('sha256', $b64, self::key());
        return $b64.'.'.$sig;
    }

    public static function verify(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) { return null; }
        [$b64, $sig] = $parts;
        $calc = hash_hmac('sha256', $b64, self::key());
        if (!hash_equals($calc, $sig)) { return null; }
        $json = self::b64d($b64);
        $data = json_decode($json, true);
        if (!is_array($data)) { return null; }
        if (!empty($data['exp']) && time() > (int)$data['exp']) { return null; }
        return $data;
    }

    private static function key(): string
    {
        return (string) config('app.key');
    }

    private static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private static function b64d(string $s): string
    {
        $pad = strlen($s) % 4;
        if ($pad) { $s .= str_repeat('=', 4 - $pad); }
        return base64_decode(strtr($s, '-_', '+/')) ?: '';
    }
}
