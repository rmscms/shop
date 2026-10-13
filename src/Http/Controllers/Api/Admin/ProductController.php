<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RMS\Shop\Http\Resources\Admin\ProductResource;
use RMS\Shop\Models\Product;
use RMS\Shop\Services\ProductService;

class ProductController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/admin/shop/products",
     *     tags={"Admin - Products"},
     *     summary="List all products",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="category_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="brand_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="active", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="with_trashed", in="query", @OA\Schema(type="boolean", default=false)),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"id", "name", "price", "created_at"}, default="id")),
     *     @OA\Parameter(name="sort_dir", in="query", @OA\Schema(type="string", enum={"asc", "desc"}, default="desc")),
     *     @OA\Response(
     *         response=200,
     *         description="Products collection",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/AdminProductResource")
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

        $query = Product::query()
            ->with(['category:id,name,slug', 'brand:id,name,slug']);

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', (int) $request->input('category_id'));
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', (int) $request->input('brand_id'));
        }

        if ($request->has('active')) {
            $query->where('active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
        }

        $withTrashed = filter_var($request->input('with_trashed', false), FILTER_VALIDATE_BOOLEAN);
        if ($withTrashed) {
            $query->withTrashed();
        } else {
            $query->whereNull('deleted_at');
        }

        $sortBy = $request->input('sort_by', 'id');
        $sortDir = $request->input('sort_dir', 'desc') === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['id', 'name', 'price', 'created_at'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderByDesc('id');
        }

        $products = $query->paginate($perPage);

        $data = ProductResource::collection($products->items())->toArray($request);

        return $this->apiSuccess($data, [
            'pagination' => $this->paginationMeta($products),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/products/{id}",
     *     tags={"Admin - Products"},
     *     summary="Show product detail",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Product detail",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminProductResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function show(int $id)
    {
        $product = Product::with(['category:id,name,slug', 'brand:id,name,slug'])
            ->findOrFail($id);

        return $this->apiSuccess((new ProductResource($product))->toArray(request()));
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/products",
     *     tags={"Admin - Products"},
     *     summary="Create a new product",
     *     security={{"admin":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Product Name"),
     *             @OA\Property(property="slug", type="string", example="product-name"),
     *             @OA\Property(property="sku", type="string", example="SKU-001"),
     *             @OA\Property(property="price", type="number", format="float", example=100000),
     *             @OA\Property(property="sale_price", type="number", format="float", example=90000),
     *             @OA\Property(property="cost_cny", type="number", format="float", example=100),
     *             @OA\Property(property="sale_price_cny", type="number", format="float", example=90),
     *             @OA\Property(property="stock_qty", type="integer", example=100),
     *             @OA\Property(property="active", type="boolean", example=true),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="brand_id", type="integer", example=1),
     *             @OA\Property(property="short_desc", type="string", example="Short description"),
     *             @OA\Property(property="description", type="string", example="Full description"),
     *             @OA\Property(property="discount_type", type="string", enum={"percentage", "fixed"}, example="percentage"),
     *             @OA\Property(property="discount_value", type="number", format="float", example=10),
     *             @OA\Property(property="point_per_unit", type="integer", example=10)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Product created",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminProductResource")
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
            'slug' => ['nullable', 'string', 'max:190', 'unique:products,slug'],
            'sku' => ['nullable', 'string', 'max:190', 'unique:products,sku'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'cost_cny' => ['nullable', 'numeric', 'min:0'],
            'sale_price_cny' => ['nullable', 'numeric', 'min:0'],
            'stock_qty' => ['integer', 'min:0'],
            'active' => ['boolean'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:shop_brands,id'],
            'short_desc' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'discount_type' => ['nullable', 'string', 'in:percentage,fixed'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'point_per_unit' => ['integer', 'min:0'],
        ]);

        $product = Product::create($validated);

        return $this->apiSuccess((new ProductResource($product->load(['category', 'brand'])))->toArray(request()), [], 201);
    }

    /**
     * @OA\Put(
     *     path="/admin/shop/products/{id}",
     *     tags={"Admin - Products"},
     *     summary="Update product",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Product Name"),
     *             @OA\Property(property="slug", type="string", example="product-name"),
     *             @OA\Property(property="sku", type="string", example="SKU-001"),
     *             @OA\Property(property="price", type="number", format="float", example=100000),
     *             @OA\Property(property="sale_price", type="number", format="float", example=90000),
     *             @OA\Property(property="cost_cny", type="number", format="float", example=100),
     *             @OA\Property(property="sale_price_cny", type="number", format="float", example=90),
     *             @OA\Property(property="stock_qty", type="integer", example=100),
     *             @OA\Property(property="active", type="boolean", example=true),
     *             @OA\Property(property="category_id", type="integer", example=1),
     *             @OA\Property(property="brand_id", type="integer", example=1),
     *             @OA\Property(property="short_desc", type="string", example="Short description"),
     *             @OA\Property(property="description", type="string", example="Full description"),
     *             @OA\Property(property="discount_type", type="string", enum={"percentage", "fixed"}, example="percentage"),
     *             @OA\Property(property="discount_value", type="number", format="float", example=10),
     *             @OA\Property(property="point_per_unit", type="integer", example=10)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminProductResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function update(Request $request, int $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', 'unique:products,slug,' . $id],
            'sku' => ['nullable', 'string', 'max:190', 'unique:products,sku,' . $id],
            'price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'cost_cny' => ['nullable', 'numeric', 'min:0'],
            'sale_price_cny' => ['nullable', 'numeric', 'min:0'],
            'stock_qty' => ['integer', 'min:0'],
            'active' => ['boolean'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:shop_brands,id'],
            'short_desc' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'discount_type' => ['nullable', 'string', 'in:percentage,fixed'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'point_per_unit' => ['integer', 'min:0'],
        ]);

        $product->update($validated);

        return $this->apiSuccess((new ProductResource($product->fresh(['category', 'brand'])))->toArray(request()));
    }

    /**
     * @OA\Delete(
     *     path="/admin/shop/products/{id}",
     *     tags={"Admin - Products"},
     *     summary="Delete product (soft delete)",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Product deleted",
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
        $product = Product::findOrFail($id);
        $product->delete();

        return $this->apiSuccess(['deleted' => true]);
    }

    /**
     * @OA\Patch(
     *     path="/admin/shop/products/{id}/basic",
     *     tags={"Admin - Products"},
     *     summary="Update product basic information",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="slug", type="string"),
     *             @OA\Property(property="sku", type="string"),
     *             @OA\Property(property="category_id", type="integer"),
     *             @OA\Property(property="brand_id", type="integer"),
     *             @OA\Property(property="short_desc", type="string"),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product basic info updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminProductResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function updateBasic(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $productService = app(ProductService::class);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', 'unique:products,slug,' . $id],
            'sku' => ['nullable', 'string', 'max:190', 'unique:products,sku,' . $id],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:shop_brands,id'],
            'short_desc' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'stock_qty' => ['nullable', 'integer', 'min:0'],
        ]);

        $productService->updateBasic($id, $validated);

        return $this->apiSuccess((new ProductResource($product->fresh(['category', 'brand'])))->toArray(request()));
    }

    /**
     * @OA\Patch(
     *     path="/admin/shop/products/{id}/pricing",
     *     tags={"Admin - Products"},
     *     summary="Update product pricing",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="price", type="number", format="float"),
     *             @OA\Property(property="sale_price", type="number", format="float"),
     *             @OA\Property(property="cost_cny", type="number", format="float"),
     *             @OA\Property(property="sale_price_cny", type="number", format="float"),
     *             @OA\Property(property="discount_type", type="string", enum={"percentage", "fixed"}),
     *             @OA\Property(property="discount_value", type="number", format="float"),
     *             @OA\Property(property="point_per_unit", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product pricing updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminProductResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function updatePricing(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $productService = app(ProductService::class);

        $validated = $request->validate([
            'price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'cost_cny' => ['nullable', 'numeric', 'min:0'],
            'sale_price_cny' => ['nullable', 'numeric', 'min:0'],
            'discount_type' => ['nullable', 'string', 'in:percentage,fixed'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'point_per_unit' => ['integer', 'min:0'],
        ]);

        $productService->updatePricing($id, $validated);

        return $this->apiSuccess((new ProductResource($product->fresh()))->toArray(request()));
    }
}
