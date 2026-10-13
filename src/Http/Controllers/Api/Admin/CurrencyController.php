<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RMS\Shop\Http\Resources\Admin\CurrencyResource;
use RMS\Shop\Models\Currency;

class CurrencyController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/admin/shop/currencies",
     *     tags={"Admin - Currencies"},
     *     summary="List all currencies",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Currencies collection",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AdminCurrencyResource"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        $query = Currency::query();

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $currencies = $query->orderBy('code')->get();

        $data = CurrencyResource::collection($currencies)->toArray($request);

        return $this->apiSuccess($data);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/currencies/{id}",
     *     tags={"Admin - Currencies"},
     *     summary="Show currency detail",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Currency detail",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminCurrencyResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function show(int $id)
    {
        $currency = Currency::findOrFail($id);

        return $this->apiSuccess((new CurrencyResource($currency))->toArray(request()));
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/currencies",
     *     tags={"Admin - Currencies"},
     *     summary="Create a new currency",
     *     security={{"admin":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code", "name"},
     *             @OA\Property(property="code", type="string", example="USD"),
     *             @OA\Property(property="name", type="string", example="US Dollar"),
     *             @OA\Property(property="symbol", type="string", example="$"),
     *             @OA\Property(property="decimals", type="integer", example=2),
     *             @OA\Property(property="is_base", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Currency created",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminCurrencyResource")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:currencies,code'],
            'name' => ['required', 'string', 'max:190'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'decimals' => ['integer', 'min:0', 'max:8'],
            'is_base' => ['boolean'],
        ]);

        $currency = Currency::create($validated);

        return $this->apiSuccess((new CurrencyResource($currency))->toArray(request()), [], 201);
    }

    /**
     * @OA\Put(
     *     path="/admin/shop/currencies/{id}",
     *     tags={"Admin - Currencies"},
     *     summary="Update currency",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="string", example="USD"),
     *             @OA\Property(property="name", type="string", example="US Dollar"),
     *             @OA\Property(property="symbol", type="string", example="$"),
     *             @OA\Property(property="decimals", type="integer", example=2),
     *             @OA\Property(property="is_base", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Currency updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminCurrencyResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function update(Request $request, int $id)
    {
        $currency = Currency::findOrFail($id);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:currencies,code,' . $id],
            'name' => ['required', 'string', 'max:190'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'decimals' => ['integer', 'min:0', 'max:8'],
            'is_base' => ['boolean'],
        ]);

        $currency->update($validated);

        return $this->apiSuccess((new CurrencyResource($currency->fresh()))->toArray(request()));
    }

    /**
     * @OA\Delete(
     *     path="/admin/shop/currencies/{id}",
     *     tags={"Admin - Currencies"},
     *     summary="Delete currency",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Currency deleted",
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
        $currency = Currency::findOrFail($id);
        $currency->delete();

        return $this->apiSuccess(['deleted' => true]);
    }
}
