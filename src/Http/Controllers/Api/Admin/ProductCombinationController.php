<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RMS\Shop\Http\Resources\Admin\ProductCombinationResource;
use RMS\Shop\Models\Product;
use RMS\Shop\Models\ProductCombination;
use RMS\Shop\Services\ProductAttributesService;

class ProductCombinationController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/admin/shop/products/{product_id}/combinations",
     *     tags={"Admin - Products"},
     *     summary="List all combinations for a product",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="product_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Combinations collection",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/AdminProductCombinationResource")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Product not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(int $product_id)
    {
        $product = Product::findOrFail($product_id);

        $combinations = ProductCombination::query()
            ->where('product_id', $product_id)
            ->with(['values.value.attribute', 'assignedImages'])
            ->orderByDesc('id')
            ->get();

        $data = ProductCombinationResource::collection($combinations)->toArray(request());

        return $this->apiSuccess($data);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/products/{product_id}/combinations/{id}",
     *     tags={"Admin - Products"},
     *     summary="Show combination detail",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="product_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Combination detail",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminProductCombinationResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function show(int $product_id, int $id)
    {
        $combination = ProductCombination::query()
            ->where('product_id', $product_id)
            ->with(['values.value.attribute', 'assignedImages'])
            ->findOrFail($id);

        return $this->apiSuccess((new ProductCombinationResource($combination))->toArray(request()));
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/products/{product_id}/combinations",
     *     tags={"Admin - Products"},
     *     summary="Create a new combination",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="product_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="sku", type="string", example="SKU-001-RED"),
     *             @OA\Property(property="price", type="number", format="float", example=100000),
     *             @OA\Property(property="sale_price", type="number", format="float", example=90000),
     *             @OA\Property(property="sale_price_cny", type="number", format="float", example=90),
     *             @OA\Property(property="stock_qty", type="integer", example=100),
     *             @OA\Property(property="active", type="boolean", example=true),
     *             @OA\Property(property="attribute_value_ids", type="array", @OA\Items(type="integer"), example={1, 2})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Combination created",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminProductCombinationResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Product not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function store(Request $request, int $product_id)
    {
        $product = Product::findOrFail($product_id);

        $validated = $request->validate([
            'sku' => ['nullable', 'string', 'max:190'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price_cny' => ['nullable', 'numeric', 'min:0'],
            'stock_qty' => ['integer', 'min:0'],
            'active' => ['boolean'],
            'attribute_value_ids' => ['required', 'array', 'min:1'],
            'attribute_value_ids.*' => ['integer', 'exists:product_attribute_values,id'],
        ]);

        $combination = ProductCombination::create([
            'product_id' => $product_id,
            'sku' => $validated['sku'] ?? null,
            'price' => $validated['price'] ?? null,
            'sale_price' => $validated['sale_price'] ?? null,
            'sale_price_cny' => $validated['sale_price_cny'] ?? null,
            'stock_qty' => $validated['stock_qty'] ?? 0,
            'active' => $validated['active'] ?? true,
        ]);

        // Attach attribute values
        foreach ($validated['attribute_value_ids'] as $valueId) {
            $combination->values()->create([
                'attribute_id' => \RMS\Shop\Models\ProductAttributeValue::find($valueId)->attribute_id ?? null,
                'attribute_value_id' => $valueId,
            ]);
        }

        return $this->apiSuccess((new ProductCombinationResource($combination->load(['values.value.attribute', 'assignedImages'])))->toArray(request()), [], 201);
    }

    /**
     * @OA\Put(
     *     path="/admin/shop/products/{product_id}/combinations/{id}",
     *     tags={"Admin - Products"},
     *     summary="Update combination",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="product_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="sku", type="string", example="SKU-001-RED"),
     *             @OA\Property(property="price", type="number", format="float", example=100000),
     *             @OA\Property(property="sale_price", type="number", format="float", example=90000),
     *             @OA\Property(property="sale_price_cny", type="number", format="float", example=90),
     *             @OA\Property(property="stock_qty", type="integer", example=100),
     *             @OA\Property(property="active", type="boolean", example=true),
     *             @OA\Property(property="attribute_value_ids", type="array", @OA\Items(type="integer"), example={1, 2})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Combination updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminProductCombinationResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function update(Request $request, int $product_id, int $id)
    {
        $combination = ProductCombination::query()
            ->where('product_id', $product_id)
            ->findOrFail($id);

        $validated = $request->validate([
            'sku' => ['nullable', 'string', 'max:190'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price_cny' => ['nullable', 'numeric', 'min:0'],
            'stock_qty' => ['integer', 'min:0'],
            'active' => ['boolean'],
            'attribute_value_ids' => ['sometimes', 'array', 'min:1'],
            'attribute_value_ids.*' => ['integer', 'exists:product_attribute_values,id'],
        ]);

        $combination->update($validated);

        // Update attribute values if provided
        if (isset($validated['attribute_value_ids'])) {
            $combination->values()->delete();
            foreach ($validated['attribute_value_ids'] as $valueId) {
                $combination->values()->create([
                    'attribute_id' => \RMS\Shop\Models\ProductAttributeValue::find($valueId)->attribute_id ?? null,
                    'attribute_value_id' => $valueId,
                ]);
            }
        }

        return $this->apiSuccess((new ProductCombinationResource($combination->fresh(['values.value.attribute', 'assignedImages'])))->toArray(request()));
    }

    /**
     * @OA\Delete(
     *     path="/admin/shop/products/{product_id}/combinations/{id}",
     *     tags={"Admin - Products"},
     *     summary="Delete combination",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="product_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Combination deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object", @OA\Property(property="deleted", type="boolean", example=true))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function destroy(int $product_id, int $id)
    {
        $combination = ProductCombination::query()
            ->where('product_id', $product_id)
            ->findOrFail($id);

        $combination->delete();

        return $this->apiSuccess(['deleted' => true]);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/products/{product_id}/combinations/save",
     *     tags={"Admin - Products"},
     *     summary="Save attributes and combinations (bulk operation)",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="product_id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"attributes_json", "combinations_json"},
     *             @OA\Property(property="attributes_json", type="string", example="[{id:1,name:Color}]"),
     *             @OA\Property(property="combinations_json", type="string", example="[{id:1,sku:SKU-001}]")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Attributes and combinations saved",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="attributes", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="combinations", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Product not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function save(Request $request, int $product_id)
    {
        $product = Product::findOrFail($product_id);

        $request->validate([
            'attributes_json' => ['required', 'string'],
            'combinations_json' => ['required', 'string'],
        ]);

        ProductAttributesService::saveFromJson(
            $product_id,
            $request->input('attributes_json'),
            $request->input('combinations_json')
        );

        // Return fresh data
        $attrRows = \RMS\Shop\Models\ProductAttribute::query()
            ->where('product_id', $product_id)
            ->orderBy('sort')
            ->with('values')
            ->get();

        $attributes = $attrRows->map(function ($a) {
            $values = ($a->values ?? collect())->sortBy('sort')->map(function ($v) {
                return [
                    'id' => $v->id,
                    'value' => $v->value,
                    'image_path' => $v->image_path,
                    'color' => $v->color,
                    'sort' => $v->sort,
                ];
            })->values()->all();

            return [
                'id' => $a->id,
                'name' => $a->name,
                'type' => $a->type ?? 'text',
                'ui' => $a->ui ?? 'pill',
                'sort' => $a->sort,
                'values' => $values,
            ];
        })->values()->all();

        $combRows = ProductCombination::query()
            ->where('product_id', $product_id)
            ->orderByDesc('id')
            ->get();

        $combinations = $combRows->map(function ($c) {
            $valIds = $c->values()->orderBy('id')->pluck('attribute_value_id')->toArray();
            return [
                'id' => $c->id,
                'sku' => $c->sku,
                'price' => $c->price,
                'price_cny' => $c->sale_price_cny,
                'stock' => $c->stock_qty,
                'active' => (bool) $c->active,
                'attribute_value_ids' => $valIds,
            ];
        })->values()->all();

        return $this->apiSuccess([
            'attributes' => $attributes,
            'combinations' => $combinations,
        ]);
    }
}
