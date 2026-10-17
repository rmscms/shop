<?php

namespace RMS\Shop\Http\Controllers\Api\Panel;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use OpenApi\Annotations as OA;
use RMS\Shop\Models\Brand;
use RMS\Shop\Models\Category;
use RMS\Shop\Models\Product;
use RMS\Shop\Services\SeoService;

class SeoController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/seo/products/{slug}",
     *     tags={"SEO"},
     *     summary="Head tags and JSON-LD for a product page",
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="title, description, canonical, robots, open_graph, twitter, json_ld"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function product(string $slug, SeoService $seo)
    {
        $product = Product::query()->where('slug', $slug)->orWhere('id', ctype_digit($slug) ? (int) $slug : 0)->firstOrFail();

        return $this->apiSuccess($seo->forProduct($product));
    }

    /**
     * @OA\Get(
     *     path="/seo/categories/{slug}",
     *     tags={"SEO"},
     *     summary="Head tags and JSON-LD for a category page",
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="title, description, canonical, robots, content, open_graph, json_ld"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function category(string $slug, SeoService $seo)
    {
        return $this->apiSuccess($seo->forCategory(Category::query()->where('slug', $slug)->firstOrFail()));
    }

    /**
     * @OA\Get(
     *     path="/seo/brands/{slug}",
     *     tags={"SEO"},
     *     summary="Head tags and JSON-LD for a brand page",
     *     @OA\Parameter(name="slug", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="title, description, canonical, robots, open_graph, json_ld"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function brand(string $slug, SeoService $seo)
    {
        return $this->apiSuccess($seo->forBrand(Brand::query()->where('slug', $slug)->firstOrFail()));
    }

    /**
     * @OA\Get(
     *     path="/seo/sitemap.xml",
     *     tags={"SEO"},
     *     summary="XML sitemap of indexable products, categories and brands (storefront URLs, with images)",
     *     @OA\Response(response=200, description="application/xml")
     * )
     */
    public function sitemap(SeoService $seo): Response
    {
        abort_unless(config('shop.seo.sitemap.enabled', true), 404);

        $xml = Cache::remember('shop:seo:sitemap', now()->addMinutes((int) config('shop.seo.sitemap.cache_minutes', 60)), function () use ($seo) {
            $entries = [];

            Product::query()
                ->where('active', true)
                ->where(fn ($q) => $q->where('noindex', false)->orWhereNull('noindex'))
                ->with('assignedImages')
                ->orderBy('id')
                ->chunk(200, function ($products) use (&$entries, $seo) {
                    foreach ($products as $product) {
                        $entries[] = [
                            'loc' => $product->canonical_url ?: $seo->productUrl($product),
                            'lastmod' => $product->updated_at,
                            'images' => collect($seo->productImages($product))->pluck('url')->all(),
                        ];
                    }
                });

            foreach (Category::query()->where('active', true)->where(fn ($q) => $q->where('noindex', false)->orWhereNull('noindex'))->get() as $category) {
                $entries[] = ['loc' => $seo->url('category', $category->slug), 'lastmod' => $category->updated_at, 'images' => []];
            }
            foreach (Brand::query()->where('is_active', true)->get() as $brand) {
                $entries[] = ['loc' => $seo->url('brand', $brand->slug), 'lastmod' => $brand->updated_at, 'images' => []];
            }

            return view('shop::seo.sitemap', ['entries' => $entries])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
