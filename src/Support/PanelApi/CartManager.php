<?php

namespace RMS\Shop\Support\PanelApi;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RMS\Shop\Models\Cart;
use RMS\Shop\Models\Product;
use RMS\Shop\Models\ProductCombination;
use RMS\Shop\Services\CartReservationService;
use RMS\Shop\Services\ShopPriceService;

class CartManager
{
    protected ?array $rateMeta = null;

    public function __construct(
        protected CartStorage $storage,
        protected CartReservationService $reservations,
        protected ShopPriceService $priceService
    ) {
    }

    public function buildCartPayload(string $cartKey): array
    {
        $rawItems = collect($this->storage->getItems($cartKey))
            ->filter(fn ($line) => isset($line['product_id']) && (int)($line['qty'] ?? 0) > 0)
            ->values();

        if ($rawItems->isEmpty()) {
            return [
                'cart_key' => $cartKey,
                'items' => [],
                'summary' => [
                    'item_count' => 0,
                    'total_qty' => 0,
                    'total_amount' => 0,
                    'total_amount_cny' => null,
                ],
                'currency' => [
                    'code' => $this->priceService->displayCurrency(),
                    'label' => $this->priceService->displayLabel(),
                    'base_code' => $this->priceService->baseCurrency(),
                ],
                'rate' => $this->currentRateMeta(),
            ];
        }

        $productIds = $rawItems->pluck('product_id')->unique()->values();
        $products = Product::query()
            ->with([
                'category:id,name,slug',
                'images' => function ($q) {
                    $q->select('id', 'product_id', 'path', 'is_main', 'sort')
                        ->orderByDesc('is_main')
                        ->orderBy('sort');
                },
            ])
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $combinationIds = $rawItems->pluck('combination_id')->filter()->unique()->values();
        $combinations = ProductCombination::query()
            ->whereIn('id', $combinationIds->all() ?: [0])
            ->get()
            ->keyBy('id');

        $lines = [];
        $totalQty = 0;
        $totalAmount = 0.0;
        $totalAmountCny = 0.0;
        $rateMeta = $this->currentRateMeta();

        foreach ($rawItems as $entry) {
            $productId = (int) $entry['product_id'];
            $combinationId = $entry['combination_id'] ?? null;
            $qty = max(1, (int) $entry['qty']);
            $product = $products->get($productId);
            if (!$product) {
                continue;
            }
            $combination = $combinationId ? $combinations->get((int) $combinationId) : null;
            if ($combinationId && !$combination) {
                continue;
            }

            $availableStock = $this->resolveAvailableStock($product, $combination);
            if ($availableStock <= 0) {
                continue;
            }

            $qty = min($qty, $availableStock);

            $unitPriceCny = $this->resolveUnitPriceCny($product, $combination);
            $context = 'product:'.$productId.':'.($combinationId ?: 'base');
            $unitPrice = $unitPriceCny !== null
                ? $this->priceService->convertFromBase($unitPriceCny, $context)
                : null;
            if ($unitPrice === null) {
                $unitPrice = $this->resolveUnitPrice($product, $combination);
            }
            $unitPrice = $unitPrice !== null ? (float) $unitPrice : 0.0;

            $subtotal = round($unitPrice * $qty, 2);
            $subtotalCny = $unitPriceCny !== null ? round($unitPriceCny * $qty, 4) : null;
            $totalQty += $qty;
            $totalAmount += $subtotal;
            if ($subtotalCny !== null) {
                $totalAmountCny += $subtotalCny;
            }

            $lines[] = [
                'line_id' => $entry['line_id'] ?? Str::uuid()->toString(),
                'product_id' => $productId,
                'combination_id' => $combination?->id,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                 'unit_price_cny' => $unitPriceCny,
                 'subtotal_cny' => $subtotalCny,
                'product' => (new \RMS\Shop\Http\Resources\Panel\ProductResource($product))->toArray(request()),
                'combination' => $combination ? [
                    'id' => (int) $combination->id,
                    'sku' => $combination->sku,
                ] : null,
                'rate' => $rateMeta,
                'currency' => $this->priceService->displayCurrency(),
            ];
        }

        return [
            'cart_key' => $cartKey,
            'items' => $lines,
            'summary' => [
                'item_count' => count($lines),
                'total_qty' => $totalQty,
                'total_amount' => round($totalAmount, 2),
                'total_amount_cny' => $totalAmountCny > 0 ? round($totalAmountCny, 4) : null,
            ],
            'currency' => [
                'code' => $this->priceService->displayCurrency(),
                'label' => $this->priceService->displayLabel(),
                'base_code' => $this->priceService->baseCurrency(),
            ],
            'rate' => $rateMeta,
        ];
    }

    public function syncToUserCart(string $cartKey, int $userId): ?Cart
    {
        $payload = $this->buildCartPayload($cartKey);
        if (empty($payload['items'])) {
            return null;
        }

        return DB::transaction(function () use ($payload, $userId) {
            /** @var Cart $cart */
            $cart = Cart::query()->firstOrCreate(
                ['user_id' => $userId, 'status' => 'open'],
                ['created_at' => now(), 'updated_at' => now()]
            );

            $existing = DB::table('cart_items')
                ->where('cart_id', $cart->id)
                ->get()
                ->keyBy(fn ($row) => $this->lineKey((int) $row->product_id, $row->combination_id));

            foreach ($payload['items'] as $line) {
                $key = $this->lineKey($line['product_id'], $line['combination_id']);
                $data = [
                    'product_id' => $line['product_id'],
                    'combination_id' => $line['combination_id'],
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'unit_price_cny' => $line['unit_price_cny'] ?? null,
                    'updated_at' => now(),
                ];

                if ($existing->has($key)) {
                    $row = $existing->get($key);
                    DB::table('cart_items')
                        ->where('id', $row->id)
                        ->update($data);
                    $this->reservations->syncLine($cart->id, $line['product_id'], $line['combination_id'], $line['qty'], (int) $row->qty);
                    $existing->forget($key);
                } else {
                    DB::table('cart_items')->insert(array_merge($data, [
                        'cart_id' => $cart->id,
                        'created_at' => now(),
                    ]));
                    $this->reservations->syncLine($cart->id, $line['product_id'], $line['combination_id'], $line['qty'], 0);
                }
            }

            if ($existing->isNotEmpty()) {
                foreach ($existing as $row) {
                    DB::table('cart_items')->where('id', $row->id)->delete();
                    $this->reservations->syncLine($cart->id, (int) $row->product_id, $row->combination_id ? (int) $row->combination_id : null, 0, (int) $row->qty);
                }
            }

            $cart->touch();

            return $cart;
        });
    }

    protected function resolveAvailableStock(Product $product, ?ProductCombination $combination = null): int
    {
        if ($combination) {
            return max(0, (int) ($combination->stock_qty ?? 0));
        }

        return max(0, (int) ($product->stock_qty ?? 0));
    }

    protected function resolveUnitPriceCny(Product $product, ?ProductCombination $combination = null): ?float
    {
        $candidates = [
            $combination?->sale_price_cny,
            $product->sale_price_cny,
            $product->cost_cny,
        ];

        foreach ($candidates as $candidate) {
            if (!is_null($candidate) && (float) $candidate > 0) {
                return (float) $candidate;
            }
        }

        return null;
    }

    protected function resolveUnitPrice(Product $product, ?ProductCombination $combination = null): ?float
    {
        $candidates = [
            $combination?->sale_price,
            $combination?->price,
            $product->sale_price,
            $product->price,
        ];

        foreach ($candidates as $candidate) {
            if (!is_null($candidate) && (float) $candidate > 0) {
                return (float) $candidate;
            }
        }

        return null;
    }

    protected function lineKey(int $productId, ?int $combinationId): string
    {
        return $productId . ':' . ($combinationId ?? '0');
    }

    protected function currentRateMeta(): ?array
    {
        if ($this->rateMeta !== null) {
            return $this->rateMeta;
        }

        return $this->rateMeta = $this->priceService->currentRateMeta();
    }
}

