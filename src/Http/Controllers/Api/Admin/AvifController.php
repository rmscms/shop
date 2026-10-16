<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;
use RMS\Shop\Helpers\AvifHelper;
use RMS\Shop\Jobs\RegenerateAvifForDirectories;
use RMS\Shop\Models\AvifDirectory;

class AvifController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/admin/shop/avif/stats",
     *     tags={"Admin - Utilities"},
     *     summary="Get AVIF conversion statistics",
     *     security={{"admin":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="AVIF statistics",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function stats()
    {
        $stats = AvifHelper::getDirectoryStats();

        return $this->apiSuccess($stats);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/avif/regenerate-all",
     *     tags={"Admin - Utilities"},
     *     summary="Regenerate all AVIF files",
     *     security={{"admin":{}}},
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="clean_existing", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Regeneration queued",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function regenerateAll(Request $request)
    {
        try {
            $clean = (bool) $request->boolean('clean_existing', false);
            RegenerateAvifForDirectories::dispatch(AvifHelper::getTargetDirectories(), $clean);

            return $this->apiSuccess([
                'queued' => count(AvifHelper::getTargetDirectories()),
                'message' => 'بازسازی AVIF در صف قرار گرفت.',
            ]);
        } catch (\Exception $e) {
            return $this->apiError(['avif' => [$e->getMessage()]], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/avif/clean-all",
     *     tags={"Admin - Utilities"},
     *     summary="Clean all AVIF files",
     *     security={{"admin":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="AVIF files cleaned",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function cleanAll()
    {
        try {
            $result = AvifHelper::cleanAvifFiles();

            return $this->apiSuccess([
                'message' => 'فایل‌های AVIF پاکسازی شدند.',
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->apiError(['avif' => [$e->getMessage()]], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/avif/directories",
     *     tags={"Admin - Utilities"},
     *     summary="List AVIF directories",
     *     security={{"admin":{}}},
     *     @OA\Response(response=200, description="Directories"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function directories()
    {
        $rows = AvifDirectory::query()->orderBy('type')->orderBy('path')->get();

        return $this->apiSuccess($rows);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/avif/directories",
     *     tags={"Admin - Utilities"},
     *     summary="Create or enable an AVIF directory",
     *     security={{"admin":{}}},
     *     @OA\Response(response=201, description="Saved"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function storeDirectory(Request $request)
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:public,storage'],
        ]);

        $directory = AvifDirectory::query()->firstOrNew([
            'path' => trim($data['path'], '/'),
            'type' => $data['type'],
        ]);
        $directory->fill([
            'active' => true,
            'is_default' => $directory->is_default ?? false,
        ]);
        $created = !$directory->exists;
        $directory->save();

        return $this->apiSuccess($directory, [], $created ? 201 : 200);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/avif/directories/{directory}/toggle",
     *     tags={"Admin - Utilities"},
     *     summary="Toggle an AVIF directory",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="directory", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Toggled"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function toggleDirectory(AvifDirectory $directory)
    {
        $directory->active = !$directory->active;
        $directory->save();

        return $this->apiSuccess($directory);
    }

    /**
     * @OA\Delete(
     *     path="/admin/shop/avif/directories/{directory}",
     *     tags={"Admin - Utilities"},
     *     summary="Delete an AVIF directory",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="directory", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=422, description="Default directory"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function destroyDirectory(AvifDirectory $directory)
    {
        if ($directory->is_default) {
            return $this->apiError(['directory' => ['حذف پوشه‌های پیش‌فرض مجاز نیست.']], 422);
        }

        $directory->delete();

        return $this->apiSuccess(['deleted' => true]);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/avif/regenerate-directory",
     *     tags={"Admin - Utilities"},
     *     summary="Regenerate AVIF files in one public directory",
     *     security={{"admin":{}}},
     *     @OA\Response(response=200, description="Queued"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function regenerateDirectory(Request $request)
    {
        $data = $request->validate([
            'directory' => ['required', 'string', 'max:255'],
        ]);

        $directory = trim($data['directory'], '/');
        $allowed = AvifDirectory::query()
            ->where('type', 'public')
            ->where('active', true)
            ->pluck('path')
            ->map(fn ($path) => trim((string) $path, '/'))
            ->all();

        if (!in_array($directory, $allowed, true)) {
            return $this->apiError(['directory' => ['پوشه مجاز نیست.']], 422);
        }

        RegenerateAvifForDirectories::dispatch([
            ['type' => 'public', 'dir' => $directory],
        ]);

        return $this->apiSuccess(['directory' => $directory, 'queued' => true]);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/avif/upload-convert",
     *     tags={"Admin - Utilities"},
     *     summary="Upload an image into an allowed directory and convert it to AVIF",
     *     security={{"admin":{}}},
     *     @OA\Response(response=200, description="Converted"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function uploadAndConvert(Request $request)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'directory' => ['nullable', 'string', 'max:255'],
        ]);

        $query = AvifDirectory::query()->where('type', 'public')->where('active', true);
        if (!empty($data['directory'])) {
            $query->where('path', trim($data['directory'], '/'));
        }
        $directory = $query->orderByDesc('is_default')->orderBy('id')->first();
        if (!$directory) {
            return $this->apiError(['directory' => ['پوشه مجاز نیست.']], 422);
        }

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $relative = trim($directory->path, '/').'/'.Str::uuid().'.'.$ext;
        $absolute = public_path($relative);
        File::ensureDirectoryExists(dirname($absolute));
        $file->move(dirname($absolute), basename($absolute));

        $converted = AvifHelper::convertToAvif($absolute, 85);

        return $this->apiSuccess([
            'path' => $relative,
            'converted' => $converted,
        ]);
    }
}
