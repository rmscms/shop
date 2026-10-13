<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RMS\Shop\Http\Resources\Admin\BrandResource;
use RMS\Shop\Models\Brand;
use RMS\Shop\Services\BrandService;

class BrandController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/admin/shop/brands",
     *     tags={"Admin - Brands"},
     *     summary="List all brands",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_active", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"id", "name", "sort", "created_at"}, default="sort")),
     *     @OA\Parameter(name="sort_dir", in="query", @OA\Schema(type="string", enum={"asc", "desc"}, default="asc")),
     *     @OA\Response(
     *         response=200,
     *         description="Brands collection",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/AdminBrandResource")
     *             ),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginatedMeta")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));

        $query = Brand::query()
            ->select('shop_brands.*')
            ->selectRaw('(SELECT COUNT(*) FROM products WHERE products.brand_id = shop_brands.id) as products_count');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $sortBy = $request->input('sort_by', 'sort');
        $sortDir = $request->input('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['id', 'name', 'sort', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('sort')->orderBy('name');
        }

        $brands = $query->paginate($perPage);

        $data = BrandResource::collection($brands->items())->toArray($request);

        return $this->apiSuccess($data, [
            'pagination' => $this->paginationMeta($brands),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/brands/{id}",
     *     tags={"Admin - Brands"},
     *     summary="Show brand detail",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Brand detail",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminBrandResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function show(int $id)
    {
        $brand = Brand::query()
            ->select('shop_brands.*')
            ->selectRaw('(SELECT COUNT(*) FROM products WHERE products.brand_id = shop_brands.id) as products_count')
            ->findOrFail($id);

        return $this->apiSuccess((new BrandResource($brand))->toArray(request()));
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/brands",
     *     tags={"Admin - Brands"},
     *     summary="Create a new brand",
     *     security={{"admin":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Apple"),
     *             @OA\Property(property="slug", type="string", example="apple"),
     *             @OA\Property(property="description", type="string", example="Brand description"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="sort", type="integer", example=0)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Brand created",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminBrandResource")
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
            'slug' => ['nullable', 'string', 'max:190', 'unique:shop_brands,slug'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'sort' => ['integer', 'min:0'],
        ]);

        $brand = Brand::create($validated);

        return $this->apiSuccess((new BrandResource($brand))->toArray(request()), [], 201);
    }

    /**
     * @OA\Put(
     *     path="/admin/shop/brands/{id}",
     *     tags={"Admin - Brands"},
     *     summary="Update brand",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Apple"),
     *             @OA\Property(property="slug", type="string", example="apple"),
     *             @OA\Property(property="description", type="string", example="Brand description"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="sort", type="integer", example=0)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Brand updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminBrandResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function update(Request $request, int $id)
    {
        $brand = Brand::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', 'unique:shop_brands,slug,' . $id],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'sort' => ['integer', 'min:0'],
        ]);

        $brand->update($validated);

        return $this->apiSuccess((new BrandResource($brand->fresh()))->toArray(request()));
    }

    /**
     * @OA\Delete(
     *     path="/admin/shop/brands/{id}",
     *     tags={"Admin - Brands"},
     *     summary="Delete brand",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Brand deleted",
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
        $brand = Brand::findOrFail($id);
        $brand->delete();

        return $this->apiSuccess(['deleted' => true]);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/brands/options",
     *     tags={"Admin - Brands"},
     *     summary="Get brand options for select dropdowns",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="include_inactive", in="query", @OA\Schema(type="boolean", default=false)),
     *     @OA\Response(
     *         response=200,
     *         description="Brand options",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function options(Request $request)
    {
        $includeInactive = filter_var($request->input('include_inactive', false), FILTER_VALIDATE_BOOLEAN);
        $brandService = app(BrandService::class);
        $options = $brandService->getOptions($includeInactive);

        $data = array_map(function ($name, $id) {
            return ['id' => (int) $id, 'name' => $name];
        }, $options, array_keys($options));

        return $this->apiSuccess(array_values($data));
    }
}
