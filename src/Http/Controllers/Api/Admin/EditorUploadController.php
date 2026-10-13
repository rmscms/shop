<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EditorUploadController extends BaseController
{
    /**
     * @OA\Post(
     *     path="/admin/shop/editor/upload",
     *     tags={"Admin - Utilities"},
     *     summary="Upload image for editor (CKEditor)",
     *     security={{"admin":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="upload", type="string", format="binary", description="Image file"),
     *                 @OA\Property(property="context", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Image uploaded",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="url", type="string"),
     *                 @OA\Property(property="urls", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function upload(Request $request)
    {
        $request->validate([
            'upload' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/avif',
                'max:4096',
            ],
            'context' => ['nullable', 'string', 'max:50'],
        ]);

        $file = $request->file('upload');
        if (!$file->isValid()) {
            return $this->apiError(['upload' => ['Invalid upload']], 422);
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $uuid = (string) Str::uuid();
        $dir = 'uploads/ckeditor/' . date('Y/m');
        $origRel = $dir . '/orig/' . $uuid . '.' . $ext;

        $stream = fopen($file->getRealPath(), 'r');
        Storage::disk('public')->put($origRel, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $url = Storage::disk('public')->url($origRel);
        $urls = ['default' => $url];

        // Try to create WEBP version
        $webpRel = $dir . '/webp/' . $uuid . '.webp';
        try {
            if (class_exists(\Intervention\Image\ImageManager::class)) {
                $manager = \Intervention\Image\ImageManager::gd();
                $img = $manager->read($file->getRealPath());
                try {
                    if (method_exists($img, 'orientate')) {
                        $img->orientate();
                    }
                } catch (\Throwable $e) {
                }
                @mkdir(dirname(Storage::disk('public')->path($webpRel)), 0777, true);
                $img->save(Storage::disk('public')->path($webpRel), 82, 'webp');
                if (Storage::disk('public')->exists($webpRel)) {
                    $urls['webp'] = Storage::disk('public')->url($webpRel);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $this->apiSuccess([
            'url' => $url,
            'urls' => $urls,
        ]);
    }
}
