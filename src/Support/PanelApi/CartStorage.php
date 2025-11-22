<?php

namespace RMS\Shop\Support\PanelApi;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CartStorage
{
    protected string $cookieName;
    protected int $ttlSeconds;

    public function __construct(protected CacheRepository $cache)
    {
        $config = config('shop.panel_api.cart', []);
        $this->cookieName = $config['cookie_name'] ?? 'panel_cart';
        $this->ttlSeconds = (int) ($config['ttl'] ?? 60 * 60 * 24 * 30);
    }

    public function resolveCartKey(Request $request): array
    {
        $cartKey = $request->cookie($this->cookieName) ?: $request->header('X-Cart-Key');
        $needsCookie = false;

        if (!$cartKey || !preg_match('/^[A-Za-z0-9\-]{8,}$/', $cartKey)) {
            $cartKey = Str::uuid()->toString();
            $needsCookie = true;
        }

        $cookie = null;
        if ($needsCookie) {
            $minutes = (int) ceil($this->ttlSeconds / 60);
            $cookie = cookie($this->cookieName, $cartKey, $minutes, '/', null, false, false);
        }

        return [$cartKey, $cookie];
    }

    public function getItems(string $cartKey): array
    {
        return $this->cache->get($this->cacheKey($cartKey), []);
    }

    public function putItems(string $cartKey, array $items): void
    {
        $this->cache->put($this->cacheKey($cartKey), array_values($items), $this->ttlSeconds);
    }

    public function addItem(string $cartKey, array $attributes): array
    {
        $items = $this->getItems($cartKey);
        $existingIndex = $this->findLineIndex($items, $attributes['product_id'], $attributes['combination_id']);

        if ($existingIndex !== null) {
            $items[$existingIndex]['qty'] += $attributes['qty'];
        } else {
            $items[] = [
                'line_id' => Str::uuid()->toString(),
                'product_id' => (int) $attributes['product_id'],
                'combination_id' => $attributes['combination_id'] ? (int) $attributes['combination_id'] : null,
                'qty' => (int) $attributes['qty'],
                'added_at' => now()->toIso8601String(),
            ];
        }

        $this->putItems($cartKey, $items);

        return $items;
    }

    public function updateLine(string $cartKey, string $lineId, int $qty): array
    {
        $items = $this->getItems($cartKey);
        foreach ($items as &$line) {
            if ($line['line_id'] === $lineId) {
                $line['qty'] = $qty;
                break;
            }
        }
        $this->putItems($cartKey, $items);

        return $items;
    }

    public function removeLine(string $cartKey, string $lineId): array
    {
        $items = $this->getItems($cartKey);
        $items = array_values(array_filter($items, fn ($line) => $line['line_id'] !== $lineId));
        $this->putItems($cartKey, $items);

        return $items;
    }

    protected function cacheKey(string $cartKey): string
    {
        return 'shop:panel_cart:' . $cartKey;
    }

    protected function findLineIndex(array $items, int $productId, ?int $combinationId): ?int
    {
        foreach ($items as $index => $line) {
            if ((int) $line['product_id'] === $productId && (int) ($line['combination_id'] ?? 0) === (int) ($combinationId ?? 0)) {
                return $index;
            }
        }

        return null;
    }
}

