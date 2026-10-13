<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RMS\Shop\Http\Resources\Admin\ProductFeatureCategoryResource;
use RMS\Shop\Models\ProductFeatureCategory;

class ProductFeatureCategoryController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/admin/shop/product-feature-categories",
     *     tags={"Admin - Products"},
     *     summary="List all feature categories",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Parameter(name="active", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(
     *         response=200,
     *         description="Feature categories collection",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AdminProductFeatureCategoryResource"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        $query = ProductFeatureCategory::query();

        if ($request->has('active')) {
            $query->where('active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
        }

        $categories = $query->orderBy('sort')->orderBy('name')->get();

        $data = ProductFeatureCategoryResource::collection($categories)->toArray($request);

        return $this->apiSuccess($data);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/product-feature-categories/{id}",
     *     tags={"Admin - Products"},
     *     summary="Show feature category detail",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Feature category detail",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminProductFeatureCategoryResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function show(int $id)
    {
        $category = ProductFeatureCategory::findOrFail($id);

        return $this->apiSuccess((new ProductFeatureCategoryResource($category))->toArray(request()));
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/product-feature-categories",
     *     tags={"Admin - Products"},
     *     summary="Create a new feature category",
     *     security={{"admin":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Technical Specifications"),
     *             @OA\Property(property="slug", type="string", example="technical-specifications", nullable=true),
     *             @OA\Property(property="icon", type="string", example="ph-cpu", nullable=true),
     *             @OA\Property(property="description", type="string", nullable=true),
     *             @OA\Property(property="sort", type="integer", example=0),
     *             @OA\Property(property="active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Feature category created",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminProductFeatureCategoryResource")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', 'unique:product_feature_categories,slug'],
            'icon' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'sort' => ['integer', 'min:0'],
            'active' => ['boolean'],
        ]);

        $category = ProductFeatureCategory::create($validated);

        return $this->apiSuccess((new ProductFeatureCategoryResource($category))->toArray(request()), [], 201);
    }

    /**
     * @OA\Put(
     *     path="/admin/shop/product-feature-categories/{id}",
     *     tags={"Admin - Products"},
     *     summary="Update feature category",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Technical Specifications"),
     *             @OA\Property(property="slug", type="string", example="technical-specifications", nullable=true),
     *             @OA\Property(property="icon", type="string", example="ph-cpu", nullable=true),
     *             @OA\Property(property="description", type="string", nullable=true),
     *             @OA\Property(property="sort", type="integer", example=0),
     *             @OA\Property(property="active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Feature category updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminProductFeatureCategoryResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function update(Request $request, int $id)
    {
        $category = ProductFeatureCategory::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', 'unique:product_feature_categories,slug,' . $id],
            'icon' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'sort' => ['integer', 'min:0'],
            'active' => ['boolean'],
        ]);

        $category->update($validated);

        return $this->apiSuccess((new ProductFeatureCategoryResource($category->fresh()))->toArray(request()));
    }

    /**
     * @OA\Delete(
     *     path="/admin/shop/product-feature-categories/{id}",
     *     tags={"Admin - Products"},
     *     summary="Delete feature category",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Feature category deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object", @OA\Property(property="deleted", type="boolean", example=true))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function destroy(int $id)
    {
        $category = ProductFeatureCategory::findOrFail($id);
        $category->delete();

        return $this->apiSuccess(['deleted' => true]);
    }
}
