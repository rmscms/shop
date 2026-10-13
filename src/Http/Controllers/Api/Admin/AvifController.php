<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
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
}
