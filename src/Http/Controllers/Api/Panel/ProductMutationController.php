<?php

namespace RMS\Shop\Http\Controllers\Api\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Annotations as OA;
use RMS\Shop\Http\Requests\Api\Panel\UpdateProductStockRequest;
use RMS\Shop\Http\Resources\Panel\ProductDetailResource;
use RMS\Shop\Models\Product;
use RMS\Shop\Models\ProductCombination;

class ProductMutationController extends BaseController
{
    /**
     * @OA\Post(
     *     path="/products/{product}/stock",
     *     tags={"Catalog"},
     *     security={{"sanctum":{}}},
     *     summary="Update base stock and combination quantities",
     *     @OA\Parameter(name="product", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="stock_qty", type="integer", nullable=true),
     *             @OA\Property(
     *                 property="combinations",
     *                 type="array",
     *                 @OA\Items(
     *                     required={"id","stock_qty"},
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="stock_qty", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Stock updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProductDetailResource")
     *         )
     *     )
     * )
     */
    public function updateStock(UpdateProductStockRequest $request, int $product): JsonResponse
    {
        /** @var Product $model */
        $model = Product::query()->findOrFail($product);

        if (!is_null($request->input('stock_qty'))) {
            $model->stock_qty = (int) $request->input('stock_qty');
            $model->save();
        }

        $combos = collect($request->input('combinations', []))
            ->keyBy(fn ($row) => (int) ($row['id'] ?? 0))
            ->filter(fn ($row, $id) => $id > 0);

        if ($combos->isNotEmpty()) {
            $rows = ProductCombination::query()
                ->where('product_id', $model->id)
                ->whereIn('id', $combos->keys()->all())
                ->get();

            if ($rows->count() !== $combos->count()) {
                throw ValidationException::withMessages([
                    'combinations' => ['invalid_combination_ids'],
                ]);
            }

            foreach ($rows as $comb) {
                $data = $combos[$comb->id];
                $comb->stock_qty = (int) ($data['stock_qty'] ?? 0);
                $comb->save();
            }
        }

        Product::invalidateAvailabilityCache((int) $model->id);

        $model->refresh()
            ->load([
                'category:id,name,slug',
                'assignedImages' => function($q) {
                    $q->orderByPivot('is_main', 'desc')->orderByPivot('sort');
                },
                'combinations.values.value.attribute',
                'combinations.assignedImages',
            ]);

        return $this->apiSuccess(
            (new ProductDetailResource($model))->toArray($request)
        );
    }
}

