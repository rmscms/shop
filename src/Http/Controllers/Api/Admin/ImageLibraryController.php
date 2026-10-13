<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;
use RMS\Shop\Http\Resources\Admin\ImageLibraryResource;
use RMS\Shop\Models\ImageLibrary;
use RMS\Shop\Models\Product;
use RMS\Shop\Services\ImageLibraryService;
use RMS\Shop\Services\ProductImagesService;

class ImageLibraryController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/admin/shop/image-library",
     *     tags={"Admin - Media"},
     *     summary="List all images in library",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="mime_type", in="query", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Images collection",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/AdminImageLibraryResource")
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
        $search = $request->input('search', '');

        $query = ImageLibrary::query()
            ->selectRaw('image_library.*, (SELECT COUNT(*) FROM image_assignments WHERE image_assignments.image_id = image_library.id) as assignments_count');

        if ($search) {
            $query->where('filename', 'like', "%{$search}%");
        }

        if ($request->filled('mime_type')) {
            $query->where('mime_type', $request->input('mime_type'));
        }

        $images = $query->orderByDesc('id')->paginate($perPage);

        $data = ImageLibraryResource::collection($images->items())->toArray($request);

        return $this->apiSuccess($data, [
            'pagination' => $this->paginationMeta($images),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/image-library/{id}",
     *     tags={"Admin - Media"},
     *     summary="Show image detail",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Image detail",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminImageLibraryResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function show(int $id)
    {
        $image = ImageLibrary::findOrFail($id);

        return $this->apiSuccess((new ImageLibraryResource($image))->toArray(request()));
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/image-library",
     *     tags={"Admin - Media"},
     *     summary="Upload image to library",
     *     security={{"admin":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="image", type="string", format="binary", description="Image file")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Image uploaded",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminImageLibraryResource")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120'],
        ]);

        try {
            $file = $request->file('image');
            $image = ImageLibraryService::uploadImage($file);

            return $this->apiSuccess((new ImageLibraryResource($image))->toArray(request()), [], 201);
        } catch (\Exception $e) {
            return $this->apiError(['image' => ['خطا در آپلود تصویر: ' . $e->getMessage()]], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/admin/shop/image-library/{id}",
     *     tags={"Admin - Media"},
     *     summary="Delete image from library",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Image deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object", @OA\Property(property="deleted", type="boolean", example=true))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Cannot delete - image is in use"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function destroy(int $id)
    {
        try {
            $image = ImageLibrary::findOrFail($id);

            if (!$image->canBeDeleted()) {
                return $this->apiError(['image' => ['این تصویر قابل حذف نیست زیرا در محصولاتی استفاده شده است']], 422);
            }

            $deleted = ImageLibraryService::deleteImage($image);

            if ($deleted) {
                return $this->apiSuccess(['deleted' => true]);
            } else {
                return $this->apiError(['image' => ['خطا در حذف تصویر']], 500);
            }
        } catch (\Exception $e) {
            return $this->apiError(['image' => ['خطا در حذف تصویر: ' . $e->getMessage()]], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/image-library/{id}/assign-to-product",
     *     tags={"Admin - Media"},
     *     summary="Assign image to product",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id"},
     *             @OA\Property(property="product_id", type="integer"),
     *             @OA\Property(property="is_main", type="boolean", example=false),
     *             @OA\Property(property="sort", type="integer", example=0)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Image assigned",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object", @OA\Property(property="assigned", type="boolean", example=true))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function assignToProduct(Request $request, int $id)
    {
        $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'is_main' => ['boolean'],
            'sort' => ['integer', 'min:0'],
        ]);

        try {
            $productId = (int) $request->input('product_id');
            $data = [
                'is_main' => $request->boolean('is_main', false),
                'sort' => (int) $request->input('sort', 0),
            ];

            ProductImagesService::assignToProduct($productId, $id, $data);

            return $this->apiSuccess(['assigned' => true]);
        } catch (\Exception $e) {
            return $this->apiError(['image' => ['خطا در اختصاص تصویر: ' . $e->getMessage()]], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/image-library/{id}/detach-from-product",
     *     tags={"Admin - Media"},
     *     summary="Detach image from product",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id"},
     *             @OA\Property(property="product_id", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Image detached",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object", @OA\Property(property="detached", type="boolean", example=true))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function detachFromProduct(Request $request, int $id)
    {
        $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        try {
            $productId = (int) $request->input('product_id');
            ProductImagesService::detachFromProduct($productId, $id);

            return $this->apiSuccess(['detached' => true]);
        } catch (\Exception $e) {
            return $this->apiError(['image' => ['خطا در جدا کردن تصویر: ' . $e->getMessage()]], 500);
        }
    }
}
