<?php

namespace RMS\Shop\Http\Controllers\Api\Panel;

use OpenApi\Annotations as OA;
use RMS\Shop\Http\Resources\Panel\BrandResource;
use RMS\Shop\Models\Brand;

class BrandController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/brands",
     *     tags={"Catalog"},
     *     summary="List active brands",
     *     @OA\Response(
     *         response=200,
     *         description="Brands collection",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/BrandResource")
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $brands = Brand::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('name')
            ->get();

        return $this->apiSuccess(
            BrandResource::collection($brands)->toArray(request())
        );
    }
}

