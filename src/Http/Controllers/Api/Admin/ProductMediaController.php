<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;
use RMS\Shop\Jobs\TranscodeProductVideo;
use RMS\Shop\Models\ImageAssignment;
use RMS\Shop\Models\ImageLibrary;
use RMS\Shop\Models\Product;
use RMS\Shop\Models\ProductCombination;
use RMS\Shop\Models\ProductVideo;
use RMS\Shop\Models\VideoAssignment;
use RMS\Shop\Models\VideoLibrary;
use RMS\Shop\Services\ImageLibraryService;
use RMS\Shop\Services\ProductImagesService;
use RMS\Shop\Services\VideoLibraryService;

class ProductMediaController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/admin/shop/products/{id}/images",
     *     tags={"Admin - Media"},
     *     summary="List product gallery images",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="combination_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Gallery"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function listImages(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $combinationId = $request->query('combination_id');

        if ($combinationId) {
            $combination = ProductCombination::query()
                ->where('id', (int) $combinationId)
                ->where('product_id', $product->id)
                ->first();

            if (!$combination) {
                return $this->apiError(['combination' => ['ترکیب پیدا نشد.']], 404);
            }

            $images = ImageLibraryService::getCombinationImages($combination);
        } else {
            $images = ImageLibraryService::getProductImages($product);
        }

        return $this->apiSuccess($images->map(fn ($image) => $this->imageRow($image))->values());
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/products/{id}/images",
     *     tags={"Admin - Media"},
     *     summary="Upload an image into the product gallery",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=201, description="Uploaded"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function uploadImage(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $combination = null;
        if ($request->filled('combination_id')) {
            $combination = ProductCombination::query()
                ->where('id', (int) $request->input('combination_id'))
                ->where('product_id', $product->id)
                ->first();
            if (!$combination) {
                return $this->apiError(['combination' => ['ترکیب پیدا نشد.']], 404);
            }
        }

        $file = $this->firstUploadedFile($request);
        if (!$file) {
            return $this->apiError(['file' => ['فایل تصویر لازم است.']], 422);
        }

        $request->validate([
            'file' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120'],
        ]);

        $image = ImageLibraryService::uploadImage($file);
        $target = $combination ?: $product;
        $assignment = ImageLibraryService::assignImage($image, $target);

        return $this->apiSuccess([
            'image_id' => (int) $image->id,
            'assignment_id' => (int) $assignment->id,
            'url' => $image->avif_url ?: $image->url,
        ], [], 201);
    }

    /**
     * @OA\Delete(
     *     path="/admin/shop/products/{id}/images",
     *     tags={"Admin - Media"},
     *     summary="Detach images from a product",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Detached"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function detachImages(Request $request, int $id)
    {
        $product = Product::findOrFail($id);

        if ($request->filled('assignment_id')) {
            ImageAssignment::query()->where('id', (int) $request->input('assignment_id'))->delete();

            return $this->apiSuccess(['detached' => true]);
        }

        $imageIds = $request->input('image_ids', $request->filled('image_id') ? [$request->input('image_id')] : []);
        if (!is_array($imageIds) || $imageIds === []) {
            return $this->apiError(['image_ids' => ['شناسه تصویر لازم است.']], 422);
        }

        foreach ($imageIds as $imageId) {
            ProductImagesService::detachFromProduct($product->id, (int) $imageId);
        }

        return $this->apiSuccess(['detached' => count($imageIds)]);
    }

    /**
     * @OA\Delete(
     *     path="/admin/shop/products/{id}/images/{image}",
     *     tags={"Admin - Media"},
     *     summary="Detach one library image from the product",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="image", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Detached"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function deleteImage(int $id, int $image)
    {
        $product = Product::findOrFail($id);
        $libraryImage = ImageLibrary::findOrFail($image);
        $detached = ImageLibraryService::detachImage($libraryImage, $product);

        if (!$detached) {
            return $this->apiError(['image' => ['تصویر روی این محصول نیست.']], 404);
        }

        return $this->apiSuccess(['detached' => true]);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/products/{id}/images/set-main",
     *     tags={"Admin - Media"},
     *     summary="Set the main product image",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function setMainImage(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $validated = $request->validate([
            'image_id' => ['required', 'integer', 'exists:image_library,id'],
        ]);

        ProductImagesService::setMainImage($product->id, (int) $validated['image_id']);

        return $this->apiSuccess(['image_id' => (int) $validated['image_id']]);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/products/{id}/images/sort",
     *     tags={"Admin - Media"},
     *     summary="Sort product gallery images",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function sortImages(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.sort' => ['required', 'integer', 'min:0'],
        ]);

        ProductImagesService::updateSort($product->id, $validated['items']);

        return $this->apiSuccess(['sorted' => count($validated['items'])]);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/products/{id}/images/{image}/combinations",
     *     tags={"Admin - Media"},
     *     summary="Combination ids that use this image",
     *     security={{"admin":{}}},
     *     @OA\Response(response=200, description="Ids"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function assignedCombinations(int $id, int $image)
    {
        $product = Product::findOrFail($id);
        ImageLibrary::findOrFail($image);

        $ids = ImageAssignment::query()
            ->where('image_id', $image)
            ->where('assignable_type', ProductCombination::class)
            ->whereIn('assignable_id', function ($query) use ($product) {
                $query->select('id')->from('product_combinations')->where('product_id', $product->id);
            })
            ->pluck('assignable_id')
            ->map(fn ($value) => (int) $value)
            ->values();

        return $this->apiSuccess($ids);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/products/{id}/images/{image}/combinations",
     *     tags={"Admin - Media"},
     *     summary="Assign an image to product combinations",
     *     security={{"admin":{}}},
     *     @OA\Response(response=200, description="Assigned"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function assignCombinations(Request $request, int $id, int $image)
    {
        $product = Product::findOrFail($id);
        $libraryImage = ImageLibrary::findOrFail($image);
        $validated = $request->validate([
            'combination_ids' => ['required', 'array'],
            'combination_ids.*' => ['integer', 'exists:product_combinations,id'],
        ]);

        $valid = ProductCombination::query()
            ->where('product_id', $product->id)
            ->whereIn('id', $validated['combination_ids'])
            ->pluck('id');

        if ($valid->isEmpty()) {
            return $this->apiError(['combination_ids' => ['ترکیب معتبری برای این محصول نیست.']], 422);
        }

        ImageAssignment::query()
            ->where('image_id', $libraryImage->id)
            ->where('assignable_type', ProductCombination::class)
            ->whereIn('assignable_id', function ($query) use ($product) {
                $query->select('id')->from('product_combinations')->where('product_id', $product->id);
            })
            ->delete();

        foreach ($valid as $combinationId) {
            $combination = ProductCombination::findOrFail($combinationId);
            ImageLibraryService::assignImage($libraryImage, $combination);
        }

        return $this->apiSuccess(['assigned' => $valid->count()]);
    }

    /**
     * @OA\Delete(
     *     path="/admin/shop/products/{id}/images/{image}/combinations",
     *     tags={"Admin - Media"},
     *     summary="Detach an image from the product combinations",
     *     security={{"admin":{}}},
     *     @OA\Response(response=200, description="Detached"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function detachCombinations(int $id, int $image)
    {
        $product = Product::findOrFail($id);
        ImageLibrary::findOrFail($image);

        $deleted = ImageAssignment::query()
            ->where('image_id', $image)
            ->where('assignable_type', ProductCombination::class)
            ->whereIn('assignable_id', function ($query) use ($product) {
                $query->select('id')->from('product_combinations')->where('product_id', $product->id);
            })
            ->delete();

        return $this->apiSuccess(['detached' => $deleted]);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/products/{id}/videos",
     *     tags={"Admin - Media"},
     *     summary="List product videos",
     *     security={{"admin":{}}},
     *     @OA\Response(response=200, description="Videos"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function listVideos(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $service = app(VideoLibraryService::class);
        $combinationId = $request->query('combination_id');

        if ($combinationId) {
            $combination = ProductCombination::query()
                ->where('id', (int) $combinationId)
                ->where('product_id', $product->id)
                ->firstOrFail();
            $videos = $service->getCombinationVideos($combination);
        } else {
            $videos = $service->getProductVideos($product);
        }

        return $this->apiSuccess($videos->map(fn ($video) => [
            'id' => $video->pivot->id ?? null,
            'video_id' => (int) $video->id,
            'path' => $video->path,
            'url' => $video->url,
            'hls_path' => $video->hls_path,
            'poster_path' => $video->poster_path,
            'is_main' => (bool) ($video->pivot->is_main ?? false),
            'sort' => (int) ($video->pivot->sort ?? 0),
        ])->values());
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/products/{id}/videos/library",
     *     tags={"Admin - Media"},
     *     summary="Search the video library for a product",
     *     security={{"admin":{}}},
     *     @OA\Response(response=200, description="Library page"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function videoLibrary(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $page = app(VideoLibraryService::class)->searchVideosForProduct(
            $product,
            (string) $request->query('search', ''),
            (int) $request->query('page', 1)
        );

        return $this->apiSuccess($page);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/products/{id}/videos",
     *     tags={"Admin - Media"},
     *     summary="Upload a product video",
     *     security={{"admin":{}}},
     *     @OA\Response(response=201, description="Queued"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function uploadVideo(Request $request, int $id)
    {
        Product::findOrFail($id);
        $request->validate([
            'video' => ['required', 'file', 'mimetypes:video/mp4,video/webm,video/quicktime,video/x-matroska', 'max:512000'],
        ]);

        $file = $request->file('video');
        $ext = strtolower($file->getClientOriginalExtension());
        $name = (string) Str::uuid().'.'.$ext;
        $dir = 'uploads/products/'.$id.'/video/orig';
        $rel = $file->storeAs($dir, $name, 'public');

        $video = ProductVideo::create([
            'product_id' => $id,
            'title' => null,
            'source_path' => $rel,
            'hls_master_path' => null,
            'poster_path' => null,
            'size_bytes' => (int) ($file->getSize() ?: 0),
        ]);

        TranscodeProductVideo::dispatch($id, (int) $video->id, $rel);

        return $this->apiSuccess(['video_id' => (int) $video->id], [], 201);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/products/{id}/video",
     *     tags={"Admin - Media"},
     *     summary="List uploaded product video files",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Product videos"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function listProductVideos(int $id)
    {
        Product::findOrFail($id);

        $rows = ProductVideo::query()
            ->where('product_id', $id)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(fn (ProductVideo $video) => [
                'id' => (int) $video->id,
                'title' => $video->title,
                'source_path' => $video->source_path,
                'hls_master_path' => $video->hls_master_path,
                'poster_path' => $video->poster_path,
                'size_bytes' => $video->size_bytes,
                'sort' => (int) $video->sort,
            ])
            ->values();

        return $this->apiSuccess($rows);
    }

    /**
     * @OA\Delete(
     *     path="/admin/shop/products/{id}/video/{video}",
     *     tags={"Admin - Media"},
     *     summary="Delete an uploaded product video file",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="video", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function deleteProductVideo(int $id, int $video)
    {
        $row = ProductVideo::query()->where('id', $video)->where('product_id', $id)->first();
        if (!$row) {
            return $this->apiError(['video' => ['ویدیو پیدا نشد.']], 404);
        }

        $disk = Storage::disk('public');
        if (!empty($row->source_path)) {
            $disk->delete((string) $row->source_path);
        }
        if (!empty($row->poster_path)) {
            $disk->delete((string) $row->poster_path);
        }
        if (!empty($row->hls_master_path)) {
            $hlsDir = dirname((string) $row->hls_master_path);
            foreach ($disk->allFiles($hlsDir) as $file) {
                $disk->delete($file);
            }
        }
        $row->delete();

        return $this->apiSuccess(['deleted' => true]);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/products/{id}/videos/chunk-init",
     *     tags={"Admin - Media"},
     *     summary="Start a chunked video upload",
     *     security={{"admin":{}}},
     *     @OA\Response(response=200, description="Upload id"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function videoChunkInit(Request $request, int $id)
    {
        Product::findOrFail($id);
        $request->validate([
            'filename' => ['required', 'string', 'max:190'],
        ]);

        $uploadId = (string) Str::uuid();
        $tmpDir = storage_path('app/tmp/video/'.$id);
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }
        file_put_contents($tmpDir.DIRECTORY_SEPARATOR.$uploadId.'.json', json_encode([
            'product_id' => $id,
            'filename' => (string) $request->input('filename'),
            'created_at' => now()->toISOString(),
        ], JSON_UNESCAPED_SLASHES));

        return $this->apiSuccess(['upload_id' => $uploadId]);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/products/{id}/videos/chunk",
     *     tags={"Admin - Media"},
     *     summary="Upload one video chunk",
     *     security={{"admin":{}}},
     *     @OA\Response(response=200, description="Chunk stored"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function videoChunk(Request $request, int $id)
    {
        Product::findOrFail($id);
        $request->validate([
            'upload_id' => ['required', 'string'],
            'index' => ['required', 'integer', 'min:0'],
            'count' => ['required', 'integer', 'min:1'],
            'filename' => ['required', 'string', 'max:190'],
            'chunk' => ['required', 'file', 'max:20480'],
        ]);

        $uploadId = (string) $request->input('upload_id');
        $index = (int) $request->input('index');
        $count = (int) $request->input('count');
        $filename = (string) $request->input('filename');
        $chunk = $request->file('chunk');

        $tmpDir = storage_path('app/tmp/video/'.$id);
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }
        $tmpFile = $tmpDir.DIRECTORY_SEPARATOR.$uploadId.'.part';
        $in = fopen($chunk->getRealPath(), 'rb');
        $out = fopen($tmpFile, $index === 0 ? 'wb' : 'ab');
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        if (($index + 1) < $count) {
            return $this->apiSuccess(['done' => false, 'received' => $index]);
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION) ?: 'mp4');
        $destName = (string) Str::uuid().'.'.$ext;
        $dir = 'uploads/products/'.$id.'/video/orig';
        Storage::disk('public')->makeDirectory($dir);
        $destAbs = Storage::disk('public')->path($dir.'/'.$destName);
        rename($tmpFile, $destAbs);
        $rel = $dir.'/'.$destName;

        $video = ProductVideo::create([
            'product_id' => $id,
            'title' => null,
            'source_path' => $rel,
            'hls_master_path' => null,
            'poster_path' => null,
            'size_bytes' => is_file($destAbs) ? filesize($destAbs) : null,
        ]);

        @unlink($tmpDir.DIRECTORY_SEPARATOR.$uploadId.'.json');
        TranscodeProductVideo::dispatch($id, (int) $video->id, $rel);

        return $this->apiSuccess(['done' => true, 'video_id' => (int) $video->id]);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/products/{id}/videos/assign",
     *     tags={"Admin - Media"},
     *     summary="Assign library videos to a product",
     *     security={{"admin":{}}},
     *     @OA\Response(response=200, description="Assigned"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function assignVideos(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $validated = $request->validate([
            'video_ids' => ['required', 'array'],
            'video_ids.*' => ['integer', 'exists:video_library,id'],
        ]);

        app(VideoLibraryService::class)->assignVideosTo($product, $validated['video_ids']);

        return $this->apiSuccess(['assigned' => count($validated['video_ids'])]);
    }

    /**
     * @OA\Delete(
     *     path="/admin/shop/products/{id}/videos",
     *     tags={"Admin - Media"},
     *     summary="Detach videos from a product",
     *     security={{"admin":{}}},
     *     @OA\Response(response=200, description="Detached"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function detachVideos(Request $request, int $id)
    {
        $product = Product::findOrFail($id);

        if ($request->filled('assignment_id')) {
            VideoAssignment::query()->where('id', (int) $request->input('assignment_id'))->delete();

            return $this->apiSuccess(['detached' => true]);
        }

        $validated = $request->validate([
            'video_ids' => ['required', 'array'],
            'video_ids.*' => ['integer', 'exists:video_library,id'],
        ]);

        app(VideoLibraryService::class)->detachVideosFrom($product, $validated['video_ids']);

        return $this->apiSuccess(['detached' => count($validated['video_ids'])]);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/products/{id}/videos/set-main",
     *     tags={"Admin - Media"},
     *     summary="Set the main product video",
     *     security={{"admin":{}}},
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function setMainVideo(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $validated = $request->validate([
            'video_id' => ['required', 'integer', 'exists:video_library,id'],
        ]);

        $video = VideoLibrary::findOrFail($validated['video_id']);
        app(VideoLibraryService::class)->setMainVideo($video, $product);

        return $this->apiSuccess(['video_id' => (int) $video->id]);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/products/{id}/videos/sort",
     *     tags={"Admin - Media"},
     *     summary="Sort product videos",
     *     security={{"admin":{}}},
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function sortVideos(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $validated = $request->validate([
            'video_ids' => ['required', 'array'],
            'video_ids.*' => ['integer', 'exists:video_library,id'],
        ]);

        app(VideoLibraryService::class)->updateSort($product, $validated['video_ids']);

        return $this->apiSuccess(['sorted' => count($validated['video_ids'])]);
    }

    protected function imageRow($image): array
    {
        return [
            'id' => $image->pivot->id ?? null,
            'image_id' => (int) $image->id,
            'path' => $image->path,
            'url' => $image->url,
            'avif_url' => $image->avif_url,
            'filename' => $image->filename,
            'is_main' => (bool) ($image->pivot->is_main ?? false),
            'sort' => (int) ($image->pivot->sort ?? 0),
        ];
    }

    protected function firstUploadedFile(Request $request): ?UploadedFile
    {
        if ($request->file('file') instanceof UploadedFile) {
            return $request->file('file');
        }

        $found = null;
        $walk = function ($value) use (&$walk, &$found) {
            if ($found) {
                return;
            }
            if (is_array($value)) {
                foreach ($value as $item) {
                    $walk($item);
                }
            } elseif ($value instanceof UploadedFile) {
                $found = $value;
            }
        };
        foreach ($request->allFiles() as $file) {
            $walk($file);
        }

        return $found;
    }
}
