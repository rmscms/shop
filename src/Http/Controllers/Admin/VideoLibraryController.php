<?php

namespace RMS\Shop\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\List\HasList;
use RMS\Shop\Http\Controllers\Admin\ShopAdminController;
use RMS\Core\Data\Field;
use RMS\Shop\Models\VideoLibrary;
use RMS\Shop\Models\Product;
use RMS\Shop\Services\VideoLibraryService;

class VideoLibraryController extends ShopAdminController implements HasList, HasForm, ShouldFilter
{
    public function table(): string
    {
        return 'video_library';
    }

    public function modelName(): string
    {
        return VideoLibrary::class;
    }

    public function baseRoute(): string
    {
        return 'video-library';
    }

    public function routeParameter(): string
    {
        return 'video';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('filename', 'نام فایل')->readonly(),
            Field::string('path', 'مسیر')->readonly(),
            Field::number('size_bytes', 'حجم')->readonly(),
            Field::number('duration_seconds', 'مدت (ثانیه)')->readonly(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::string('id', 'ID')->sortable(),
            Field::string('filename', 'نام فایل')->sortable(),
            Field::string('formatted_size', 'حجم'),
            Field::number('duration_seconds', 'مدت (ثانیه)'),
            Field::date('created_at', 'تاریخ ایجاد')->sortable(),
        ];
    }

    protected function beforeRenderView(): void
    {
        parent::beforeRenderView();
        $this->view
            ->withJs('vendor/shop/admin/js/video-library.js', true)
            ->withJsVariables([
                'videoLibraryRoutes' => [
                    'upload' => route('admin.shop.video-library.upload'),
                    'destroy' => route('admin.shop.video-library.ajax-destroy', '__ID__'),
                ]
            ]);
    }

    public function index(Request $request)
    {
        $this->title = 'کتابخانه ویدیو';

        $search = $request->get('search', '');
        $videoService = app(VideoLibraryService::class);
        $videos = $videoService->searchVideos($search);

        $this->view->usePackageNamespace('shop')
            ->setTheme('admin')
            ->setTpl('video-library.index')
            ->withJs('vendor/shop/admin/js/video-library.js', true)
            ->withPlugins(['hls', 'confirm-modal'])
            ->withVariables(compact('videos', 'search'))
            ->withJsVariables([
                'VideoLibraryConfig' => [
                    'csrf' => csrf_token(),
                    'routes' => [
                        'upload' => route('admin.shop.video-library.upload'),
                        'destroy' => route('admin.shop.video-library.ajax-destroy', '__ID__'),
                    ],
                ],
            ]);

        return $this->view();
    }

    public function upload(Request $request): JsonResponse
    {
        // Handle chunked upload
        if ($request->has('chunk')) {
            $uploadId = $request->input('upload_id');
            $chunkIndex = (int)$request->input('chunk_index');
            $totalChunks = (int)$request->input('total_chunks');
            $filename = $request->input('filename');

            $tempDir = storage_path('app/temp/video-uploads/' . $uploadId);
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0777, true);
            }

            $chunkPath = $tempDir . '/chunk_' . $chunkIndex;
            move_uploaded_file($request->file('chunk')->getPathname(), $chunkPath);

            // If all chunks received, merge them
            if ($chunkIndex == $totalChunks - 1) {
                $finalPath = storage_path('app/temp/' . $filename);
                $finalFile = fopen($finalPath, 'wb');

                for ($i = 0; $i < $totalChunks; $i++) {
                    $chunk = file_get_contents($tempDir . '/chunk_' . $i);
                    fwrite($finalFile, $chunk);
                }
                fclose($finalFile);

                // Clean up chunks
                array_map('unlink', glob($tempDir . '/*'));
                rmdir($tempDir);

                // Now upload to storage
                $customTitle = $request->input('custom_name');
                $videoService = app(VideoLibraryService::class);
                $uploadedFile = new \Illuminate\Http\UploadedFile(
                    $finalPath,
                    $filename,
                    mime_content_type($finalPath),
                    null,
                    true
                );
                $video = $videoService->upload($uploadedFile, $customTitle);

                // Clean up temp file
                @unlink($finalPath);

                return response()->json([
                    'success' => true,
                    'video' => $video,
                    'message' => 'ویدیو با موفقیت آپلود شد',
                    'completed' => true
                ]);
            }

            return response()->json([
                'success' => true,
                'chunk_index' => $chunkIndex,
                'completed' => false
            ]);
        }

        // Regular upload (fallback)
        $request->validate([
            'video' => 'required|file|mimes:mp4,mov,avi,webm|max:512000',
        ]);

        $videoService = app(VideoLibraryService::class);
        $video = $videoService->upload($request->file('video'));

        return response()->json([
            'success' => true,
            'video' => $video,
            'message' => 'ویدیو با موفقیت آپلود شد',
        ]);
    }

    public function ajaxDestroy($id): JsonResponse
    {
        $video = VideoLibrary::findOrFail($id);
        $videoService = app(VideoLibraryService::class);
        $videoService->delete($video);

        return response()->json([
            'success' => true,
            'message' => 'ویدیو حذف شد',
        ]);
    }

    public function getProductVideos($productId, Request $request): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $videoService = app(VideoLibraryService::class);
        $videos = $videoService->searchVideosForProduct(
            $product,
            $request->query('search', ''),
            $request->query('page', 1)
        );

        return response()->json($videos);
    }

    public function assignToProduct($productId, Request $request): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $request->validate([
            'video_ids' => 'required|array',
            'video_ids.*' => 'integer|exists:video_library,id'
        ]);

        $videoService = app(VideoLibraryService::class);
        $videoService->assignVideosTo($product, $request->video_ids);

        return response()->json([
            'success' => true,
            'message' => 'ویدیوها به محصول اختصاص یافتند',
        ]);
    }

    public function detachFromProduct($productId, Request $request): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $request->validate([
            'video_ids' => 'required|array',
            'video_ids.*' => 'integer|exists:video_library,id'
        ]);

        $videoService = app(VideoLibraryService::class);
        $videoService->detachVideosFrom($product, $request->video_ids);

        return response()->json([
            'success' => true,
            'message' => 'ویدیوها از محصول جدا شدند',
        ]);
    }

    public function setMainVideo($productId, Request $request): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $request->validate(['video_id' => 'required|integer|exists:video_library,id']);

        $video = VideoLibrary::findOrFail($request->video_id);
        $videoService = app(VideoLibraryService::class);
        $videoService->setMainVideo($video, $product);

        return response()->json([
            'success' => true,
            'message' => 'ویدیو اصلی تنظیم شد',
        ]);
    }

    public function updateSort($productId, Request $request): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $request->validate(['sort_order' => 'required|array']);

        $videoService = app(VideoLibraryService::class);
        $videoService->updateSort($product, $request->sort_order);

        return response()->json([
            'success' => true,
            'message' => 'ترتیب ویدیوها ذخیره شد',
        ]);
    }
}
