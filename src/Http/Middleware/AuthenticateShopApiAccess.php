<?php

namespace RMS\Shop\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use RMS\Shop\Models\ShopApiAccess;
use RMS\Shop\Support\AdminApi\AdminApiResponder;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateShopApiAccess
{
    /** Non-GET routes a read-only token may still call. */
    private const READ_TOKEN_WRITABLE_ROUTES = [
        'shop.admin_api.ai.suggestions.store',
        'shop.admin_api.ai.reviews.store',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $plain = $this->bearerToken($request);
        if ($plain === null || $plain === '') {
            return $this->error($request, ['token' => ['توکن ارسال نشده است.']], 401);
        }

        $access = ShopApiAccess::query()
            ->where('token_hash', ShopApiAccess::hashToken($plain))
            ->where('active', true)
            ->first();

        if (!$access) {
            return $this->error($request, ['token' => ['توکن نامعتبر است.']], 401);
        }

        $allowed = array_values(array_filter(array_map(
            static fn ($ip) => trim((string) $ip),
            $access->allowed_ips ?? []
        )));

        if ($allowed !== [] && !in_array($request->ip(), $allowed, true)) {
            return $this->error($request, ['ip' => ['آی‌پی مجاز نیست.']], 403);
        }

        // Read tokens may only read. The AI work queue (reviews and suggestions) is the one exception:
        // it records results for an admin to look at and changes nothing in the shop.
        if (! $access->canWrite()
            && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)
            && ! in_array($request->route()?->getName(), self::READ_TOKEN_WRITABLE_ROUTES, true)) {
            return $this->error($request, ['permission' => ['این توکن فقط خواندنی است (read).']], 403);
        }

        $limit = (int) $access->rate_limit;
        if ($limit > 0) {
            $key = 'shop-api-access:'.$access->id;

            if (RateLimiter::tooManyAttempts($key, $limit)) {
                $retry = RateLimiter::availableIn($key);

                return $this->error($request, ['rate_limit' => ['سقف درخواست پر شده است.']], 429, [
                    'retry_after' => $retry,
                ])->header('Retry-After', (string) $retry);
            }

            RateLimiter::hit($key, 60);
        }
        $access->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('shop_api_access', $access);

        return $next($request);
    }

    protected function bearerToken(Request $request): ?string
    {
        $header = (string) $request->header('Authorization', '');
        if (preg_match('/^\s*Bearer\s+(\S+)\s*$/i', $header, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    protected function error(Request $request, array $errors, int $status, array $meta = []): Response
    {
        return app(AdminApiResponder::class)->error($errors, $status, $meta, $request);
    }
}
