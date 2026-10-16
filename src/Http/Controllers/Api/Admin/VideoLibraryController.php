<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RMS\Shop\Http\Resources\Admin\VideoLibraryResource;
use RMS\Shop\Models\VideoLibrary;
use RMS\Shop\Models\VideoLibraryFolder;
use RMS\Shop\Models\Product;
use RMS\Shop\Services\VideoLibraryFolderService;
use RMS\Shop\Services\VideoLibraryService;
use RMS\Shop\Support\MediaLibrarySchema;

class VideoLibraryController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/admin/shop/video-library",
     *     tags={"Admin - Media"},
     *     summary="List all videos in library",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Videos collection",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AdminVideoLibraryResource"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $videoService = app(VideoLibraryService::class);
        $videos = $videoService->searchVideos($search);

        $data = VideoLibraryResource::collection($videos)->toArray($request);

        return $this->apiSuccess($data);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/video-library/{id}",
     *     tags={"Admin - Media"},
     *     summary="Show video detail",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Video detail",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminVideoLibraryResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function show(int $id)
    {
        $video = VideoLibrary::findOrFail($id);

        return $this->apiSuccess((new VideoLibraryResource($video))->toArray(request()));
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/video-library",
     *     tags={"Admin - Media"},
     *     summary="Upload video to library",
     *     security={{"admin":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="video", type="string", format="binary", description="Video file"),
     *                 @OA\Property(property="title", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Video uploaded",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminVideoLibraryResource")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'video' => ['required', 'file', 'mimes:mp4,webm,ogg', 'max:51200'],
            'title' => ['nullable', 'string', 'max:190'],
        ]);

        try {
            $file = $request->file('video');
            $videoService = app(VideoLibraryService::class);
            $video = $videoService->upload($file, $request->input('title'));

            return $this->apiSuccess((new VideoLibraryResource($video))->toArray(request()), [], 201);
        } catch (\Exception $e) {
            return $this->apiError(['video' => ['خطا در آپلود ویدیو: ' . $e->getMessage()]], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/admin/shop/video-library/{id}",
     *     tags={"Admin - Media"},
     *     summary="Delete video from library",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Video deleted",
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
        try {
            $video = VideoLibrary::findOrFail($id);
            $videoService = app(VideoLibraryService::class);
            $videoService->delete($video);

            return $this->apiSuccess(['deleted' => true]);
        } catch (\Exception $e) {
            return $this->apiError(['video' => ['خطا در حذف ویدیو: ' . $e->getMessage()]], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/video-library/{id}/assign-to-product",
     *     tags={"Admin - Media"},
     *     summary="Assign video to product",
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
     *         description="Video assigned",
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
            $product = Product::findOrFail($request->input('product_id'));
            $video = VideoLibrary::findOrFail($id);
            $videoService = app(VideoLibraryService::class);

            $videoService->assignVideosTo($product, [$id]);

            return $this->apiSuccess(['assigned' => true]);
        } catch (\Exception $e) {
            return $this->apiError(['video' => ['خطا در اختصاص ویدیو: ' . $e->getMessage()]], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/video-library/{id}/detach-from-product",
     *     tags={"Admin - Media"},
     *     summary="Detach video from product",
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
     *         description="Video detached",
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
            $product = Product::findOrFail($request->input('product_id'));
            $videoService = app(VideoLibraryService::class);
            $videoService->detachVideosFrom($product, [$id]);

            return $this->apiSuccess(['detached' => true]);
        } catch (\Exception $e) {
            return $this->apiError(['video' => ['خطا در جدا کردن ویدیو: ' . $e->getMessage()]], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/video-library/folders/tree",
     *     tags={"Admin - Media"},
     *     summary="Video library folder tree",
     *     security={{"admin":{}}},
     *     @OA\Response(response=200, description="Tree"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function foldersTree(Request $request)
    {
        $selected = $request->input('selected');
        $selectedId = is_numeric($selected) ? (int) $selected : null;

        return $this->apiSuccess(app(VideoLibraryFolderService::class)->getTreeForPlugin($selectedId));
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/video-library/folders",
     *     tags={"Admin - Media"},
     *     summary="Create a video library folder",
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
            $folder = app(VideoLibraryFolderService::class)->createFolder(
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
     *     path="/admin/shop/video-library/folders/{folder}",
     *     tags={"Admin - Media"},
     *     summary="Rename a video library folder",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="folder", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function updateFolder(Request $request, VideoLibraryFolder $folder)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:190'],
        ]);

        try {
            $folder = app(VideoLibraryFolderService::class)->updateFolder($folder, (string) $request->input('name'));

            return $this->apiSuccess($this->folderRow($folder));
        } catch (\Throwable $e) {
            return $this->apiError(['folder' => [$e->getMessage()]], 422);
        }
    }

    /**
     * @OA\Delete(
     *     path="/admin/shop/video-library/folders/{folder}",
     *     tags={"Admin - Media"},
     *     summary="Delete a video library folder",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="folder", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function destroyFolder(VideoLibraryFolder $folder)
    {
        try {
            app(VideoLibraryFolderService::class)->deleteFolder($folder);

            return $this->apiSuccess(['deleted' => true]);
        } catch (\Throwable $e) {
            return $this->apiError(['folder' => [$e->getMessage()]], 422);
        }
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/video-library/{id}/move",
     *     tags={"Admin - Media"},
     *     summary="Move a video into a folder",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Moved"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function moveVideo(Request $request, int $id)
    {
        $video = VideoLibrary::findOrFail($id);
        $request->validate([
            'folder_id' => ['nullable', 'integer'],
        ]);

        try {
            $targetFolder = ($request->filled('folder_id') && MediaLibrarySchema::hasVideoFolders())
                ? VideoLibraryFolder::query()->findOrFail((int) $request->input('folder_id'))
                : null;

            $video = app(VideoLibraryService::class)->moveVideo($video, $targetFolder);

            return $this->apiSuccess([
                'id' => (int) $video->id,
                'folder_id' => $video->folder_id,
                'path' => $video->path,
            ]);
        } catch (\Throwable $e) {
            return $this->apiError(['video' => [$e->getMessage()]], 422);
        }
    }

    protected function folderRow(VideoLibraryFolder $folder): array
    {
        return [
            'id' => (int) $folder->id,
            'name' => $folder->name,
            'path' => $folder->path,
            'parent_id' => $folder->parent_id,
        ];
    }
}
