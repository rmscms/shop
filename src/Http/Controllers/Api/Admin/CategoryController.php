<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RMS\Shop\Http\Resources\Admin\CategoryResource;
use RMS\Shop\Models\Category;
use RMS\Shop\Services\CategoryTreeService;

class CategoryController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/admin/shop/categories",
     *     tags={"Admin - Categories"},
     *     summary="List all categories",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="parent_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="active", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"id", "name", "sort", "created_at"}, default="sort")),
     *     @OA\Parameter(name="sort_dir", in="query", @OA\Schema(type="string", enum={"asc", "desc"}, default="asc")),
     *     @OA\Response(
     *         response=200,
     *         description="Categories collection",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/AdminCategoryResource")
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

        $query = Category::query()->with('parent:id,name,slug');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('parent_id')) {
            $query->where('parent_id', (int) $request->input('parent_id'));
        } else {
            $query->whereNull('parent_id');
        }

        if ($request->has('active')) {
            $query->where('active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
        }

        $sortBy = $request->input('sort_by', 'sort');
        $sortDir = $request->input('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['id', 'name', 'sort', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('sort')->orderBy('name');
        }

        $categories = $query->paginate($perPage);

        $data = CategoryResource::collection($categories->items())->toArray($request);

        return $this->apiSuccess($data, [
            'pagination' => $this->paginationMeta($categories),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/categories/{id}",
     *     tags={"Admin - Categories"},
     *     summary="Show category detail",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Category detail",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminCategoryResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function show(int $id)
    {
        $category = Category::with('parent')->findOrFail($id);

        return $this->apiSuccess((new CategoryResource($category))->toArray(request()));
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/categories",
     *     tags={"Admin - Categories"},
     *     summary="Create a new category",
     *     security={{"admin":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "slug"},
     *             @OA\Property(property="name", type="string", example="Electronics"),
     *             @OA\Property(property="slug", type="string", example="electronics"),
     *             @OA\Property(property="parent_id", type="integer", nullable=true),
     *             @OA\Property(property="active", type="boolean", example=true),
     *             @OA\Property(property="sort", type="integer", example=0)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Category created",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminCategoryResource")
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
            'slug' => ['required', 'string', 'max:190', 'unique:categories,slug'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ]);

        $category = Category::create($validated);

        return $this->apiSuccess((new CategoryResource($category->load('parent')))->toArray(request()), [], 201);
    }

    /**
     * @OA\Put(
     *     path="/admin/shop/categories/{id}",
     *     tags={"Admin - Categories"},
     *     summary="Update category",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Electronics"),
     *             @OA\Property(property="slug", type="string", example="electronics"),
     *             @OA\Property(property="parent_id", type="integer", nullable=true),
     *             @OA\Property(property="active", type="boolean", example=true),
     *             @OA\Property(property="sort", type="integer", example=0)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminCategoryResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function update(Request $request, int $id)
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['required', 'string', 'max:190', 'unique:categories,slug,' . $id],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ]);

        $category->update($validated);

        return $this->apiSuccess((new CategoryResource($category->fresh(['parent'])))->toArray(request()));
    }

    /**
     * @OA\Delete(
     *     path="/admin/shop/categories/{id}",
     *     tags={"Admin - Categories"},
     *     summary="Delete category",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Category deleted",
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
        $category = Category::findOrFail($id);
        $category->delete();

        return $this->apiSuccess(['deleted' => true]);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/categories/tree",
     *     tags={"Admin - Categories"},
     *     summary="Get category tree",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="active_only", in="query", @OA\Schema(type="boolean", default=true)),
     *     @OA\Response(
     *         response=200,
     *         description="Category tree",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function tree(Request $request)
    {
        $activeOnly = filter_var($request->input('active_only', true), FILTER_VALIDATE_BOOLEAN);
        $service = app(CategoryTreeService::class);
        $treeData = $service->getTreeForPlugin(null, $activeOnly);
        $defaultId = $service->defaultCategoryId();

        return $this->apiSuccess([
            'tree' => $treeData,
            'default_category_id' => $defaultId,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/categories/options",
     *     tags={"Admin - Categories"},
     *     summary="Get category options for select dropdowns",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="active_only", in="query", @OA\Schema(type="boolean", default=false)),
     *     @OA\Response(
     *         response=200,
     *         description="Category options",
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
        $activeOnly = filter_var($request->input('active_only', false), FILTER_VALIDATE_BOOLEAN);
        $service = app(CategoryTreeService::class);
        $options = $service->flatOptions($activeOnly);

        return $this->apiSuccess($options);
    }
}
