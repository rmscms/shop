<?php

namespace RMS\Shop\Http\Controllers\Api\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RMS\Shop\Http\Requests\Api\Panel\StoreCurrencyRateRequest;
use RMS\Shop\Http\Resources\Panel\CurrencyRateResource;
use RMS\Shop\Models\CurrencyRate;

class CurrencyRateController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/currency-rates",
     *     tags={"Currencies"},
     *     summary="List currency rates",
     *     @OA\Parameter(name="base_code", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="quote_code", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="effective_from", in="query", @OA\Schema(type="string", format="date-time")),
     *     @OA\Parameter(name="effective_to", in="query", @OA\Schema(type="string", format="date-time")),
     *     @OA\Response(
     *         response=200,
     *         description="Currency rate list",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/CurrencyRateResource")
     *             ),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginatedMeta")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->integer('per_page', 15)));

        $query = CurrencyRate::query();

        if ($request->filled('base_code')) {
            $query->where('base_code', strtoupper($request->input('base_code')));
        }

        if ($request->filled('quote_code')) {
            $query->where('quote_code', strtoupper($request->input('quote_code')));
        }

        if ($request->filled('effective_from')) {
            $query->where('effective_at', '>=', $request->input('effective_from'));
        }

        if ($request->filled('effective_to')) {
            $query->where('effective_at', '<=', $request->input('effective_to'));
        }

        $query->orderByDesc('effective_at')->orderByDesc('id');

        $rates = $query->paginate($perPage);

        return $this->apiSuccess(
            CurrencyRateResource::collection($rates->items())->toArray($request),
            ['pagination' => $this->paginationMeta($rates)]
        );
    }

    /**
     * @OA\Post(
     *     path="/currency-rates",
     *     tags={"Currencies"},
     *     summary="Create new sell rate",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"base_code","quote_code","sell_rate","effective_at"},
     *             @OA\Property(property="base_code", type="string", example="CNY"),
     *             @OA\Property(property="quote_code", type="string", example="IRT"),
     *             @OA\Property(property="sell_rate", type="number", format="float"),
     *             @OA\Property(property="effective_at", type="string", format="date-time"),
     *             @OA\Property(property="notes", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Rate created",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/CurrencyRateResource")
     *         )
     *     )
     * )
     */
    public function store(StoreCurrencyRateRequest $request): JsonResponse
    {
        $data = $request->validated();
        $rate = CurrencyRate::create([
            'base_code' => strtoupper($data['base_code']),
            'quote_code' => strtoupper($data['quote_code']),
            'sell_rate' => $data['sell_rate'],
            'effective_at' => $data['effective_at'],
            'notes' => $data['notes'] ?? null,
        ]);

        return $this->apiSuccess(
            (new CurrencyRateResource($rate))->toArray($request),
            status: 201
        );
    }
}

