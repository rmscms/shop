<?php

namespace RMS\Shop\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class CartReservationService
{
    private int $ttl;

    public function __construct()
    {
        $this->ttl = (int) config('shop.cart_reservation_ttl', 7200);
    }

    public function syncLine(int $cartId, int $productId, ?int $combinationId, int $newQty, int $oldQty = 0): void
    {
        $newQty = max(0, (int) $newQty);
        $oldQty = max(0, (int) $oldQty);
        if ($newQty === $oldQty) {
            if ($newQty > 0) {
                $this->touchCartSet($productId, $combinationId, $cartId);
            }
            return;
        }

        $delta = $newQty - $oldQty;
        try {
            if ($delta > 0) {
                $this->incrementCounter($productId, $combinationId, $delta);
                $this->addCartToSet($productId, $combinationId, $cartId);
            } else {
                $this->decrementCounter($productId, $combinationId, abs($delta));
                if ($newQty <= 0) {
                    $this->removeCartFromSet($productId, $combinationId, $cartId);
                }
            }

            if ($combinationId) {
                // Maintain product-level cart set for combinations as well
                if ($delta > 0 && $newQty > 0) {
                    $this->addCartToSet($productId, null, $cartId);
                } elseif ($newQty <= 0) {
                    $this->removeCartFromSet($productId, null, $cartId);
                }
            } elseif ($delta > 0 && $newQty > 0) {
                $this->addCartToSet($productId, null, $cartId);
            } elseif ($newQty <= 0) {
                $this->removeCartFromSet($productId, null, $cartId);
            }

            if ($newQty > 0) {
                $this->touchCartSet($productId, $combinationId, $cartId);
                if ($combinationId) {
                    $this->touchCartSet($productId, null, $cartId);
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function releaseCart(int $cartId): void
    {
        try {
            $lines = DB::table('cart_items')
                ->where('cart_id', $cartId)
                ->get(['product_id','combination_id','qty']);
        } catch (\Throwable $e) {
            report($e);
            return;
        }

        foreach ($lines as $line) {
            $productId = (int) $line->product_id;
            $combId = $line->combination_id !== null ? (int) $line->combination_id : null;
            $qty = (int) $line->qty;
            if ($qty <= 0) continue;
            $this->syncLine($cartId, $productId, $combId, 0, $qty);
        }
    }

    /**
     * @return array<int>
     */
    public function cartsForProduct(int $productId, ?int $combinationId = null): array
    {
        $keys = [$this->cartsKey($productId, null)];
        if ($combinationId) {
            $keys = [$this->cartsKey($productId, $combinationId)];
        }

        try {
            $conn = Redis::connection();
        } catch (\Throwable $e) {
            report($e);
            return [];
        }

        $ids = [];
        foreach ($keys as $key) {
            try {
                $members = $conn->smembers($key);
                if (!empty($members)) {
                    foreach ($members as $member) {
                        $ids[] = (int) $member;
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return array_values(array_unique(array_filter($ids, fn ($id) => $id > 0)));
    }

    public function reservedCount(int $productId, ?int $combinationId = null): int
    {
        try {
            $conn = \Illuminate\Support\Facades\Redis::connection();
        } catch (\Throwable $e) {
            report($e);
            return 0;
        }
        $key = $this->counterKey($productId, $combinationId);
        try {
            $val = (int) $conn->get($key);
            return max(0, $val);
        } catch (\Throwable $e) {
            report($e);
            return 0;
        }
    }

    private function incrementCounter(int $productId, ?int $combinationId, int $qty): void
    {
        $this->adjustCounter($productId, $combinationId, $qty);
    }

    private function decrementCounter(int $productId, ?int $combinationId, int $qty): void
    {
        $this->adjustCounter($productId, $combinationId, -$qty);
    }

    private function adjustCounter(int $productId, ?int $combinationId, int $delta): void
    {
        try {
            $conn = Redis::connection();
        } catch (\Throwable $e) {
            report($e);
            return;
        }
        $key = $this->counterKey($productId, $combinationId);
        if ($delta > 0) {
            $conn->incrby($key, $delta);
            $this->touchTtl($conn, $key);
            return;
        }

        if ($conn->exists($key)) {
            $newVal = (int) $conn->decrby($key, abs($delta));
            if ($newVal <= 0) {
                $conn->del($key);
            } else {
                $this->touchTtl($conn, $key);
            }
        }
    }

    private function addCartToSet(int $productId, ?int $combinationId, int $cartId): void
    {
        if ($cartId <= 0) return;
        try {
            $conn = Redis::connection();
            $key = $this->cartsKey($productId, $combinationId);
            $conn->sadd($key, $cartId);
            $this->touchTtl($conn, $key);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function touchCartSet(int $productId, ?int $combinationId, int $cartId): void
    {
        if ($cartId <= 0) return;
        try {
            $conn = Redis::connection();
            $key = $this->cartsKey($productId, $combinationId);
            if ($conn->exists($key) && $this->ttl > 0) {
                $conn->expire($key, $this->ttl);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function removeCartFromSet(int $productId, ?int $combinationId, int $cartId): void
    {
        if ($cartId <= 0) return;
        try {
            $conn = Redis::connection();
            $key = $this->cartsKey($productId, $combinationId);
            if ($conn->exists($key)) {
                $conn->srem($key, $cartId);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function counterKey(int $productId, ?int $combinationId): string
    {
        if ($combinationId) {
            return "shop:reserve:comb:{$combinationId}:count";
        }
        return "shop:reserve:product:{$productId}:count";
    }

    private function cartsKey(int $productId, ?int $combinationId): string
    {
        if ($combinationId) {
            return "shop:reserve:comb:{$combinationId}:carts";
        }
        return "shop:reserve:product:{$productId}:carts";
    }

    private function touchTtl($conn, string $key): void
    {
        if ($this->ttl > 0) {
            try {
                $conn->expire($key, $this->ttl);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}


