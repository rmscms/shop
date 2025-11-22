<?php

namespace RMS\Shop\Http\Controllers\Admin\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use RMS\Shop\Jobs\TranscodeProductVideo;
use RMS\Shop\Models\Product;
use RMS\Shop\Models\ProductVideo;
use RMS\Shop\Models\ProductCombination;
use RMS\Shop\Models\VideoAssignment;
use RMS\Shop\Models\VideoLibrary;
use RMS\Shop\Services\VideoLibraryService;
use Illuminate\Http\JsonResponse;

trait ProductVideoTrait
{
    public function videoPage(Request $request, int $productId)
    {
        $product = Product::query()->find((int)$productId);
        abort_if(!$product, 404);
        $video = ProductVideo::query()->where('product_id', (int)$productId)->first();
        
        $this->view->usePackageNamespace('shop')
            ->setTheme('admin')
            ->setTpl('products.video')
            ->withVariables([
                'product' => $product,
                'video' => $video,
                'uploadUrl' => route('admin.shop.products.video.upload', ['product' => (int)$productId]),
                'deleteUrl' => route('admin.shop.products.video.delete', ['product' => (int)$productId]),
            ]);
        
        return $this->view();
    }

    public function uploadVideo(Request $request, int $productId)
    {
        $request->validate([
            'video' => ['required','file','mimetypes:video/mp4,video/webm,video/quicktime,video/x-matroska','max:512000'],
        ]);
        /** @var UploadedFile $file */
        $file = $request->file('video');
        $ext = strtolower($file->getClientOriginalExtension());
        $name = (string) Str::uuid().'.'.$ext;
        $dir = 'uploads/products/'.$productId.'/video/orig';
        $rel = $file->storeAs($dir, $name, 'public');

        $video = ProductVideo::create([
            'product_id' => (int)$productId,
            'title' => null,
            'source_path' => $rel,
            'hls_master_path' => null,
            'poster_path' => null,
            'size_bytes' => (int)($file->getSize() ?: 0),
        ]);

        TranscodeProductVideo::dispatch((int)$productId, (int)$video->id, $rel);

        return response()->json(['ok' => true, 'video_id' => (int)$video->id, 'message' => trans('admin.file_uploaded') ?? 'ویدیو آپلود شد و در صف پردازش قرار گرفت.']);
    }

    public function deleteVideo(Request $request, int $productId, int $videoId)
    {
        $row = ProductVideo::query()->where('id', (int)$videoId)->where('product_id', (int)$productId)->first();
        if (!$row) { return response()->json(['ok'=>false,'error'=>'not_found'],404); }
        $disk = Storage::disk('public');
        if (!empty($row->source_path)) { $disk->delete((string)$row->source_path); }
        if (!empty($row->poster_path)) { $disk->delete((string)$row->poster_path); }
        if (!empty($row->hls_master_path)) {
            $hlsDir = dirname((string)$row->hls_master_path);
            foreach ($disk->allFiles($hlsDir) as $f) { $disk->delete($f); }
            @rmdir($disk->path($hlsDir.'/h480'));
            @rmdir($disk->path($hlsDir.'/h720'));
            @rmdir($disk->path($hlsDir));
        }
        $row->delete();
        return response()->json(['ok'=>true]);
    }

    public function videoChunkInit(Request $request, int $productId)
    {
        $request->validate([
            'filename' => ['required','string','max:190'],
        ]);
        $uploadId = (string) Str::uuid();
        $tmpDir = storage_path('app/tmp/video/'.$productId);
        @mkdir($tmpDir, 0777, true);
        @file_put_contents($tmpDir.DIRECTORY_SEPARATOR.$uploadId.'.json', json_encode([
            'product_id' => (int)$productId,
            'filename' => (string)$request->input('filename'),
            'created_at' => now()->toISOString(),
        ], JSON_UNESCAPED_SLASHES));
        return response()->json(['ok'=>true,'upload_id'=>$uploadId]);
    }

    public function videoChunk(Request $request, int $productId)
    {
        $preChunk = $request->file('chunk');
        $preErrCode = ($preChunk instanceof UploadedFile) ? $preChunk->getError() : null;
        $preErrMsg = ($preChunk instanceof UploadedFile && method_exists($preChunk, 'getErrorMessage')) ? $preChunk->getErrorMessage() : null;
        $serverLimits = [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_file_uploads' => ini_get('max_file_uploads'),
            'file_uploads' => ini_get('file_uploads'),
            'memory_limit' => ini_get('memory_limit'),
        ];
        Log::info('videoChunk: pre-validate', [
            'product_id' => $productId,
            'upload_id' => (string)$request->input('upload_id'),
            'index' => (int)$request->input('index'),
            'count' => (int)$request->input('count'),
            'filename' => (string)$request->input('filename'),
            'content_length' => $request->headers->get('Content-Length'),
            'upload_err_code' => $preErrCode,
            'upload_err_message' => $preErrMsg,
            'server_limits' => $serverLimits,
        ]);

        $request->validate([
            'upload_id' => ['required','string'],
            'index' => ['required','integer','min:0'],
            'count' => ['required','integer','min:1'],
            'filename' => ['required','string','max:190'],
            'chunk' => ['required','file','max:20480'],
        ]);

        $uploadId = (string)$request->input('upload_id');
        $index = (int)$request->input('index');
        $count = (int)$request->input('count');
        $filename = (string)$request->input('filename');
        /** @var UploadedFile $chunk */
        $chunk = $request->file('chunk');

        $postErrCode = $chunk?->getError();
        $postErrMsg = method_exists($chunk, 'getErrorMessage') ? $chunk->getErrorMessage() : null;
        Log::info('videoChunk: received file', [
            'product_id' => $productId,
            'upload_id' => $uploadId,
            'index' => $index,
            'count' => $count,
            'filename' => $filename,
            'size_bytes' => $chunk?->getSize(),
            'mime' => $chunk?->getMimeType(),
            'is_valid' => $chunk?->isValid(),
            'upload_err_code' => $postErrCode,
            'upload_err_message' => $postErrMsg,
        ]);

        $tmpDir = storage_path('app/tmp/video/'.$productId);
        @mkdir($tmpDir, 0777, true);
        $tmpFile = $tmpDir.DIRECTORY_SEPARATOR.$uploadId.'.part';
        $in = fopen($chunk->getRealPath(), 'rb');
        $out = fopen($tmpFile, $index === 0 ? 'wb' : 'ab');
        stream_copy_to_stream($in, $out);
        fclose($in); fclose($out);

        $isLast = ($index + 1) >= $count;
        if ($isLast) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION) ?: 'mp4');
            $destName = (string) Str::uuid().'.'.$ext;
            $dir = 'uploads/products/'.$productId.'/video/orig';
            @mkdir(Storage::disk('public')->path($dir), 0777, true);
            $destAbs = Storage::disk('public')->path($dir.'/'.$destName);
            @rename($tmpFile, $destAbs);
            $rel = $dir.'/'.$destName;

            $video = ProductVideo::create([
                'product_id' => (int)$productId,
                'title' => null,
                'source_path' => $rel,
                'hls_master_path' => null,
                'poster_path' => null,
                'size_bytes' => is_file($destAbs) ? filesize($destAbs) : null,
            ]);

            @unlink($tmpDir.DIRECTORY_SEPARATOR.$uploadId.'.json');
            TranscodeProductVideo::dispatch((int)$productId, (int)$video->id, $rel);

            return response()->json(['ok'=>true,'done'=>true,'video_id'=>(int)$video->id]);
        }

        return response()->json(['ok'=>true,'done'=>false,'received'=>$index]);
    }

    public function listVideos(Request $request, int $productId)
    {
        $combinationId = $request->query('combination_id');

        try {
            $videoService = app(VideoLibraryService::class);
            
            if ($combinationId) {
                $combination = ProductCombination::where('id', (int)$combinationId)
                    ->where('product_id', (int)$productId)
                    ->firstOrFail();
                $videos = $videoService->getCombinationVideos($combination);
            } else {
                $product = Product::findOrFail($productId);
                $videos = $videoService->getProductVideos($product);
            }

            $rows = $videos->map(function($video) {
                return [
                    'id' => $video->pivot->id, // Assignment ID
                    'video_id' => $video->id, // Video Library ID
                    'path' => $video->path,
                    'url' => $video->url,
                    'hls_path' => $video->hls_path,
                    'poster_path' => $video->poster_path,
                    'is_main' => $video->pivot->is_main,
                    'sort' => $video->pivot->sort,
                ];
            });

            return response()->json(['ok' => true, 'data' => $rows]);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getVideoLibrary(Request $request, int $productId)
    {
        try {
            $product = Product::findOrFail($productId);
            $videoService = app(VideoLibraryService::class);
            $videos = $videoService->searchVideosForProduct(
                $product,
                $request->query('search', ''),
                $request->query('page', 1)
            );

            return response()->json($videos);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function detachVideos(Request $request, int $productId)
    {
        $combinationId = $request->input('combination_id');
        $videoId = $request->input('video_id');
        $assignmentId = $request->input('combination_video_id');

        try {
            if ($assignmentId) {
                VideoAssignment::where('id', (int)$assignmentId)->delete();
            } elseif ($combinationId && $videoId) {
                VideoAssignment::query()
                    ->where('assignable_type', ProductCombination::class)
                    ->where('assignable_id', (int)$combinationId)
                    ->where('video_id', (int)$videoId)
                    ->delete();
            } else {
                return response()->json(['ok' => false, 'error' => 'missing_parameters'], 400);
            }

            return response()->json(['ok' => true, 'message' => 'Video detached successfully']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function setMainVideo(Request $request, int $productId): JsonResponse
    {
        $request->validate([
            'video_id' => ['required', 'integer', 'exists:video_library,id'],
        ]);

        try {
            $video = VideoLibrary::findOrFail($request->video_id);
            $product = Product::findOrFail($productId);
            $videoService = app(VideoLibraryService::class);
            $videoService->setMainVideo($video, $product);

            return response()->json(['ok' => true, 'message' => 'ویدیو اصلی تنظیم شد']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function updateVideoSort(Request $request, int $productId): JsonResponse
    {
        $request->validate([
            'video_ids' => ['required', 'array'],
            'video_ids.*' => ['integer', 'exists:video_library,id'],
            'sorts' => ['required', 'array', 'size:' . count($request->video_ids)],
            'sorts.*' => ['integer', 'min:0'],
        ]);

        try {
            $product = Product::findOrFail($productId);
            VideoLibraryService::updateVideoSort($product, $request->video_ids, $request->sorts);

            return response()->json(['ok' => true, 'message' => 'ترتیب ویدیوها بروزرسانی شد']);
        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
