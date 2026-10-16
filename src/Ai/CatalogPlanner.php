<?php

namespace RMS\Shop\Ai;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RMS\Shop\Models\AiCatalogItem;
use RMS\Shop\Models\AiCatalogPlan;
use RMS\Shop\Models\Brand;
use RMS\Shop\Models\Category;
use RMS\Shop\Models\Product;
use RMS\Shop\Services\ProductAttributesService;

/**
 * Looks at a whole staged product list at once and proposes a category tree,
 * brands and product groups (one product with variants, or separate products).
 * Applying a reviewed plan creates inactive, unpriced products; the per-product
 * enrich step fills their copy and features afterwards.
 */
class CatalogPlanner
{
    public const MODE_VARIANTS = 'variants';
    public const MODE_SEPARATE = 'separate';

    public function __construct(protected AiManager $ai)
    {
    }

    public function queue(string $batch): AiCatalogPlan
    {
        // A new plan replaces older unreviewed ones for the same list.
        AiCatalogPlan::query()
            ->where('batch', $batch)
            ->whereIn('status', [AiCatalogPlan::STATUS_READY, AiCatalogPlan::STATUS_FAILED])
            ->update(['status' => AiCatalogPlan::STATUS_REJECTED]);

        return AiCatalogPlan::query()->create([
            'batch' => $batch,
            'status' => AiCatalogPlan::STATUS_PENDING,
            'driver' => $this->ai->driverName(),
        ]);
    }

    public function run(AiCatalogPlan $plan): AiCatalogPlan
    {
        $items = $this->stagedItems($plan->batch);
        if ($items->isEmpty()) {
            $plan->forceFill(['status' => AiCatalogPlan::STATUS_FAILED, 'error' => 'ردیف واردنشده‌ای در این فایل نمانده است.'])->save();

            return $plan;
        }

        $plan->forceFill(['status' => AiCatalogPlan::STATUS_PROCESSING, 'error' => null])->save();

        $result = $this->ai
            ->driver(null, [
                'max_tokens' => (int) config('shop.ai.catalog_max_tokens', 16000),
                'timeout' => (int) config('shop.ai.catalog_timeout', 300),
            ])
            ->generate($this->systemPrompt(), $this->prompt($items), [], $this->schema());

        $plan->forceFill([
            'status' => AiCatalogPlan::STATUS_READY,
            'driver' => $this->ai->driverName(),
            'model' => $result->model,
            'payload' => $this->normalize($result->data, $items),
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
        ])->save();

        return $plan;
    }

    /**
     * Store a plan written outside the AI call (same shape as the AI answer) for review.
     * Items and suspicious rows may point at a row number of the file instead of an id.
     *
     * @param  array<string, mixed>  $data
     */
    public function fromData(string $batch, array $data, string $source = 'manual'): AiCatalogPlan
    {
        $items = $this->stagedItems($batch);
        $idsByRow = $items->pluck('id', 'row');
        $resolve = function (array $entry) use ($idsByRow): array {
            if (empty($entry['id']) && isset($entry['row'])) {
                $entry['id'] = (int) ($idsByRow[(int) $entry['row']] ?? 0);
            }

            return $entry;
        };

        foreach ((array) ($data['groups'] ?? []) as $g => $group) {
            $data['groups'][$g]['items'] = array_map($resolve, (array) ($group['items'] ?? []));
        }
        $data['suspicious'] = array_map($resolve, (array) ($data['suspicious'] ?? []));

        $plan = $this->queue($batch);
        $plan->forceFill([
            'status' => AiCatalogPlan::STATUS_READY,
            'driver' => 'manual',
            'model' => mb_substr($source, 0, 100),
            'payload' => $this->normalize($data, $items),
        ])->save();

        return $plan;
    }

    /**
     * Create categories, brands and inactive products for the chosen groups.
     *
     * @param  array<int, int>  $groupIndexes  Groups to create; others stay staged
     * @param  array<int, string>  $modes  Admin overrides of a group's mode, by group index
     * @return array<string, mixed>
     */
    public function apply(AiCatalogPlan $plan, array $groupIndexes, array $modes = []): array
    {
        $payload = (array) $plan->payload;
        $groups = (array) ($payload['groups'] ?? []);
        $selected = array_values(array_intersect(array_keys($groups), array_map('intval', $groupIndexes)));

        return DB::transaction(function () use ($plan, $groups, $selected, $modes) {
            $result = ['products' => [], 'categories_created' => [], 'brands_created' => []];
            $categoryIds = [];
            $brandIds = [];

            foreach ($selected as $index) {
                $group = $groups[$index];
                $items = AiCatalogItem::query()
                    ->where('batch', $plan->batch)
                    ->where('status', AiCatalogItem::STATUS_STAGED)
                    ->whereIn('id', array_column($group['items'], 'id'))
                    ->get()
                    ->keyBy('id');
                if ($items->isEmpty()) {
                    continue;
                }

                $path = (string) ($group['category'] ?? '');
                $categoryIds[$path] ??= $path !== ''
                    ? $this->categoryForPath($path, $result['categories_created'])
                    : Category::ensureDefault()?->id;
                $brand = (string) ($group['brand'] ?? '');
                $brandIds[$brand] ??= $brand !== '' ? $this->brandFor($brand, $result['brands_created']) : null;

                $groupItems = array_values(array_filter($group['items'], fn ($i) => $items->has($i['id'])));
                $mode = in_array($modes[$index] ?? null, [self::MODE_VARIANTS, self::MODE_SEPARATE], true)
                    ? $modes[$index]
                    : $group['mode'];
                $base = ['category_id' => $categoryIds[$path], 'brand_id' => $brandIds[$brand]];

                if ($mode === self::MODE_VARIANTS && count($groupItems) > 1) {
                    $product = $this->createProduct($group['name'], $base);
                    $this->addVariants($product, (string) $group['attribute'], $groupItems);
                    $this->markImported($items->keys()->all(), $product->id);
                    $result['products'][] = ['id' => $product->id, 'name' => $product->name, 'variants' => count($groupItems)];
                    continue;
                }

                foreach ($groupItems as $item) {
                    $name = $item['name'] ?: trim($group['name'].' '.$item['value']);
                    $product = $this->createProduct($name, $base);
                    $this->markImported([$item['id']], $product->id);
                    $result['products'][] = ['id' => $product->id, 'name' => $product->name, 'variants' => 0];
                }
            }

            $plan->forceFill([
                'status' => AiCatalogPlan::STATUS_APPLIED,
                'result' => $result,
                'applied_at' => now(),
            ])->save();

            return $result;
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, AiCatalogItem>
     */
    protected function stagedItems(string $batch)
    {
        return AiCatalogItem::query()
            ->where('batch', $batch)
            ->where('status', AiCatalogItem::STATUS_STAGED)
            ->orderBy('row')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $base
     */
    protected function createProduct(string $name, array $base): Product
    {
        return Product::query()->create($base + [
            'name' => mb_substr($name, 0, 190),
            'slug' => $this->uniqueSlug($name, fn ($slug) => Product::withTrashed()->where('slug', $slug)->exists()),
            'active' => false,
            'stock_qty' => 0,
        ]);
    }

    /**
     * @param  array<int, array{id: int, value: string}>  $items
     */
    protected function addVariants(Product $product, string $attribute, array $items): void
    {
        $values = [];
        $combinations = [];
        foreach ($items as $sort => $item) {
            $tmpId = 'item-'.$item['id'];
            $values[] = ['tmpId' => $tmpId, 'value' => $item['value'], 'sort' => $sort];
            $combinations[] = ['attribute_value_ids' => [$tmpId], 'stock' => 0, 'active' => true];
        }

        ProductAttributesService::save($product->id, [[
            'name' => $attribute !== '' ? $attribute : 'مدل',
            'type' => 'text',
            'ui' => 'pill',
            'values' => $values,
        ]], $combinations);
    }

    /**
     * @param  array<int, int>  $ids
     */
    protected function markImported(array $ids, int $productId): void
    {
        AiCatalogItem::query()->whereIn('id', $ids)->update([
            'status' => AiCatalogItem::STATUS_IMPORTED,
            'product_id' => $productId,
        ]);
    }

    /**
     * Find or create each level of "Parent > Child > Grandchild".
     *
     * @param  array<int, int>  $created
     */
    protected function categoryForPath(string $path, array &$created): int
    {
        $parentId = null;
        foreach ($this->splitPath($path) as $name) {
            $category = Category::query()
                ->where('name', $name)
                ->where('parent_id', $parentId)
                ->first();

            if (!$category) {
                $category = Category::query()->create([
                    'name' => $name,
                    'slug' => $this->uniqueSlug($name, fn ($slug) => Category::query()->where('slug', $slug)->exists()),
                    'parent_id' => $parentId,
                    'active' => true,
                    'sort' => 0,
                ]);
                $created[] = $category->id;
            }
            $parentId = $category->id;
        }

        return (int) $parentId;
    }

    /**
     * @param  array<int, int>  $created
     */
    protected function brandFor(string $name, array &$created): int
    {
        $brand = Brand::query()->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();
        if ($brand) {
            return (int) $brand->id;
        }

        $brand = Brand::query()->create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name, fn ($slug) => Brand::query()->where('slug', $slug)->exists()),
            'is_active' => true,
            'sort' => 0,
        ]);
        $created[] = $brand->id;

        return (int) $brand->id;
    }

    /**
     * Slugs keep Persian letters, like slugs typed in the admin.
     */
    protected function uniqueSlug(string $text, callable $exists): string
    {
        $base = mb_substr(Str::slug($text, '-', null), 0, 150) ?: 'item';
        $slug = $base;
        $counter = 2;
        while ($exists($slug)) {
            $slug = $base.'-'.$counter++;
        }

        return $slug;
    }

    /**
     * @return array<int, string>
     */
    protected function splitPath(string $path): array
    {
        $parts = array_values(array_filter(array_map('trim', explode('>', $path)), fn ($p) => $p !== ''));

        return array_slice($parts, 0, 3);
    }

    protected function systemPrompt(): string
    {
        $prompt = <<<'TXT'
You organise the catalog of an Iranian online store. You get its whole raw product list (Persian names, often with a model or size in a second column) and plan how the store should be structured before any product is created.

Rules:
- Category names are short, natural Persian. At most 3 levels, written as a path like "لحیم‌کاری > نوک هویه". Prefer the store's existing categories when they fit. Aim for categories with several products each, not one category per product.
- Brands: detect them from the names (e.g. Mechanic, OSS Team, 2UUL, AMAOE). Keep the brand's usual Latin spelling. Prefer existing brand names when they match.
- Groups: every row id goes into exactly one group.
  - mode "variants": rows that are the same item in different sizes, shapes, capacities or close models a shopper picks from on one page (e.g. the same desoldering wick in 1.5/2.0/2.5 mm, the same glue in 50 and 110 ml). Give the attribute name in Persian (e.g. «سایز»، «قطر»، «حجم»، «مدل»، «شکل نوک») and each row's short value.
  - mode "separate": rows that are really different products, or a single row. Give each row a full product name.
  - Tips of different series (e.g. C210, C115, C245, T12) fit different handles; group them per series rather than mixing series in one product.
- Group name: a clean, searchable Persian product name with the brand and model family in Latin, without the variant value.
- Never invent rows or specs. Keep model numbers exactly as written.
- suspicious: rows that look like typos or spreadsheet autofill mistakes (e.g. a model number that increments row by row while only the size changes, the same size written for "small" and "large", a missing space in a model). Explain briefly in Persian and suggest the likely correct value.
TXT;

        $extra = trim((string) config('shop.ai.store_context', ''));

        return $extra !== '' ? $prompt."\n\nAbout this store: ".$extra : $prompt;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, AiCatalogItem>  $items
     */
    protected function prompt($items): string
    {
        $rows = $items->map(fn (AiCatalogItem $i) => $i->id.' | '.$i->name.' | '.($i->spec ?? ''))->implode("\n");

        $categories = Category::query()->get(['id', 'name', 'parent_id'])->keyBy('id');
        $paths = $categories->map(function ($category) use ($categories) {
            $path = [$category->name];
            $parent = $category->parent_id;
            $guard = 0;
            while ($parent && isset($categories[$parent]) && $guard++ < 10) {
                array_unshift($path, $categories[$parent]->name);
                $parent = $categories[$parent]->parent_id;
            }

            return implode(' > ', $path);
        })->values()->all();

        $brands = Brand::query()->orderBy('name')->limit(500)->pluck('name')->all();

        return implode("\n\n", [
            "Rows (id | name | spec):\n".$rows,
            "Existing categories:\n".($paths === [] ? '(none)' : implode("\n", $paths)),
            "Existing brands:\n".($brands === [] ? '(none)' : implode('، ', $brands)),
            'Plan the catalog and submit it.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function schema(): array
    {
        $text = ['type' => 'string'];

        return [
            'type' => 'object',
            'properties' => [
                'categories' => ['type' => 'array', 'items' => $text, 'description' => 'Category paths, e.g. "لحیم‌کاری > نوک هویه"'],
                'brands' => ['type' => 'array', 'items' => $text],
                'groups' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => $text,
                            'brand' => ['type' => ['string', 'null']],
                            'category' => ['type' => 'string', 'description' => 'One of the category paths'],
                            'mode' => ['type' => 'string', 'enum' => [self::MODE_VARIANTS, self::MODE_SEPARATE]],
                            'attribute' => ['type' => ['string', 'null'], 'description' => 'Persian variant attribute name, for mode variants'],
                            'items' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => ['type' => 'integer'],
                                        'value' => ['type' => 'string', 'description' => 'Variant value, e.g. "2.0 mm"'],
                                        'name' => ['type' => ['string', 'null'], 'description' => 'Full product name, for mode separate'],
                                    ],
                                    'required' => ['id', 'value'],
                                ],
                            ],
                        ],
                        'required' => ['name', 'category', 'mode', 'items'],
                    ],
                ],
                'suspicious' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => ['id' => ['type' => 'integer'], 'reason' => $text, 'suggestion' => $text],
                        'required' => ['id', 'reason'],
                    ],
                ],
                'notes' => $text,
            ],
            'required' => ['categories', 'brands', 'groups', 'suspicious'],
        ];
    }

    /**
     * Keep the plan consistent: every staged row in exactly one group, known
     * ids only, and every category and brand a group uses listed once.
     *
     * @param  array<string, mixed>  $data
     * @param  \Illuminate\Support\Collection<int, AiCatalogItem>  $items
     * @return array<string, mixed>
     */
    public function normalize(array $data, $items): array
    {
        $byId = $items->keyBy('id');
        $assigned = [];
        $groups = [];
        $categories = [];
        $brands = [];

        foreach ((array) ($data['categories'] ?? []) as $path) {
            $path = implode(' > ', $this->splitPath((string) $path));
            if ($path !== '') {
                $categories[$path] = true;
            }
        }
        foreach ((array) ($data['brands'] ?? []) as $brand) {
            $brand = trim((string) $brand);
            if ($brand !== '') {
                $brands[mb_strtolower($brand)] = $brand;
            }
        }

        foreach ((array) ($data['groups'] ?? []) as $group) {
            if (!is_array($group)) {
                continue;
            }

            $groupItems = [];
            $values = [];
            foreach ((array) ($group['items'] ?? []) as $entry) {
                $id = (int) ($entry['id'] ?? 0);
                if (!$byId->has($id) || isset($assigned[$id])) {
                    continue;
                }
                $assigned[$id] = true;

                $value = trim((string) ($entry['value'] ?? '')) ?: ($byId[$id]->spec ?? $byId[$id]->name);
                // Two identical values would give one combination; keep them apart.
                $key = mb_strtolower($value);
                $values[$key] = ($values[$key] ?? 0) + 1;
                if ($values[$key] > 1) {
                    $value .= ' ('.$values[$key].')';
                }

                $groupItems[] = ['id' => $id, 'value' => $value, 'name' => trim((string) ($entry['name'] ?? '')) ?: null];
            }
            if ($groupItems === []) {
                continue;
            }

            $path = implode(' > ', $this->splitPath((string) ($group['category'] ?? '')));
            if ($path !== '') {
                $categories[$path] = true;
            }
            $brand = trim((string) ($group['brand'] ?? ''));
            if ($brand !== '') {
                $brand = $brands[mb_strtolower($brand)] ??= $brand;
            }

            $mode = ($group['mode'] ?? null) === self::MODE_VARIANTS && count($groupItems) > 1
                ? self::MODE_VARIANTS
                : self::MODE_SEPARATE;

            $groups[] = [
                'name' => trim((string) ($group['name'] ?? '')) ?: $byId[$groupItems[0]['id']]->name,
                'brand' => $brand !== '' ? $brand : null,
                'category' => $path,
                'mode' => $mode,
                'attribute' => trim((string) ($group['attribute'] ?? '')) ?: 'مدل',
                'items' => $groupItems,
            ];
        }

        // Rows the model skipped still need a place.
        foreach ($items as $item) {
            if (!isset($assigned[$item->id])) {
                $groups[] = [
                    'name' => $item->label(),
                    'brand' => null,
                    'category' => '',
                    'mode' => self::MODE_SEPARATE,
                    'attribute' => 'مدل',
                    'items' => [['id' => $item->id, 'value' => (string) ($item->spec ?? ''), 'name' => $item->label()]],
                    'unplanned' => true,
                ];
            }
        }

        $suspicious = [];
        foreach ((array) ($data['suspicious'] ?? []) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($byId->has($id) && trim((string) ($row['reason'] ?? '')) !== '') {
                $suspicious[] = [
                    'id' => $id,
                    'reason' => trim((string) $row['reason']),
                    'suggestion' => trim((string) ($row['suggestion'] ?? '')),
                ];
            }
        }

        $paths = array_keys($categories);
        sort($paths);

        return [
            'categories' => $paths,
            'brands' => array_values($brands),
            'groups' => $groups,
            'suspicious' => $suspicious,
            'notes' => trim((string) ($data['notes'] ?? '')),
        ];
    }
}
