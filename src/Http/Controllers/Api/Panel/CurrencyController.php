<?php

namespace RMS\Shop\Http\Controllers\Api\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RMS\Shop\Http\Resources\Panel\CurrencyResource;
use RMS\Shop\Models\Currency;

class CurrencyController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/currencies",
     *     tags={"Currencies"},
     *     summary="List available currencies",
     *     @OA\Parameter(name="code", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="is_base", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(
     *         response=200,
     *         description="Currency list",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/CurrencyResource")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Currency::query();

        if ($request->filled('code')) {
            $query->where('code', strtoupper($request->input('code')));
        }

        if (!is_null($request->input('is_base'))) {
            $query->where('is_base', filter_var($request->input('is_base'), FILTER_VALIDATE_BOOLEAN));
        }

        $currencies = $query->orderByDesc('is_base')->orderBy('code')->get();

        return $this->apiSuccess(CurrencyResource::collection($currencies)->toArray($request));
    }
}

