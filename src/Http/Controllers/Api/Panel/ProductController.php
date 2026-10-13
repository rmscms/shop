<?php

namespace RMS\Shop\Http\Controllers\Api\Panel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RMS\Shop\Http\Resources\Panel\ProductDetailResource;
use RMS\Shop\Http\Resources\Panel\ProductResource;
use RMS\Shop\Models\Category;
use RMS\Shop\Models\Product;
use RMS\Shop\Services\ShopPriceService;

class ProductController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/products",
     *     tags={"Catalog"},
     *     summary="List products",
     *     @OA\Parameter(name="q", in="query", description="Search term", @OA\Schema(type="string")),
     *     @OA\Parameter(name="category_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="category_slug", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="brand_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="active", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="min_price", in="query", @OA\Schema(type="number", format="float")),
     *     @OA\Parameter(name="max_price", in="query", @OA\Schema(type="number", format="float")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/ProductResource")
     *             ),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginatedMeta")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));

        $query = Product::query()
            ->with([
                'category:id,name,slug,parent_id',
                'brand:id,name,slug,description,sort',
                'assignedImages' => function ($q) {
                    $q->orderByPivot('is_main', 'desc')
                        ->orderByPivot('sort');
                },
                'assignedVideos' => function ($q) {
                    $q->where('video_assignments.is_main', true);
                },
            ]);

        $query->when($request->filled('q'), function (Builder $builder) use ($request) {
            $term = trim((string) $request->input('q'));
            $builder->where(function (Builder $inner) use ($term) {
                $inner->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('slug', 'like', "%{$term}%");
            });
        });

        $categoryIds = $this->resolveCategoryIds($request);
        if ($categoryIds !== null) {
            if ($categoryIds === []) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereIn('category_id', $categoryIds);
            }
        }

        $query->when($request->filled('brand_id'), function (Builder $builder) use ($request) {
            $builder->where('brand_id', (int) $request->input('brand_id'));
        });

        $query->when($request->filled('slug'), function (Builder $builder) use ($request) {
            $builder->whereIn('slug', $this->slugLookupValues((string) $request->input('slug')));
        });

        if (!is_null($request->input('active'))) {
            $query->where('active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
        }

        $priceService = app(ShopPriceService::class);

        if ($request->filled('min_price')) {
            $displayMin = (float) $request->input('min_price');
            $this->applyDisplayPriceFilter($query, $priceService->convertToBase($displayMin), $displayMin, '>=');
        }

        if ($request->filled('max_price')) {
            $displayMax = (float) $request->input('max_price');
            $this->applyDisplayPriceFilter($query, $priceService->convertToBase($displayMax), $displayMax, '<=');
        }

        $query->orderByDesc('id');

        $products = $query->paginate($perPage);

        $data = [];
        foreach ($products->items() as $product) {
            $row = (new ProductResource($product))->toArray($request);
            $data[] = $this->decorateProductPayload($row, $product);
        }

        return $this->apiSuccess($data, [
            'pagination' => $this->paginationMeta($products),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/products/{product}",
     *     tags={"Catalog"},
     *     summary="Show product detail",
     *     @OA\Parameter(name="product", in="path", required=true, description="Product id or slug", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Product detail",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProductDetailResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(mixed $product)
    {
        $query = Product::query()->with([
            'category:id,name,slug,parent_id',
            'brand:id,name,slug,description,sort',
            'assignedImages' => function ($q) {
                $q->orderByPivot('is_main', 'desc')
                    ->orderByPivot('sort');
            },
            'assignedVideos' => function ($q) {
                $q->orderByPivot('is_main', 'desc')
                    ->orderByPivot('sort');
            },
            'attributes.values',
            'combinations.values.value.attribute',
            'combinations.assignedImages',
            'combinations.assignedVideos',
        ]);

        $model = $this->findProduct($query, $product);
        $data = (new ProductDetailResource($model))->toArray(request());

        return $this->apiSuccess($this->decorateProductPayload($data, $model, true));
    }

    protected function findProduct(Builder $query, mixed $product): Product
    {
        $key = is_string($product) ? rawurldecode($product) : $product;
        if (ctype_digit((string) $key)) {
            return $query->findOrFail((int) $key);
        }

        return $query->whereIn('slug', $this->slugLookupValues((string) $key))->firstOrFail();
    }

    /**
     * @return list<string>
     */
    protected function slugLookupValues(string $slug): array
    {
        $slug = trim(rawurldecode($slug));

        return $slug === '' ? [] : [$slug];
    }

    /**
     * @return list<int>|null
     */
    protected function resolveCategoryIds(Request $request): ?array
    {
        $slug = trim((string) $request->input('category_slug', ''));
        if ($slug !== '') {
            $category = Category::query()
                ->whereIn('slug', $this->slugLookupValues($slug))
                ->first();

            return $category ? $this->categoryAndDescendantIds($category) : [];
        }

        if (! $request->filled('category_id')) {
            return null;
        }

        $category = Category::query()->find((int) $request->input('category_id'));

        return $category
            ? $this->categoryAndDescendantIds($category)
            : [(int) $request->input('category_id')];
    }

    /**
     * @return list<int>
     */
    protected function categoryAndDescendantIds(Category $category): array
    {
        $ids = [(int) $category->id];
        $frontier = [(int) $category->id];
        $guard = 0;

        while ($frontier && $guard < 32) {
            $children = Category::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $children = array_values(array_diff($children, $ids));
            if ($children === []) {
                break;
            }
            $ids = array_merge($ids, $children);
            $frontier = $children;
            $guard++;
        }

        return $ids;
    }

    protected function decorateProductPayload(array $data, Product $product, bool $detail = false): array
    {
        return $data;
    }

    protected function applyDisplayPriceFilter(Builder $query, ?float $baseAmount, float $displayAmount, string $operator): void
    {
        $query->where(function (Builder $inner) use ($baseAmount, $displayAmount, $operator) {
            if ($baseAmount !== null) {
                $inner->where('sale_price_cny', $operator, $baseAmount);
            }

            $inner->orWhere(function (Builder $fallback) use ($displayAmount, $operator) {
                $fallback->whereNull('sale_price_cny')
                    ->where('price', $operator, $displayAmount);
            });
        });
    }
}

