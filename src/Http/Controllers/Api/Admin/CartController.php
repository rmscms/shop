<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RMS\Shop\Http\Resources\Admin\CartResource;
use RMS\Shop\Models\Cart;
use Illuminate\Support\Facades\DB;

class CartController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/admin/shop/carts",
     *     tags={"Admin - Carts"},
     *     summary="List all carts",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"open", "converted"})),
     *     @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Carts collection",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AdminCartResource")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginatedMeta")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));

        $query = Cart::query()
            ->select('carts.*')
            ->selectRaw('(SELECT COUNT(*) FROM cart_items WHERE cart_items.cart_id = carts.id) as items_count')
            ->selectRaw('(SELECT COALESCE(SUM(cart_items.qty * cart_items.unit_price), 0) FROM cart_items WHERE cart_items.cart_id = carts.id) as subtotal')
            ->leftJoin('users', 'carts.user_id', '=', 'users.id')
            ->addSelect('users.name as user_name', 'users.mobile as user_mobile');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('carts.id', 'like', "%{$search}%")
                    ->orWhere('users.name', 'like', "%{$search}%")
                    ->orWhere('users.mobile', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('carts.status', $request->input('status'));
        }

        if ($request->filled('user_id')) {
            $query->where('carts.user_id', (int) $request->input('user_id'));
        }

        $carts = $query->orderByDesc('carts.id')->paginate($perPage);

        $data = CartResource::collection($carts->items())->toArray($request);

        return $this->apiSuccess($data, [
            'pagination' => $this->paginationMeta($carts),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/carts/{id}",
     *     tags={"Admin - Carts"},
     *     summary="Show cart detail",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Cart detail",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminCartResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function show(int $id)
    {
        $cart = Cart::with(['user', 'items.product'])->findOrFail($id);

        return $this->apiSuccess((new CartResource($cart))->toArray(request()));
    }

    /**
     * @OA\Delete(
     *     path="/admin/shop/carts/{id}",
     *     tags={"Admin - Carts"},
     *     summary="Delete cart",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Cart deleted",
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
        $cart = Cart::findOrFail($id);
        $cart->delete();

        return $this->apiSuccess(['deleted' => true]);
    }
}
