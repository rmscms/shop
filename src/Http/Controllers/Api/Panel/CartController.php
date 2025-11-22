<?php

namespace RMS\Shop\Http\Controllers\Api\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use OpenApi\Annotations as OA;
use RMS\Shop\Http\Resources\Panel\CartResource;
use RMS\Shop\Models\Product;
use RMS\Shop\Models\ProductCombination;
use RMS\Shop\Support\PanelApi\CartManager;
use RMS\Shop\Support\PanelApi\CartStorage;

class CartController extends BaseController
{
    public function __construct(
        protected CartStorage $storage,
        protected CartManager $manager
    )
    {
    }

    /**
     * @OA\Get(
     *     path="/cart",
     *     tags={"Cart"},
     *     summary="Get current cart (guest-friendly)",
     *     @OA\Response(
     *         response=200,
     *         description="Current cart snapshot",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/CartResource")
     *         )
     *     )
     * )
     */
    public function show(Request $request): JsonResponse
    {
        [$cartKey, $cookie] = $this->storage->resolveCartKey($request);
        $payload = $this->manager->buildCartPayload($cartKey);
        $response = $this->apiSuccess((new CartResource($payload))->toArray($request));

        if ($cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }

    /**
     * @OA\Post(
     *     path="/cart/items",
     *     tags={"Cart"},
     *     summary="Add item to cart",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"product_id","qty"},
     *             @OA\Property(property="product_id", type="integer"),
     *             @OA\Property(property="combination_id", type="integer", nullable=true),
     *             @OA\Property(property="qty", type="integer", minimum=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Item added",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/CartResource")
     *         )
     *     )
     * )
     */
    public function addItem(Request $request): JsonResponse
    {
        [$cartKey, $cookie] = $this->storage->resolveCartKey($request);

        $data = $this->validateAddRequest($request);

        $product = Product::with(['images', 'category'])->findOrFail($data['product_id']);
        $combination = null;
        if (!empty($data['combination_id'])) {
            $combination = ProductCombination::where('product_id', $product->id)
                ->findOrFail($data['combination_id']);
        }

        $this->storage->addItem($cartKey, [
            'product_id' => (int) $product->id,
            'combination_id' => $combination?->id,
            'qty' => (int) $data['qty'],
        ]);

        $payload = $this->manager->buildCartPayload($cartKey);
        $response = $this->apiSuccess((new CartResource($payload))->toArray($request));
        if ($cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }

    /**
     * @OA\Patch(
     *     path="/cart/items/{lineId}",
     *     tags={"Cart"},
     *     summary="Update cart line quantity",
     *     @OA\Parameter(name="lineId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"qty"},
     *             @OA\Property(property="qty", type="integer", minimum=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Quantity updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/CartResource")
     *         )
     *     )
     * )
     */
    public function updateItem(Request $request, string $lineId): JsonResponse
    {
        [$cartKey, $cookie] = $this->storage->resolveCartKey($request);

        $data = $request->validate([
            'qty' => ['required', 'integer', 'min:1'],
        ]);

        $items = $this->storage->getItems($cartKey);
        if (!collect($items)->contains(fn ($line) => $line['line_id'] === $lineId)) {
            abort(404, 'Line not found');
        }

        $this->storage->updateLine($cartKey, $lineId, (int) $data['qty']);

        $payload = $this->manager->buildCartPayload($cartKey);
        $response = $this->apiSuccess((new CartResource($payload))->toArray($request));
        if ($cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }

    /**
     * @OA\Delete(
     *     path="/cart/items/{lineId}",
     *     tags={"Cart"},
     *     summary="Remove cart line",
     *     @OA\Parameter(name="lineId", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Item removed",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/CartResource")
     *         )
     *     )
     * )
     */
    public function removeItem(Request $request, string $lineId): JsonResponse
    {
        [$cartKey, $cookie] = $this->storage->resolveCartKey($request);

        $items = $this->storage->getItems($cartKey);
        if (!collect($items)->contains(fn ($line) => $line['line_id'] === $lineId)) {
            abort(404, 'Line not found');
        }

        $this->storage->removeLine($cartKey, $lineId);

        $payload = $this->manager->buildCartPayload($cartKey);
        $response = $this->apiSuccess((new CartResource($payload))->toArray($request));
        if ($cookie) {
            $response->headers->setCookie($cookie);
        }

        return $response;
    }

    /**
     * @throws ValidationException
     */
    protected function validateAddRequest(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'product_id' => ['required', 'integer', 'min:1'],
            'combination_id' => ['nullable', 'integer', 'min:1'],
            'qty' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        return $validator->validate();
    }
}

