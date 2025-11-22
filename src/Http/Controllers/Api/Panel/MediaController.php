<?php

namespace RMS\Shop\Http\Controllers\Api\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use OpenApi\Annotations as OA;
use RMS\Shop\Support\Uploads\ChunkUploadManager;

class MediaController extends BaseController
{
    public function __construct(protected ChunkUploadManager $chunks)
    {
    }

    /**
     * @OA\Post(
     *     path="/media/chunks/init",
     *     tags={"Media"},
     *     security={{"sanctum":{}}},
     *     summary="Initialize chunk upload session",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"filename"},
     *             @OA\Property(property="filename", type="string"),
     *             @OA\Property(property="chunk_size", type="integer", nullable=true),
     *             @OA\Property(property="total_size", type="integer", nullable=true),
     *             @OA\Property(property="mime", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Upload session created",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="upload_id", type="string"),
     *                 @OA\Property(property="chunk_size", type="integer")
     *             )
     *         )
     *     )
     * )
     */
    public function init(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filename' => ['required', 'string', 'max:190'],
            'chunk_size' => ['nullable', 'integer', 'min:262144', 'max:10485760'],
            'total_size' => ['nullable', 'integer', 'min:1'],
            'mime' => ['nullable', 'string', 'max:120'],
        ]);

        $uploadId = $this->chunks->init($data);

        return $this->apiSuccess([
            'upload_id' => $uploadId,
            'chunk_size' => (int) ($data['chunk_size'] ?? 2 * 1024 * 1024),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/media/chunks/upload",
     *     tags={"Media"},
     *     security={{"sanctum":{}}},
     *     summary="Upload next chunk",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"upload_id","index","count","chunk","filename"},
     *                 @OA\Property(property="upload_id", type="string"),
     *                 @OA\Property(property="index", type="integer"),
     *                 @OA\Property(property="count", type="integer"),
     *                 @OA\Property(property="filename", type="string"),
     *                 @OA\Property(property="chunk", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Chunk accepted or upload finished"
     *     )
     * )
     */
    public function upload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'upload_id' => ['required', 'string'],
            'index' => ['required', 'integer', 'min:0'],
            'count' => ['required', 'integer', 'min:1'],
            'chunk' => ['required', 'file', 'max:20480'],
            'filename' => ['required', 'string', 'max:190'],
        ]);

        /** @var UploadedFile $file */
        $file = $data['chunk'];
        unset($data['chunk']);

        $result = $this->chunks->append(
            (string) $data['upload_id'],
            $file,
            (int) $data['index'],
            (int) $data['count'],
            (string) $data['filename']
        );

        return $this->apiSuccess($result);
    }
}

