<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RMS\Shop\Models\ProductFeatureCategory;
use RMS\Shop\Models\ProductFeatureDefinition;
use RMS\Shop\Models\ProductFeatureDefinitionValue;
use Illuminate\Support\Str;

class ProductFeatureLookupController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/admin/shop/product-features/categories/search",
     *     tags={"Admin - Products"},
     *     summary="Search feature categories",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="q", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Response(
     *         response=200,
     *         description="Feature categories",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function categories(Request $request)
    {
        $query = trim((string) $request->query('q', ''));
        $limit = (int) min(20, max(1, $request->integer('limit', 10)));

        $items = ProductFeatureCategory::query()
            ->when(Str::length($query) >= 1, fn ($q) => $q->where('name', 'like', "%{$query}%"))
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'icon']);

        $data = $items->map(fn ($item) => [
            'id' => (int) $item->id,
            'name' => (string) $item->name,
            'icon' => (string) ($item->icon ?? 'ph-info'),
        ]);

        return $this->apiSuccess($data);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/product-features/search",
     *     tags={"Admin - Products"},
     *     summary="Search feature definitions",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="q", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="category_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Response(
     *         response=200,
     *         description="Feature definitions",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function features(Request $request)
    {
        $query = trim((string) $request->query('q', ''));
        $limit = (int) min(20, max(1, $request->integer('limit', 10)));
        $categoryId = $request->integer('category_id');

        $items = ProductFeatureDefinition::query()
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when(Str::length($query) >= 1, fn ($q) => $q->where('name', 'like', "%{$query}%"))
            ->where('active', true)
            ->orderByDesc('usage_count')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'category_id', 'name']);

        $data = $items->map(fn ($item) => [
            'id' => (int) $item->id,
            'name' => (string) $item->name,
            'category_id' => $item->category_id ? (int) $item->category_id : null,
        ]);

        return $this->apiSuccess($data);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/product-features/values/search",
     *     tags={"Admin - Products"},
     *     summary="Search feature definition values",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="feature_id", in="query", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="q", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Response(
     *         response=200,
     *         description="Feature definition values",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function values(Request $request)
    {
        $query = trim((string) $request->query('q', ''));
        $limit = (int) min(20, max(1, $request->integer('limit', 10)));
        $featureId = (int) $request->integer('feature_id');

        if ($featureId <= 0) {
            return $this->apiError(['feature_id' => ['feature_id is required']], 422);
        }

        $items = ProductFeatureDefinitionValue::query()
            ->where('feature_id', $featureId)
            ->when(Str::length($query) >= 1, fn ($q) => $q->where('value', 'like', "%{$query}%"))
            ->where('active', true)
            ->orderByDesc('usage_count')
            ->orderBy('value')
            ->limit($limit)
            ->get(['id', 'value']);

        $data = $items->map(fn ($item) => [
            'id' => (int) $item->id,
            'value' => (string) $item->value,
        ]);

        return $this->apiSuccess($data);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/product-features/definitions",
     *     tags={"Admin - Products"},
     *     summary="Create feature definition",
     *     security={{"admin":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Screen Size"),
     *             @OA\Property(property="category_id", type="integer", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Feature definition created",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function storeFeature(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'category_id' => ['nullable', 'integer', 'exists:product_feature_categories,id'],
        ]);

        $slugBase = Str::slug($data['name']);
        $slug = $slugBase;
        $counter = 1;
        while (ProductFeatureDefinition::where('slug', $slug)->exists()) {
            $slug = $slugBase . '-' . $counter++;
        }

        $definition = ProductFeatureDefinition::create([
            'name' => $data['name'],
            'slug' => $slug,
            'category_id' => $data['category_id'] ?? null,
            'active' => true,
        ]);

        return $this->apiSuccess([
            'id' => (int) $definition->id,
            'name' => (string) $definition->name,
            'category_id' => $definition->category_id ? (int) $definition->category_id : null,
        ], [], 201);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/product-features/values",
     *     tags={"Admin - Products"},
     *     summary="Create feature definition value",
     *     security={{"admin":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"feature_id", "value"},
     *             @OA\Property(property="feature_id", type="integer", example=1),
     *             @OA\Property(property="value", type="string", example="6.1 inches")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Feature definition value created",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function storeValue(Request $request)
    {
        $data = $request->validate([
            'feature_id' => ['required', 'integer', 'exists:product_feature_definitions,id'],
            'value' => ['required', 'string', 'max:2000'],
        ]);

        $valueText = trim($data['value']);
        $slugBase = Str::slug($valueText);
        $slug = $slugBase ?: Str::slug($valueText . '-' . now()->timestamp);
        $counter = 1;
        
        // Ensure unique slug
        while (ProductFeatureDefinitionValue::where('feature_id', $data['feature_id'])
                ->where('slug', $slug)
                ->exists()) {
            $slug = $slugBase . '-' . $counter++;
        }
        
        $value = ProductFeatureDefinitionValue::firstOrCreate(
            [
                'feature_id' => $data['feature_id'],
                'value' => $valueText,
            ],
            [
                'slug' => $slug,
                'active' => true,
            ]
        );

        return $this->apiSuccess([
            'id' => (int) $value->id,
            'value' => (string) $value->value,
        ], [], 201);
    }
}
