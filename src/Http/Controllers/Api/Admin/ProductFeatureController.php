<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RMS\Shop\Http\Resources\Admin\ProductFeatureResource;
use RMS\Shop\Models\Product;
use RMS\Shop\Models\ProductFeature;
use RMS\Shop\Models\ProductFeatureCategory;
use RMS\Shop\Services\ProductFeaturesService;

class ProductFeatureController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/admin/shop/products/{product_id}/features",
     *     tags={"Admin - Products"},
     *     summary="List all features for a product",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="product_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="category_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Features collection",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/AdminProductFeatureResource")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Product not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request, int $product_id)
    {
        $product = Product::findOrFail($product_id);

        $query = ProductFeature::query()
            ->where('product_id', $product_id)
            ->with(['category', 'definition', 'definitionValue']);

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->input('category_id'));
        }

        $features = $query->orderBy('category_id')->orderBy('sort')->get();

        $data = ProductFeatureResource::collection($features)->toArray($request);

        return $this->apiSuccess($data);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/products/{product_id}/features/{id}",
     *     tags={"Admin - Products"},
     *     summary="Show feature detail",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="product_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Feature detail",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminProductFeatureResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function show(int $product_id, int $id)
    {
        $feature = ProductFeature::query()
            ->where('product_id', $product_id)
            ->with(['category', 'definition', 'definitionValue'])
            ->findOrFail($id);

        return $this->apiSuccess((new ProductFeatureResource($feature))->toArray(request()));
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/products/{product_id}/features",
     *     tags={"Admin - Products"},
     *     summary="Save product features (bulk operation)",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="product_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"categories"},
     *             @OA\Property(property="categories", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="category_id", type="integer", nullable=true),
     *                     @OA\Property(property="category_name", type="string", nullable=true),
     *                     @OA\Property(property="features", type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="feature_id", type="integer", nullable=true),
     *                             @OA\Property(property="value_id", type="integer", nullable=true),
     *                             @OA\Property(property="name", type="string"),
     *                             @OA\Property(property="value", type="string", nullable=true),
     *                             @OA\Property(property="sort", type="integer", nullable=true)
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Features saved",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object", @OA\Property(property="saved", type="boolean", example=true))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Product not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function store(Request $request, int $product_id)
    {
        $product = Product::findOrFail($product_id);

        $data = $request->validate([
            'categories' => ['array'],
            'categories.*.category_id' => ['nullable', 'integer'],
            'categories.*.category_name' => ['nullable', 'string', 'max:190'],
            'categories.*.features' => ['array'],
            'categories.*.features.*.feature_id' => ['nullable', 'integer'],
            'categories.*.features.*.value_id' => ['nullable', 'integer'],
            'categories.*.features.*.name' => ['required', 'string', 'max:190'],
            'categories.*.features.*.value' => ['nullable', 'string', 'max:2000'],
            'categories.*.features.*.sort' => ['nullable', 'integer', 'min:0'],
        ]);

        // Validate that IDs exist if provided
        foreach ($data['categories'] ?? [] as $catIndex => $cat) {
            if (!empty($cat['category_id']) && !ProductFeatureCategory::where('id', $cat['category_id'])->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "categories.{$catIndex}.category_id" => ['دسته‌بندی انتخاب شده معتبر نیست'],
                ]);
            }

            foreach ($cat['features'] ?? [] as $featIndex => $feature) {
                if (!empty($feature['feature_id']) && !\RMS\Shop\Models\ProductFeatureDefinition::where('id', $feature['feature_id'])->exists()) {
                    $data['categories'][$catIndex]['features'][$featIndex]['feature_id'] = null;
                }
                if (!empty($feature['value_id']) && !\RMS\Shop\Models\ProductFeatureDefinitionValue::where('id', $feature['value_id'])->exists()) {
                    $data['categories'][$catIndex]['features'][$featIndex]['value_id'] = null;
                }
            }
        }

        app(ProductFeaturesService::class)->saveFeatures($product_id, $data);

        return $this->apiSuccess(['saved' => true]);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/products/features/categories",
     *     tags={"Admin - Products"},
     *     summary="Create a new feature category",
     *     security={{"admin":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Technical Specifications"),
     *             @OA\Property(property="icon", type="string", example="ph-cpu", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Feature category created",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="icon", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function createCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'icon' => ['nullable', 'string', 'max:50'],
        ]);

        $category = app(ProductFeaturesService::class)->createFeatureCategory($data);

        return $this->apiSuccess([
            'id' => (int) $category->id,
            'name' => $category->name,
            'icon' => $category->icon ?? 'ph-tag',
        ], [], 201);
    }
}
