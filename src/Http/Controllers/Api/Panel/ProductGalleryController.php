<?php

namespace RMS\Shop\Http\Controllers\Api\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;
use RMS\Shop\Http\Controllers\Admin\Traits\ProductImagesTrait;
use RMS\Shop\Jobs\ConvertImageToAvif;
use RMS\Shop\Models\ProductCombination;
use RMS\Shop\Models\ProductCombinationImage;
use RMS\Shop\Models\ProductImage;

class ProductGalleryController extends BaseController
{
    use ProductImagesTrait;

    /**
     * @OA\Post(
     *     path="/products/{product}/gallery",
     *     tags={"Catalog"},
     *     security={{"sanctum":{}}},
     *     summary="Upload gallery image or attach from chunk upload",
     *     @OA\Parameter(name="product", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="image", type="string", format="binary"),
     *                 @OA\Property(property="combination_id", type="integer", nullable=true),
     *                 @OA\Property(property="source_path", type="string", nullable=true, description="Path returned from chunk upload")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Image uploaded")
     * )
     */
    public function upload(Request $request, int $product): JsonResponse
    {
        if ($request->filled('source_path')) {
            $payload = $this->attachFromSourcePath(
                $product,
                (string) $request->input('source_path'),
                $request->integer('combination_id') ?: null
            );

            return $this->apiSuccess($payload);
        }

        $legacy = $this->uploadImage($request, $product, $request->integer('combination_id') ?: null);
        return $this->wrapLegacy($legacy);
    }

    /**
     * @OA\Delete(
     *     path="/products/{product}/gallery/{image}",
     *     tags={"Catalog"},
     *     security={{"sanctum":{}}},
     *     summary="Delete gallery image",
     *     @OA\Parameter(name="product", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="image", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Image deleted")
     * )
     */
    public function destroy(Request $request, int $product, int $image): JsonResponse
    {
        $legacy = $this->deleteImage($request, $product, $image);
        return $this->wrapLegacy($legacy);
    }

    /**
     * @OA\Post(
     *     path="/products/{product}/gallery/set-main",
     *     tags={"Catalog"},
     *     security={{"sanctum":{}}},
     *     summary="Mark image as main",
     *     @OA\Parameter(name="product", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"scope","image_id"},
     *             @OA\Property(property="scope", type="string", enum={"product","combination"}),
     *             @OA\Property(property="image_id", type="integer"),
     *             @OA\Property(property="combination_id", type="integer", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Main image updated")
     * )
     */
    public function setMain(Request $request, int $product): JsonResponse
    {
        $legacy = $this->setMainImage($request, $product);
        return $this->wrapLegacy($legacy);
    }

    /**
     * @OA\Post(
     *     path="/products/{product}/gallery/sort",
     *     tags={"Catalog"},
     *     security={{"sanctum":{}}},
     *     summary="Reorder gallery images",
     *     @OA\Parameter(name="product", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"scope","items"},
     *             @OA\Property(property="scope", type="string", enum={"product","combination"}),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 @OA\Items(
     *                     required={"id","sort"},
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="sort", type="integer")
     *                 )
     *             ),
     *             @OA\Property(property="combination_id", type="integer", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Sorting updated")
     * )
     */
    public function sort(Request $request, int $product): JsonResponse
    {
        $legacy = $this->sortImages($request, $product);
        return $this->wrapLegacy($legacy);
    }

    /**
     * @OA\Post(
     *     path="/products/{product}/gallery/assign",
     *     tags={"Catalog"},
     *     security={{"sanctum":{}}},
     *     summary="Assign product image to combination",
     *     @OA\Parameter(name="product", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"combination_id"},
     *             @OA\Property(property="combination_id", type="integer"),
     *             @OA\Property(property="image_id", type="integer", nullable=true),
     *             @OA\Property(property="file_path", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Image assigned to combination")
     * )
     */
    public function assign(Request $request, int $product): JsonResponse
    {
        $legacy = $this->assignImage($request, $product);
        return $this->wrapLegacy($legacy);
    }

    /**
     * @OA\Post(
     *     path="/products/{product}/gallery/detach",
     *     tags={"Catalog"},
     *     security={{"sanctum":{}}},
     *     summary="Detach image from combination",
     *     @OA\Parameter(name="product", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"combination_id"},
     *             @OA\Property(property="combination_id", type="integer"),
     *             @OA\Property(property="combination_image_id", type="integer", nullable=true),
     *             @OA\Property(property="image_id", type="integer", nullable=true),
     *             @OA\Property(property="file_path", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Image detached from combination")
     * )
     */
    public function detach(Request $request, int $product): JsonResponse
    {
        $legacy = $this->detachImage($request, $product);
        return $this->wrapLegacy($legacy);
    }

    protected function wrapLegacy(Response $response): JsonResponse
    {
        if ($response instanceof JsonResponse && $response->getStatusCode() >= 400) {
            $payload = $response->getData(true);
            return $this->apiError(
                ['message' => $payload['message'] ?? $payload['error'] ?? 'error'],
                $response->getStatusCode()
            );
        }

        if ($response instanceof JsonResponse) {
            return $this->apiSuccess($response->getData(true));
        }

        return $this->apiSuccess([]);
    }

    protected function attachFromSourcePath(int $productId, string $sourcePath, ?int $combinationId = null): array
    {
        $disk = Storage::disk('public');
        $relative = ltrim($sourcePath, '/');
        if (!str_starts_with($relative, 'uploads/tmp/')) {
            abort(422, 'invalid_source_path');
        }
        if (!$disk->exists($relative)) {
            abort(404, 'source_not_found');
        }

        if ($combinationId) {
            $exists = ProductCombination::query()->where(['id' => (int) $combinationId, 'product_id' => $productId])->exists();
            if (!$exists) {
                abort(404, 'combination_not_found');
            }
        }

        $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION) ?: 'jpg');
        $destDir = 'uploads/products/'.$productId.($combinationId ? '/combinations/'.$combinationId : '/product').'/orig';
        $destName = Str::uuid().'.'.$ext;
        $disk->makeDirectory($destDir);
        $destPath = $destDir.'/'.$destName;
        $disk->move($relative, $destPath);
        ConvertImageToAvif::dispatch($destPath);

        if ($combinationId) {
            $image = ProductCombinationImage::query()->create([
                'combination_id' => (int) $combinationId,
                'path' => $destPath,
                'is_main' => false,
                'sort' => 0,
            ]);
        } else {
            $image = ProductImage::query()->create([
                'product_id' => (int) $productId,
                'path' => $destPath,
                'is_main' => false,
                'sort' => 0,
            ]);
        }

        return [
            'ok' => true,
            'id' => (int) $image->id,
            'path' => $destPath,
            'url' => $disk->url($destPath),
        ];
    }
}

