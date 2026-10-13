<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RMS\Shop\Http\Resources\Admin\ImageSizeResource;
use RMS\Shop\Models\ImageSize;

class ImageSizeController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/admin/shop/image-sizes",
     *     tags={"Admin - Image Sizes"},
     *     summary="List all image sizes",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="active", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"id", "name", "sort"}, default="sort")),
     *     @OA\Parameter(name="sort_dir", in="query", @OA\Schema(type="string", enum={"asc", "desc"}, default="asc")),
     *     @OA\Response(
     *         response=200,
     *         description="Image sizes collection",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/AdminImageSizeResource")
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

        $query = ImageSize::query();

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->has('active')) {
            $query->where('active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
        }

        $sortBy = $request->input('sort_by', 'sort');
        $sortDir = $request->input('sort_dir', 'asc') === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['id', 'name', 'sort'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderBy('sort')->orderBy('id');
        }

        $sizes = $query->paginate($perPage);

        $data = ImageSizeResource::collection($sizes->items())->toArray($request);

        return $this->apiSuccess($data, [
            'pagination' => $this->paginationMeta($sizes),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/image-sizes/{id}",
     *     tags={"Admin - Image Sizes"},
     *     summary="Show image size detail",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Image size detail",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminImageSizeResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function show(int $id)
    {
        $size = ImageSize::findOrFail($id);

        return $this->apiSuccess((new ImageSizeResource($size))->toArray(request()));
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/image-sizes",
     *     tags={"Admin - Image Sizes"},
     *     summary="Create a new image size",
     *     security={{"admin":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "slug"},
     *             @OA\Property(property="name", type="string", example="Thumbnail"),
     *             @OA\Property(property="slug", type="string", example="thumbnail"),
     *             @OA\Property(property="width", type="integer", example=150, nullable=true),
     *             @OA\Property(property="height", type="integer", example=150, nullable=true),
     *             @OA\Property(property="quality", type="integer", example=85),
     *             @OA\Property(property="active", type="boolean", example=true),
     *             @OA\Property(property="sort", type="integer", example=0)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Image size created",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminImageSizeResource")
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
            'slug' => ['required', 'string', 'max:190', 'unique:image_sizes,slug'],
            'width' => ['nullable', 'integer', 'min:1'],
            'height' => ['nullable', 'integer', 'min:1'],
            'quality' => ['required', 'integer', 'min:1', 'max:100'],
            'active' => ['boolean'],
            'sort' => ['nullable', 'integer'],
        ]);

        $size = ImageSize::create($validated);

        return $this->apiSuccess((new ImageSizeResource($size))->toArray(request()), [], 201);
    }

    /**
     * @OA\Put(
     *     path="/admin/shop/image-sizes/{id}",
     *     tags={"Admin - Image Sizes"},
     *     summary="Update image size",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Thumbnail"),
     *             @OA\Property(property="slug", type="string", example="thumbnail"),
     *             @OA\Property(property="width", type="integer", example=150, nullable=true),
     *             @OA\Property(property="height", type="integer", example=150, nullable=true),
     *             @OA\Property(property="quality", type="integer", example=85),
     *             @OA\Property(property="active", type="boolean", example=true),
     *             @OA\Property(property="sort", type="integer", example=0)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Image size updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminImageSizeResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function update(Request $request, int $id)
    {
        $size = ImageSize::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:190'],
            'slug' => ['sometimes', 'required', 'string', 'max:190', 'unique:image_sizes,slug,' . $id],
            'width' => ['nullable', 'integer', 'min:1'],
            'height' => ['nullable', 'integer', 'min:1'],
            'quality' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'active' => ['boolean'],
            'sort' => ['nullable', 'integer'],
        ]);

        $size->update($validated);

        return $this->apiSuccess((new ImageSizeResource($size->fresh()))->toArray(request()));
    }

    /**
     * @OA\Delete(
     *     path="/admin/shop/image-sizes/{id}",
     *     tags={"Admin - Image Sizes"},
     *     summary="Delete image size",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Image size deleted",
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
        $size = ImageSize::findOrFail($id);
        $size->delete();

        return $this->apiSuccess(['deleted' => true]);
    }
}
