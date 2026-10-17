<?php

namespace RMS\Shop\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RMS\Shop\Models\Brand;
use RMS\Shop\Models\Category;
use RMS\Shop\Models\ImageLibrary;
use RMS\Shop\Models\ImageSizeVariant;
use RMS\Shop\Models\Product;
use RMS\Shop\Models\ProductCombination;

/**
 * Builds everything a storefront page needs in <head>: title, description,
 * canonical, robots, Open Graph, Twitter card and schema.org JSON-LD.
 * Empty SEO fields fall back to the product's own name and text.
 */
class SeoService
{
    public function __construct(protected ShopPriceService $prices)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function forProduct(Product $product): array
    {
        $product->loadMissing(['category', 'brand', 'assignedImages']);

        $url = $this->productUrl($product);
        $title = $this->title($product->meta_title ?: $product->name);
        $description = $this->description($product->meta_description, $product->short_desc, $product->description);
        $images = $this->productImages($product);
        $indexable = $product->active && !$product->noindex;

        $combinations = $product->combinations()
            ->where('active', true)
            ->with(['values'])
            ->get();

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $product->canonical_url ?: $url,
            'robots' => $indexable ? 'index,follow' : 'noindex,follow',
            'focus_keyword' => $product->focus_keyword,
            'open_graph' => array_filter([
                'og:type' => 'product',
                'og:site_name' => $this->siteName(),
                'og:locale' => 'fa_IR',
                'og:title' => $title,
                'og:description' => $description,
                'og:url' => $url,
                'og:image' => $images[0]['url'] ?? $this->defaultImage(),
                'og:image:alt' => $images[0]['alt'] ?? $product->name,
                'product:price:amount' => ($price = $this->displayPrice($product->sale_price_cny ?? $product->cost_cny, $product->sale_price ?? $product->price)) !== null ? (string) $price : null,
                'product:price:currency' => $price !== null ? $this->prices->displayCurrency() : null,
            ], fn ($v) => $v !== null && $v !== ''),
            'twitter' => array_filter([
                'twitter:card' => 'summary_large_image',
                'twitter:title' => $title,
                'twitter:description' => $description,
                'twitter:image' => $images[0]['url'] ?? $this->defaultImage(),
            ]),
            'json_ld' => array_values(array_filter([
                $combinations->isNotEmpty()
                    ? $this->productGroupSchema($product, $combinations, $url, $description, $images)
                    : $this->productSchema($product, $url, $description, $images),
                $this->breadcrumbSchema($product->category, ['name' => $product->name, 'url' => $url]),
            ])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forCategory(Category $category, int $productLimit = 30): array
    {
        $url = $this->url('category', $category->slug);
        $title = $this->title($category->meta_title ?: $category->name);
        $description = $this->description($category->meta_description, $category->seo_content, 'خرید '.$category->name);

        $products = Product::query()
            ->where('category_id', $category->id)
            ->where('active', true)
            ->orderBy('id')
            ->limit($productLimit)
            ->get(['id', 'name', 'slug']);

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $url,
            'robots' => $category->active && !$category->noindex ? 'index,follow' : 'noindex,follow',
            'content' => $category->seo_content,
            'open_graph' => array_filter([
                'og:type' => 'website',
                'og:site_name' => $this->siteName(),
                'og:locale' => 'fa_IR',
                'og:title' => $title,
                'og:description' => $description,
                'og:url' => $url,
                'og:image' => $this->defaultImage(),
            ]),
            'twitter' => array_filter(['twitter:card' => 'summary', 'twitter:title' => $title, 'twitter:description' => $description]),
            'json_ld' => array_values(array_filter([
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'CollectionPage',
                    'name' => $category->name,
                    'description' => $description,
                    'url' => $url,
                    'mainEntity' => [
                        '@type' => 'ItemList',
                        'numberOfItems' => $products->count(),
                        'itemListElement' => $products->values()->map(fn (Product $p, int $i) => [
                            '@type' => 'ListItem',
                            'position' => $i + 1,
                            'url' => $this->productUrl($p),
                            'name' => $p->name,
                        ])->all(),
                    ],
                ],
                $this->breadcrumbSchema($category->parent_id ? $category->parent : null, ['name' => $category->name, 'url' => $url]),
            ])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forBrand(Brand $brand): array
    {
        $url = $this->url('brand', $brand->slug);
        $title = $this->title($brand->meta_title ?: 'محصولات '.$brand->name);
        $description = $this->description($brand->meta_description, $brand->description, 'خرید محصولات '.$brand->name);

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $url,
            'robots' => $brand->is_active ? 'index,follow' : 'noindex,follow',
            'open_graph' => array_filter([
                'og:type' => 'website',
                'og:site_name' => $this->siteName(),
                'og:locale' => 'fa_IR',
                'og:title' => $title,
                'og:description' => $description,
                'og:url' => $url,
                'og:image' => $this->defaultImage(),
            ]),
            'twitter' => array_filter(['twitter:card' => 'summary', 'twitter:title' => $title, 'twitter:description' => $description]),
            'json_ld' => [[
                '@context' => 'https://schema.org',
                '@type' => 'Brand',
                'name' => $brand->name,
                'url' => $url,
                'description' => $description,
            ]],
        ];
    }

    /**
     * Validation rules for the product SEO fields (posted as seo[...]).
     *
     * @return array<string, array<int, string>>
     */
    public static function productFieldRules(): array
    {
        return [
            'meta_title' => ['nullable', 'string', 'max:120'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'focus_keyword' => ['nullable', 'string', 'max:100'],
            'canonical_url' => ['nullable', 'url', 'max:255'],
            'noindex' => ['nullable', 'boolean'],
            'gtin' => ['nullable', 'string', 'max:32', 'regex:/^\d{8}(\d{4,6})?$/'],
            'mpn' => ['nullable', 'string', 'max:64'],
            'image_alt' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Save validated SEO fields; image_alt goes to the product's main image.
     *
     * @param  array<string, mixed>  $fields
     */
    public function saveProductFields(Product $product, array $fields): Product
    {
        $columns = ['meta_title', 'meta_description', 'focus_keyword', 'canonical_url', 'gtin', 'mpn'];
        $updates = [];
        foreach ($columns as $column) {
            if (array_key_exists($column, $fields)) {
                $value = trim((string) ($fields[$column] ?? ''));
                $updates[$column] = $value !== '' ? $value : null;
            }
        }
        if (array_key_exists('noindex', $fields)) {
            $updates['noindex'] = (bool) $fields['noindex'];
        }
        if ($updates !== []) {
            $product->forceFill($updates)->save();
        }

        if (array_key_exists('image_alt', $fields)) {
            $main = $product->assignedImages()->first();
            $main?->forceFill(['alt' => trim((string) $fields['image_alt']) ?: null])->save();
        }

        return $product;
    }

    public function productUrl(Product $product): string
    {
        return $this->url('product', $product->slug);
    }

    public function url(string $type, ?string $slug): string
    {
        $path = (string) config('shop.seo.paths.'.$type, '/'.$type.'/{slug}');

        return $this->storefrontUrl().str_replace('{slug}', rawurlencode((string) $slug), $path);
    }

    public function storefrontUrl(): string
    {
        return rtrim((string) (config('shop.seo.storefront_url') ?: config('app.url')), '/');
    }

    /**
     * Title within the length search engines show; the site suffix is added only when it fits.
     */
    public function title(?string $title): string
    {
        $title = $this->clean($title);
        $max = (int) config('shop.seo.title_max', 60);
        $suffix = (string) config('shop.seo.title_suffix', '');

        if ($suffix !== '' && mb_strlen($title.$suffix) <= $max) {
            return $title.$suffix;
        }

        return $this->cut($title, $max);
    }

    /**
     * First non-empty source, as plain text cut on a word boundary.
     */
    public function description(?string ...$sources): string
    {
        foreach ($sources as $source) {
            $text = $this->clean($source);
            if ($text !== '') {
                return $this->cut($text, (int) config('shop.seo.description_max', 155));
            }
        }

        return '';
    }

    /**
     * @param  array<int, array{url: string, alt: string|null, variant: string|null}>  $images
     * @return array<string, mixed>
     */
    protected function productSchema(Product $product, string $url, string $description, array $images): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            '@id' => $url.'#product',
            'name' => $product->name,
            'description' => $description,
            'url' => $url,
            'image' => $this->schemaImages($images),
            'sku' => $product->sku ?: (string) $product->id,
            'mpn' => $product->mpn,
            'gtin' => $product->gtin,
            'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
            'category' => $product->category?->name,
            'offers' => $this->offer(
                $url,
                $this->displayPrice($product->sale_price_cny ?? $product->cost_cny, $product->sale_price ?? $product->price),
                (int) $product->stock_qty
            ),
        ], fn ($v) => $v !== null && $v !== [] && $v !== '');
    }

    /**
     * Google's variant markup: one ProductGroup with a Product per combination.
     *
     * @param  Collection<int, ProductCombination>  $combinations
     * @param  array<int, array{url: string, alt: string|null, variant: string|null}>  $images
     * @return array<string, mixed>
     */
    protected function productGroupSchema(Product $product, Collection $combinations, string $url, string $description, array $images): array
    {
        $variesBy = [];
        $variants = [];

        foreach ($combinations as $combination) {
            $labels = [];
            foreach ($combination->values as $value) {
                $attribute = $value->value?->attribute?->name;
                $label = $value->value?->value;
                if ($attribute && $label !== null) {
                    $labels[] = $label;
                    $variesBy[$attribute] = $this->variesByProperty($attribute);
                }
            }

            $variantUrl = $url.'?variant='.$combination->id;
            $variants[] = array_filter([
                '@type' => 'Product',
                'name' => trim($product->name.' '.implode(' ', $labels)),
                'sku' => $combination->sku ?: $product->id.'-'.$combination->id,
                'url' => $variantUrl,
                'image' => $this->schemaImages($images),
                'offers' => $this->offer(
                    $variantUrl,
                    $this->displayPrice($combination->sale_price_cny, $combination->sale_price),
                    (int) $combination->stock_qty
                ),
            ], fn ($v) => $v !== null && $v !== [] && $v !== '');
        }

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'ProductGroup',
            '@id' => $url.'#product',
            'name' => $product->name,
            'description' => $description,
            'url' => $url,
            'image' => $this->schemaImages($images),
            'productGroupID' => $product->sku ?: (string) $product->id,
            'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
            'category' => $product->category?->name,
            'variesBy' => array_values(array_unique($variesBy)),
            'hasVariant' => $variants,
        ], fn ($v) => $v !== null && $v !== [] && $v !== '');
    }

    /**
     * Google lists a few standard properties; anything else is described by its own name.
     */
    protected function variesByProperty(string $attribute): string
    {
        return match (true) {
            str_contains($attribute, 'رنگ') => 'https://schema.org/color',
            str_contains($attribute, 'سایز'), str_contains($attribute, 'اندازه') => 'https://schema.org/size',
            str_contains($attribute, 'جنس') => 'https://schema.org/material',
            default => $attribute,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function offer(string $url, ?float $displayPrice, int $stock): ?array
    {
        if ($displayPrice === null || $displayPrice <= 0) {
            // Google requires a price on offers; without one, leave offers out.
            return null;
        }

        $inStock = config('shop.seo.always_in_stock', true) || $stock > 0;
        $currency = (string) config('shop.seo.schema_currency', 'IRR');
        $schemaPrice = (int) round($displayPrice * (float) config('shop.seo.schema_price_multiplier', 10));

        return array_filter([
            '@type' => 'Offer',
            'url' => $url,
            'price' => $schemaPrice,
            'priceCurrency' => $currency,
            'priceValidUntil' => now()->addDays((int) config('shop.seo.price_valid_days', 30))->toDateString(),
            'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            'itemCondition' => (string) config('shop.seo.item_condition', 'https://schema.org/NewCondition'),
            'seller' => ['@type' => 'Organization', 'name' => $this->siteName()],
            'shippingDetails' => $this->shippingDetails($currency),
            'hasMerchantReturnPolicy' => $this->returnPolicy(),
        ], fn ($v) => $v !== null);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function shippingDetails(string $currency): ?array
    {
        $rate = config('shop.seo.shipping.rate');
        if ($rate === null || $rate === '') {
            return null;
        }
        [$handlingMin, $handlingMax] = (array) config('shop.seo.shipping.handling_days', [0, 1]);
        [$transitMin, $transitMax] = (array) config('shop.seo.shipping.transit_days', [1, 5]);

        return [
            '@type' => 'OfferShippingDetails',
            'shippingRate' => [
                '@type' => 'MonetaryAmount',
                'value' => (int) round((float) $rate * (float) config('shop.seo.schema_price_multiplier', 10)),
                'currency' => $currency,
            ],
            'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => (string) config('shop.seo.shipping.country', 'IR')],
            'deliveryTime' => [
                '@type' => 'ShippingDeliveryTime',
                'handlingTime' => ['@type' => 'QuantitativeValue', 'minValue' => (int) $handlingMin, 'maxValue' => (int) $handlingMax, 'unitCode' => 'DAY'],
                'transitTime' => ['@type' => 'QuantitativeValue', 'minValue' => (int) $transitMin, 'maxValue' => (int) $transitMax, 'unitCode' => 'DAY'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function returnPolicy(): ?array
    {
        $days = config('shop.seo.return_policy.days');
        if ($days === null || $days === '') {
            return null;
        }

        return [
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => (string) config('shop.seo.return_policy.country', 'IR'),
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => (int) $days,
        ];
    }

    /**
     * @param  array{name: string, url: string}  $current
     * @return array<string, mixed>
     */
    protected function breadcrumbSchema(?Category $category, array $current): array
    {
        $trail = [];
        $guard = 0;
        while ($category && $guard++ < 10) {
            array_unshift($trail, ['name' => $category->name, 'url' => $this->url('category', $category->slug)]);
            $category = $category->parent_id ? $category->parent : null;
        }
        array_unshift($trail, ['name' => $this->siteName(), 'url' => $this->storefrontUrl().'/']);
        $trail[] = $current;

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn ($item, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ], $trail, array_keys($trail)),
        ];
    }

    /**
     * Product photos as absolute URLs: the original (shareable everywhere) and the large variant.
     *
     * @return array<int, array{url: string, alt: string|null, variant: string|null}>
     */
    public function productImages(Product $product): array
    {
        $sizeSlug = (string) config('shop.seo.image_size', 'large');
        $images = [];

        /** @var ImageLibrary $image */
        foreach ($product->assignedImages as $image) {
            $variant = ImageSizeVariant::query()
                ->where('image_id', $image->id)
                ->whereHas('size', fn ($q) => $q->where('slug', $sizeSlug))
                ->first();

            $images[] = [
                'url' => $image->url,
                'alt' => $image->alt ?: $product->name,
                'variant' => $variant?->url,
            ];
        }

        return $images;
    }

    /**
     * @param  array<int, array{url: string, alt: string|null, variant: string|null}>  $images
     * @return array<int, string>
     */
    protected function schemaImages(array $images): array
    {
        $urls = [];
        foreach ($images as $image) {
            $urls[] = $image['url'];
            if ($image['variant']) {
                $urls[] = $image['variant'];
            }
        }
        if ($urls === [] && ($default = $this->defaultImage())) {
            $urls[] = $default;
        }

        return array_values(array_unique($urls));
    }

    protected function displayPrice(mixed $baseCny, mixed $fallbackDisplay): ?float
    {
        $converted = $baseCny !== null ? $this->prices->convertFromBase((float) $baseCny, 'seo') : null;
        if ($converted === null && $fallbackDisplay !== null) {
            $converted = (float) $fallbackDisplay;
        }

        return $converted !== null && $converted > 0 ? (float) $converted : null;
    }

    protected function defaultImage(): ?string
    {
        $image = config('shop.seo.default_image');
        if (!$image) {
            return null;
        }

        return Str::startsWith($image, ['http://', 'https://']) ? $image : $this->storefrontUrl().'/'.ltrim($image, '/');
    }

    protected function siteName(): string
    {
        return (string) config('shop.seo.site_name', config('app.name', 'Shop'));
    }

    protected function clean(?string $text): string
    {
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    protected function cut(string $text, int $max): string
    {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max - 1);
        $space = mb_strrpos($cut, ' ');
        if ($space !== false && $space > $max * 0.6) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, " ،,.؛:-").'…';
    }
}
