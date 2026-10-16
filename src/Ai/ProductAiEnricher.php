<?php

namespace RMS\Shop\Ai;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RMS\Shop\Models\AiSuggestion;
use RMS\Shop\Models\Brand;
use RMS\Shop\Models\Category;
use RMS\Shop\Models\Product;
use RMS\Shop\Models\ProductFeature;
use RMS\Shop\Models\ProductFeatureCategory;
use RMS\Shop\Services\ProductFeaturesService;

/**
 * Builds the product prompt, calls the configured AI driver and turns the
 * answer into a reviewable suggestion. Applying a suggestion is a separate,
 * explicit step so nothing on the product changes without an admin.
 */
class ProductAiEnricher
{
    public const APPLICABLE_FIELDS = ['name', 'short_desc', 'description', 'category_id', 'brand_id', 'features'];

    protected const SUPPORTED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function __construct(
        protected AiManager $ai,
        protected ProductFeaturesService $features,
    ) {
    }

    public function queue(Product $product): AiSuggestion
    {
        // One open suggestion per product: a re-run replaces the unreviewed one.
        AiSuggestion::query()
            ->where('product_id', $product->id)
            ->whereIn('status', [AiSuggestion::STATUS_READY, AiSuggestion::STATUS_FAILED])
            ->delete();

        return AiSuggestion::query()->create([
            'product_id' => $product->id,
            'status' => AiSuggestion::STATUS_PENDING,
            'driver' => $this->ai->driverName(),
        ]);
    }

    public function run(AiSuggestion $suggestion): AiSuggestion
    {
        $product = Product::query()->with(['category', 'brand'])->find($suggestion->product_id);
        if (!$product) {
            $suggestion->forceFill(['status' => AiSuggestion::STATUS_FAILED, 'error' => 'محصول پیدا نشد.'])->save();

            return $suggestion;
        }

        $suggestion->forceFill(['status' => AiSuggestion::STATUS_PROCESSING, 'error' => null])->save();

        $images = $this->collectImages($product);
        $result = $this->ai->driver()->generate(
            $this->systemPrompt(),
            $this->productPrompt($product, count($images)),
            $images,
            $this->schema(),
        );

        $suggestion->forceFill([
            'status' => AiSuggestion::STATUS_READY,
            'driver' => $this->ai->driverName(),
            'model' => $result->model,
            'payload' => $this->normalize(['sources' => $result->sources] + $result->data),
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'images_sent' => count($images),
        ])->save();

        return $suggestion;
    }

    /**
     * Store a suggestion written outside the AI call (same shape as the AI answer) for review.
     *
     * @param  array<string, mixed>  $data
     */
    public function fromData(Product $product, array $data, string $source = 'manual'): AiSuggestion
    {
        $suggestion = $this->queue($product);
        $suggestion->forceFill([
            'status' => AiSuggestion::STATUS_READY,
            'driver' => 'manual',
            'model' => mb_substr($source, 0, 100),
            'payload' => $this->normalize($data),
        ])->save();

        return $suggestion;
    }

    /**
     * @param  array<int, string>  $fields
     */
    public function apply(AiSuggestion $suggestion, array $fields): Product
    {
        $payload = (array) $suggestion->payload;
        $fields = array_values(array_intersect(self::APPLICABLE_FIELDS, $fields));
        $product = Product::query()->findOrFail($suggestion->product_id);

        DB::transaction(function () use ($product, $payload, $fields, $suggestion) {
            $updates = [];
            foreach (['name', 'short_desc', 'description'] as $field) {
                if (in_array($field, $fields, true) && filled($payload[$field] ?? null)) {
                    $updates[$field] = (string) $payload[$field];
                }
            }
            foreach (['category_id' => Category::class, 'brand_id' => Brand::class] as $field => $model) {
                $id = $payload[$field] ?? null;
                if (in_array($field, $fields, true) && $id && $model::query()->whereKey($id)->exists()) {
                    $updates[$field] = (int) $id;
                }
            }

            if ($updates !== []) {
                $product->fill($updates)->save();
            }

            if (in_array('features', $fields, true) && !empty($payload['feature_groups'])) {
                $this->features->saveFeatures($product->id, [
                    'categories' => $this->mergeFeatures($product->id, (array) $payload['feature_groups']),
                ]);
            }

            $suggestion->forceFill([
                'status' => AiSuggestion::STATUS_APPLIED,
                'applied_fields' => $fields,
                'applied_at' => now(),
            ])->save();
        });

        return $product->refresh();
    }

    /**
     * Keep the product's current features and add the suggested ones that are missing.
     *
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<int, array<string, mixed>>
     */
    protected function mergeFeatures(int $productId, array $groups): array
    {
        $existing = ProductFeature::query()
            ->where('product_id', $productId)
            ->orderBy('sort')
            ->get();

        $byCategory = [];
        $names = [];
        foreach ($existing as $feature) {
            $key = 'id:'.(int) $feature->category_id;
            $byCategory[$key] ??= ['category_id' => $feature->category_id ?: null, 'features' => []];
            $byCategory[$key]['features'][] = [
                'feature_id' => $feature->feature_definition_id,
                'value_id' => $feature->feature_value_id,
                'name' => $feature->name,
                'value' => $feature->value,
            ];
            $names[mb_strtolower(trim((string) $feature->name))] = true;
        }

        foreach ($groups as $group) {
            $groupName = trim((string) ($group['group'] ?? ''));
            $category = $groupName !== ''
                ? ProductFeatureCategory::query()->where('name', $groupName)->first()
                : null;
            $key = $category ? 'id:'.$category->id : 'name:'.$groupName;
            $byCategory[$key] ??= $category
                ? ['category_id' => $category->id, 'features' => []]
                : ['category_name' => $groupName ?: 'مشخصات', 'features' => []];

            foreach ((array) ($group['features'] ?? []) as $feature) {
                $name = trim((string) ($feature['name'] ?? ''));
                $value = trim((string) ($feature['value'] ?? ''));
                if ($name === '' || $value === '' || isset($names[mb_strtolower($name)])) {
                    continue;
                }
                $names[mb_strtolower($name)] = true;
                $byCategory[$key]['features'][] = ['name' => $name, 'value' => $value];
            }
        }

        return array_values(array_filter($byCategory, fn ($group) => $group['features'] !== []));
    }

    /**
     * @return array<int, AiImage>
     */
    protected function collectImages(Product $product): array
    {
        $max = (int) config('shop.ai.max_images', 3);
        if ($max <= 0) {
            return [];
        }

        $paths = $product->assignedImages()->limit($max)->pluck('image_library.path')->all();
        if (count($paths) < $max) {
            $legacy = $product->images()->orderByDesc('is_main')->orderBy('sort')->limit($max)->pluck('path')->all();
            $paths = array_slice(array_values(array_unique(array_merge($paths, $legacy))), 0, $max);
        }

        $disk = Storage::disk('public');
        $maxBytes = (int) config('shop.ai.max_image_kb', 4500) * 1024;
        $images = [];

        foreach ($paths as $path) {
            $path = (string) $path;
            if ($path === '' || !$disk->exists($path) || $disk->size($path) > $maxBytes) {
                continue;
            }
            $mime = (string) $disk->mimeType($path);
            if (!in_array($mime, self::SUPPORTED_MIME, true)) {
                continue;
            }
            $images[] = new AiImage($mime, base64_encode((string) $disk->get($path)));
        }

        return $images;
    }

    protected function systemPrompt(): string
    {
        $prompt = <<<'TXT'
You are the catalog editor of an Iranian online store. You write product content in fluent, natural Persian (Farsi) for shoppers and search engines.

Rules:
- All text you write is in Persian, even when the source is Chinese or English. Keep model numbers, units and brand names in their original Latin form.
- Never invent specifications. Only state what the product data or the photos show. If something is unclear, leave it out.
- Product name: short and searchable, like "کفش ورزشی مردانه نایکی مدل Air Zoom". No marketing words.
- short_desc: one or two sentences.
- description: simple HTML using only <p>, <h3>, <ul>, <li>, <strong>. Around 150 to 300 words. Describe what is visible in the photos (material, color, design, usage).
- category_id and brand_id must be ids from the given lists, or null when nothing fits.
- Features: group them with a short Persian group name (prefer the existing group names given). Reuse existing feature names when they mean the same thing.
TXT;

        if ($this->ai->searchEnabled()) {
            $prompt .= "\n- Web search is available: use it to verify specs from the manufacturer page or datasheet (search the exact model or part number), and prefer those over guesses. Say in notes which specs came from the web.";
        }

        $extra = trim((string) config('shop.ai.store_context', ''));

        return $extra !== '' ? $prompt."\n\nAbout this store: ".$extra : $prompt;
    }

    protected function productPrompt(Product $product, int $imageCount): string
    {
        $data = [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'current_category' => $product->category?->name,
            'current_brand' => $product->brand?->name,
            'short_desc' => Str::limit(strip_tags((string) $product->short_desc), 1000),
            'description' => Str::limit(strip_tags((string) $product->description), 4000),
            'current_features' => ProductFeature::query()
                ->where('product_id', $product->id)
                ->get(['name', 'value'])
                ->map(fn ($f) => $f->name.': '.$f->value)
                ->all(),
            'photos_attached' => $imageCount,
        ];

        $sections = [
            "Product data (JSON):\n".json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            "Categories (id: path):\n".$this->categoryList(),
            "Brands (id: name):\n".$this->brandList(),
            "Existing feature groups:\n".$this->featureGroupList(),
            "Common feature names already used in the store:\n".$this->commonFeatureNames(),
            'Look at the photos carefully, then submit the suggestion.',
        ];

        return implode("\n\n", $sections);
    }

    protected function categoryList(): string
    {
        $rows = Category::query()->get(['id', 'name', 'parent_id'])->keyBy('id');
        $lines = [];
        foreach ($rows as $row) {
            $path = [$row->name];
            $parent = $row->parent_id;
            $guard = 0;
            while ($parent && isset($rows[$parent]) && $guard++ < 10) {
                array_unshift($path, $rows[$parent]->name);
                $parent = $rows[$parent]->parent_id;
            }
            $lines[] = $row->id.': '.implode(' > ', $path);
        }

        return $lines === [] ? '(none)' : implode("\n", array_slice($lines, 0, 500));
    }

    protected function brandList(): string
    {
        $lines = Brand::query()->orderBy('name')->limit(500)->get(['id', 'name'])
            ->map(fn ($b) => $b->id.': '.$b->name)->all();

        return $lines === [] ? '(none)' : implode("\n", $lines);
    }

    protected function featureGroupList(): string
    {
        $names = ProductFeatureCategory::query()->orderBy('sort')->limit(100)->pluck('name')->all();

        return $names === [] ? '(none)' : implode('، ', $names);
    }

    protected function commonFeatureNames(): string
    {
        $names = ProductFeature::query()
            ->select('name', DB::raw('count(*) as uses'))
            ->groupBy('name')
            ->orderByDesc('uses')
            ->limit(80)
            ->pluck('name')
            ->all();

        return $names === [] ? '(none)' : implode('، ', $names);
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
                'source_language' => ['type' => 'string', 'description' => 'Language of the original product data, e.g. fa, en, zh'],
                'name' => $text,
                'short_desc' => $text,
                'description' => ['type' => 'string', 'description' => 'Persian HTML description'],
                'seo_title' => ['type' => 'string', 'description' => 'Up to 60 characters'],
                'seo_description' => ['type' => 'string', 'description' => 'Up to 160 characters'],
                'keywords' => ['type' => 'array', 'items' => $text],
                'image_alt' => ['type' => 'string', 'description' => 'Persian alt text for the main photo'],
                'category_id' => ['type' => ['integer', 'null']],
                'category_reason' => $text,
                'new_category_name' => ['type' => ['string', 'null'], 'description' => 'Only when no listed category fits'],
                'brand_id' => ['type' => ['integer', 'null']],
                'detected_brand' => ['type' => ['string', 'null'], 'description' => 'Brand seen in the photos or text, even if not in the list'],
                'feature_groups' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'group' => $text,
                            'features' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => ['name' => $text, 'value' => $text],
                                    'required' => ['name', 'value'],
                                ],
                            ],
                        ],
                        'required' => ['group', 'features'],
                    ],
                ],
                'notes' => ['type' => 'string', 'description' => 'Anything the admin should double-check'],
            ],
            'required' => ['name', 'short_desc', 'description', 'seo_title', 'seo_description', 'keywords', 'category_id', 'brand_id', 'feature_groups'],
        ];
    }

    /**
     * Drop ids the model made up and trim text fields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalize(array $data): array
    {
        foreach (['category_id' => Category::class, 'brand_id' => Brand::class] as $field => $model) {
            $id = isset($data[$field]) && is_numeric($data[$field]) ? (int) $data[$field] : null;
            $data[$field] = $id && $model::query()->whereKey($id)->exists() ? $id : null;
        }

        foreach (['name', 'short_desc', 'description', 'seo_title', 'seo_description', 'image_alt', 'notes'] as $field) {
            $data[$field] = trim((string) ($data[$field] ?? ''));
        }

        // Keep a few formatting tags and strip their attributes.
        $data['description'] = preg_replace(
            '/<(\/?)(p|h3|h4|ul|ol|li|strong|em|br)\b[^>]*>/i',
            '<$1$2>',
            strip_tags($data['description'], '<p><h3><h4><ul><ol><li><strong><em><br>')
        );
        $data['keywords'] = array_values(array_filter(array_map('strval', (array) ($data['keywords'] ?? []))));
        $data['feature_groups'] = array_values(array_filter((array) ($data['feature_groups'] ?? []), 'is_array'));

        // Only web links, so the review page never renders other schemes.
        $sources = [];
        foreach ((array) ($data['sources'] ?? []) as $source) {
            $url = trim((string) ($source['url'] ?? ''));
            if (preg_match('#^https?://#i', $url)) {
                $sources[] = ['title' => trim((string) ($source['title'] ?? '')) ?: $url, 'url' => $url];
            }
        }
        $data['sources'] = $sources;

        return $data;
    }
}
