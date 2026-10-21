<?php

namespace RMS\Shop\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RMS\Shop\Models\Category;
use RMS\Shop\Models\Product;
use RMS\Shop\Models\ProductCombination;
use RMS\Shop\Services\ShopPriceService;
use Throwable;

class BulkPricingController extends ShopAdminController
{
    public function table(): string
    {
        return 'products';
    }

    public function modelName(): string
    {
        return Product::class;
    }

    public function baseRoute(): string
    {
        return 'shop.bulk-pricing';
    }

    public function routeParameter(): string
    {
        return 'product';
    }

    public function index(Request $request)
    {
        $this->title('قیمت‌گذاری گروهی');

        $meta = app(ShopPriceService::class)->currentRateMeta();
        $categories = Category::query()
            ->orderBy('sort')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        $this->view
            ->usePackageNamespace('shop')
            ->setTpl('bulk-pricing.index')
            ->withJs('admin/js/bulk-pricing.js', true)
            ->withVariables([
                'categories' => $categories,
                'cnyIrtRate' => $meta['rate'] ?? null,
                'cnyIrtRateAt' => $meta['effective_at'] ?? null,
                'priceRoundTo' => $this->priceRoundTo(),
                'dataUrl' => route('admin.shop.bulk-pricing.data'),
                'saveUrl' => route('admin.shop.bulk-pricing.save'),
                'ratesUrl' => route('admin.shop.currency-rates.index'),
            ]);

        return $this->view(false);
    }

    public function data(Request $request): JsonResponse
    {
        $categoryId = $request->integer('category_id') ?: null;
        $q = trim((string) $request->input('q', ''));
        $page = max(1, $request->integer('page', 1));
        $perPage = min(50, max(10, $request->integer('per_page', 20)));

        $query = Product::query()
            ->with([
                'combinations' => fn ($c) => $c->orderBy('id')->with(['values.value.attribute']),
            ])
            ->orderBy('name');

        if ($categoryId) {
            $ids = $this->categorySubtreeIds($categoryId);
            $query->whereIn('category_id', $ids);
        }

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', '%'.$q.'%')
                    ->orWhere('sku', 'like', '%'.$q.'%');
            });
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $rate = app(ShopPriceService::class)->currentRateMeta()['rate'] ?? null;
        $roundTo = $this->priceRoundTo();

        $rows = collect($paginator->items())->map(function (Product $product) use ($rate, $roundTo) {
            $cost = $product->cost_cny !== null ? (float) $product->cost_cny : null;
            $sale = $product->sale_price_cny !== null ? (float) $product->sale_price_cny : null;
            $profit = $this->profitPercent($cost, $sale);

            return [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'active' => (bool) $product->active,
                'stock_qty' => (int) ($product->stock_qty ?? 0),
                'max_cart_qty' => (int) ($product->max_cart_qty ?? 0),
                'cost_cny' => $cost,
                'sale_price_cny' => $sale,
                'profit_percent' => $profit,
                'toman' => $this->tomanPreview($sale, $rate, $roundTo),
                'combinations' => $product->combinations->map(function (ProductCombination $c) use ($rate, $roundTo) {
                    $buy = $c->price !== null ? (float) $c->price : null;
                    $sell = $c->sale_price_cny !== null ? (float) $c->sale_price_cny : null;

                    return [
                        'id' => $c->id,
                        'sku' => $c->sku,
                        'label' => $this->combinationLabel($c),
                        'stock_qty' => (int) ($c->stock_qty ?? 0),
                        'cost_cny' => $buy,
                        'sale_price_cny' => $sell,
                        'profit_percent' => $this->profitPercent($buy, $sell),
                        'toman' => $this->tomanPreview($sell, $rate, $roundTo),
                        'active' => (bool) $c->active,
                    ];
                })->values(),
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'rate' => $rate,
            'round_to' => $roundTo,
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'products' => ['nullable', 'array'],
            'products.*.id' => ['required', 'integer', 'exists:products,id'],
            'products.*.cost_cny' => ['nullable', 'numeric', 'min:0'],
            'products.*.sale_price_cny' => ['nullable', 'numeric', 'min:0'],
            'products.*.stock_qty' => ['nullable', 'integer', 'min:0'],
            'products.*.max_cart_qty' => ['nullable', 'integer', 'min:0'],
            'combinations' => ['nullable', 'array'],
            'combinations.*.id' => ['required', 'integer', 'exists:product_combinations,id'],
            'combinations.*.cost_cny' => ['nullable', 'numeric', 'min:0'],
            'combinations.*.sale_price_cny' => ['nullable', 'numeric', 'min:0'],
            'combinations.*.stock_qty' => ['nullable', 'integer', 'min:0'],
        ]);

        $productsUpdated = 0;
        $combosUpdated = 0;

        try {
            DB::transaction(function () use ($payload, &$productsUpdated, &$combosUpdated) {
                foreach ($payload['products'] ?? [] as $row) {
                    $product = Product::query()->findOrFail($row['id']);
                    $product->cost_cny = $this->nullableFloat($row['cost_cny'] ?? null);
                    $product->sale_price_cny = $this->nullableFloat($row['sale_price_cny'] ?? null);
                    if (array_key_exists('stock_qty', $row)) {
                        $product->stock_qty = max(0, (int) ($row['stock_qty'] ?? 0));
                    }
                    if (array_key_exists('max_cart_qty', $row)) {
                        $product->max_cart_qty = max(0, (int) ($row['max_cart_qty'] ?? 0));
                    }
                    $product->save();
                    $productsUpdated++;
                }

                foreach ($payload['combinations'] ?? [] as $row) {
                    $combo = ProductCombination::query()->findOrFail($row['id']);
                    // Combination "خرید" maps to legacy price column (CNY cost).
                    $combo->price = $this->nullableFloat($row['cost_cny'] ?? null);
                    $combo->sale_price_cny = $this->nullableFloat($row['sale_price_cny'] ?? null);
                    if (array_key_exists('stock_qty', $row)) {
                        $combo->stock_qty = max(0, (int) ($row['stock_qty'] ?? 0));
                    }
                    $combo->save();
                    $combosUpdated++;
                }
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => 'ذخیره ناموفق: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => sprintf('%d محصول و %d ترکیب ذخیره شد.', $productsUpdated, $combosUpdated),
            'products_updated' => $productsUpdated,
            'combinations_updated' => $combosUpdated,
        ]);
    }

    protected function priceRoundTo(): int
    {
        return (int) config(
            'shop.currency.round_to',
            config('storefront.currency.round_to', 1000)
        );
    }

    /**
     * @return list<int>
     */
    private function categorySubtreeIds(int $rootId): array
    {
        $all = Category::query()->get(['id', 'parent_id']);
        $ids = [$rootId];
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($all as $cat) {
                if (in_array((int) $cat->parent_id, $ids, true) && ! in_array((int) $cat->id, $ids, true)) {
                    $ids[] = (int) $cat->id;
                    $changed = true;
                }
            }
        }

        return $ids;
    }

    private function combinationLabel(ProductCombination $combo): string
    {
        $parts = [];
        foreach ($combo->values as $cv) {
            $attr = $cv->value->attribute->name ?? null;
            $val = $cv->value->value ?? null;
            if ($val === null || $val === '') {
                continue;
            }
            $parts[] = $attr !== null && $attr !== ''
                ? $attr.': '.$val
                : (string) $val;
        }

        if ($parts !== []) {
            return implode(' / ', $parts);
        }

        if (! empty($combo->sku)) {
            return (string) $combo->sku;
        }

        return '#'.$combo->id;
    }

    private function profitPercent(?float $cost, ?float $sale): ?float
    {
        if ($cost === null || $cost <= 0 || $sale === null) {
            return null;
        }

        return round((($sale / $cost) - 1) * 100, 2);
    }

    private function tomanPreview(?float $saleCny, mixed $rate, int $roundTo): ?int
    {
        if ($saleCny === null || $saleCny <= 0 || ! $rate || (float) $rate <= 0) {
            return null;
        }
        $step = max(1, $roundTo);

        return (int) (ceil(($saleCny * (float) $rate) / $step) * $step);
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
