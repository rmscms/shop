<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;
use RMS\Shop\Http\Resources\Admin\ImageLibraryResource;
use RMS\Shop\Models\ImageLibrary;
use RMS\Shop\Models\Product;
use RMS\Shop\Jobs\ConvertImageToAvif;
use RMS\Shop\Models\ImageLibraryFolder;
use RMS\Shop\Services\ImageLibraryFolderService;
use RMS\Shop\Services\ImageLibraryService;
use RMS\Shop\Services\ProductImagesService;
use RMS\Shop\Support\MediaLibrarySchema;

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
        $search = (string) $request->input('search', '');
        [$folderId, $unfiledOnly] = $this->folderFilterFromRequest($request);

        if ($request->filled('mime_type')) {
            $query = ImageLibrary::query()
                ->selectRaw('image_library.*, (SELECT COUNT(*) FROM image_assignments WHERE image_assignments.image_id = image_library.id) as assignments_count')
                ->where('mime_type', (string) $request->input('mime_type'));

            if ($search !== '') {
                $query->where('filename', 'like', "%{$search}%");
            }
            if ($unfiledOnly && MediaLibrarySchema::hasImageFolders()) {
                $query->whereNull('folder_id');
            } elseif ($folderId && MediaLibrarySchema::hasImageFolders()) {
                $query->where('folder_id', $folderId);
            }

            $images = $query->orderByDesc('id')->paginate($perPage);
        } else {
            $images = ImageLibraryService::searchImages($search, $perPage, $folderId, $unfiledOnly);
        }

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
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:10240'],
            'folder_id' => ['nullable', 'integer'],
        ]);

        try {
            $file = $request->file('image');
            $folder = null;
            if ($request->filled('folder_id') && MediaLibrarySchema::hasImageFolders()) {
                $folder = ImageLibraryFolder::query()->findOrFail((int) $request->input('folder_id'));
            }
            $image = ImageLibraryService::uploadImage($file, $folder);

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

    /**
     * @OA\Get(
     *     path="/admin/shop/image-library/folders/tree",
     *     tags={"Admin - Media"},
     *     summary="Image library folder tree",
     *     security={{"admin":{}}},
     *     @OA\Response(response=200, description="Tree"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function foldersTree(Request $request)
    {
        $selected = $request->input('selected');
        $selectedId = is_numeric($selected) ? (int) $selected : null;

        return $this->apiSuccess(app(ImageLibraryFolderService::class)->getTreeForPlugin($selectedId));
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/image-library/folders",
     *     tags={"Admin - Media"},
     *     summary="Create an image library folder",
     *     security={{"admin":{}}},
     *     @OA\Response(response=201, description="Created"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function storeFolder(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'parent_id' => ['nullable', 'integer'],
        ]);

        try {
            $folder = app(ImageLibraryFolderService::class)->createFolder(
                $request->input('parent_id') ? (int) $request->input('parent_id') : null,
                (string) $request->input('name')
            );

            return $this->apiSuccess($this->folderRow($folder), [], 201);
        } catch (\Throwable $e) {
            return $this->apiError(['folder' => [$e->getMessage()]], 422);
        }
    }

    /**
     * @OA\Put(
     *     path="/admin/shop/image-library/folders/{folder}",
     *     tags={"Admin - Media"},
     *     summary="Rename an image library folder",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="folder", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function updateFolder(Request $request, ImageLibraryFolder $folder)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:190'],
        ]);

        try {
            $folder = app(ImageLibraryFolderService::class)->updateFolder($folder, (string) $request->input('name'));

            return $this->apiSuccess($this->folderRow($folder));
        } catch (\Throwable $e) {
            return $this->apiError(['folder' => [$e->getMessage()]], 422);
        }
    }

    /**
     * @OA\Delete(
     *     path="/admin/shop/image-library/folders/{folder}",
     *     tags={"Admin - Media"},
     *     summary="Delete an image library folder",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="folder", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function destroyFolder(ImageLibraryFolder $folder)
    {
        try {
            app(ImageLibraryFolderService::class)->deleteFolder($folder);

            return $this->apiSuccess(['deleted' => true]);
        } catch (\Throwable $e) {
            return $this->apiError(['folder' => [$e->getMessage()]], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/image-library/{id}/move",
     *     tags={"Admin - Media"},
     *     summary="Move an image into a folder",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Moved"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function moveImage(Request $request, int $id)
    {
        $image = ImageLibrary::findOrFail($id);
        $request->validate([
            'folder_id' => ['nullable', 'integer'],
        ]);

        try {
            $targetFolder = ($request->filled('folder_id') && MediaLibrarySchema::hasImageFolders())
                ? ImageLibraryFolder::query()->findOrFail((int) $request->input('folder_id'))
                : null;

            $image = ImageLibraryService::moveImage($image, $targetFolder);

            return $this->apiSuccess([
                'id' => (int) $image->id,
                'folder_id' => $image->folder_id,
                'path' => $image->path,
            ]);
        } catch (\Throwable $e) {
            return $this->apiError(['image' => [$e->getMessage()]], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/image-library/{id}/generate-avif",
     *     tags={"Admin - Media"},
     *     summary="Queue AVIF conversion for a library image",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Queued"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function generateAvif(int $id)
    {
        $image = ImageLibrary::findOrFail($id);
        ConvertImageToAvif::dispatch($image->path, 85);

        return $this->apiSuccess(['queued' => true, 'image_id' => (int) $image->id]);
    }

    protected function folderRow(ImageLibraryFolder $folder): array
    {
        return [
            'id' => (int) $folder->id,
            'name' => $folder->name,
            'path' => $folder->path,
            'parent_id' => $folder->parent_id,
        ];
    }

    /**
     * @return array{0: ?int, 1: bool}
     */
    private function folderFilterFromRequest(Request $request): array
    {
        if (! MediaLibrarySchema::hasImageFolders()) {
            return [null, false];
        }

        if ($request->boolean('unfiled_only')) {
            return [null, true];
        }

        if ($request->filled('folder_id')) {
            return [(int) $request->input('folder_id'), false];
        }

        return [null, false];
    }
}
