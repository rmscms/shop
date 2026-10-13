<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RMS\Shop\Http\Resources\Admin\CurrencyRateResource;
use RMS\Shop\Models\CurrencyRate;

class CurrencyRateController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/admin/shop/currency-rates",
     *     tags={"Admin - Currencies"},
     *     summary="List all currency rates",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Parameter(name="base_code", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="quote_code", in="query", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Currency rates collection",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AdminCurrencyRateResource")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginatedMeta")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));

        $query = CurrencyRate::query();

        if ($request->filled('base_code')) {
            $query->where('base_code', $request->input('base_code'));
        }

        if ($request->filled('quote_code')) {
            $query->where('quote_code', $request->input('quote_code'));
        }

        $rates = $query->orderByDesc('effective_at')->paginate($perPage);

        $data = CurrencyRateResource::collection($rates->items())->toArray($request);

        return $this->apiSuccess($data, [
            'pagination' => $this->paginationMeta($rates),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/currency-rates",
     *     tags={"Admin - Currencies"},
     *     summary="Create a new currency rate",
     *     security={{"admin":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"base_code", "quote_code", "sell_rate"},
     *             @OA\Property(property="base_code", type="string", example="CNY"),
     *             @OA\Property(property="quote_code", type="string", example="IRT"),
     *             @OA\Property(property="sell_rate", type="number", format="float", example=1000.5),
     *             @OA\Property(property="effective_at", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="notes", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Currency rate created",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminCurrencyRateResource")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'base_code' => ['required', 'string', 'max:10'],
            'quote_code' => ['required', 'string', 'max:10'],
            'sell_rate' => ['required', 'numeric', 'min:0'],
            'effective_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $currencyRate = CurrencyRate::create($validated);

        return $this->apiSuccess((new CurrencyRateResource($currencyRate))->toArray(request()), [], 201);
    }

    /**
     * @OA\Put(
     *     path="/admin/shop/currency-rates/{id}",
     *     tags={"Admin - Currencies"},
     *     summary="Update currency rate",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="sell_rate", type="number", format="float", example=1000.5),
     *             @OA\Property(property="effective_at", type="string", format="date-time", nullable=true),
     *             @OA\Property(property="notes", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Currency rate updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminCurrencyRateResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function update(Request $request, int $id)
    {
        $currencyRate = CurrencyRate::findOrFail($id);

        $validated = $request->validate([
            'sell_rate' => ['required', 'numeric', 'min:0'],
            'effective_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $currencyRate->update($validated);

        return $this->apiSuccess((new CurrencyRateResource($currencyRate->fresh()))->toArray(request()));
    }

    /**
     * @OA\Delete(
     *     path="/admin/shop/currency-rates/{id}",
     *     tags={"Admin - Currencies"},
     *     summary="Delete currency rate",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Currency rate deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object", @OA\Property(property="deleted", type="boolean", example=true))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function destroy(int $id)
    {
        $currencyRate = CurrencyRate::findOrFail($id);
        $currencyRate->delete();

        return $this->apiSuccess(['deleted' => true]);
    }
}
