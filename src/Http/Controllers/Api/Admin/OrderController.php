<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RMS\Shop\Http\Resources\Admin\OrderResource;
use RMS\Shop\Http\Resources\Admin\OrderItemResource;
use RMS\Shop\Models\Order;
use RMS\Shop\Services\OrderAdminService;
use Illuminate\Support\Facades\DB;

class OrderController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/admin/shop/orders",
     *     tags={"Admin - Orders"},
     *     summary="List all orders",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Orders collection",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/AdminOrderResource")),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginatedMeta")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));

        $query = Order::query()
            ->select('orders.*')
            ->selectRaw('(SELECT COUNT(*) FROM order_items WHERE order_items.order_id = orders.id) as items_count')
            ->leftJoin('users', 'orders.user_id', '=', 'users.id')
            ->addSelect('users.name as user_name');

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('orders.id', 'like', "%{$search}%")
                    ->orWhere('users.name', 'like', "%{$search}%")
                    ->orWhere('orders.tracking_code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('orders.status', $request->input('status'));
        }

        if ($request->filled('user_id')) {
            $query->where('orders.user_id', (int) $request->input('user_id'));
        }

        $orders = $query->orderByDesc('orders.id')->paginate($perPage);

        $data = OrderResource::collection($orders->items())->toArray($request);

        return $this->apiSuccess($data, [
            'pagination' => $this->paginationMeta($orders),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/orders/{id}",
     *     tags={"Admin - Orders"},
     *     summary="Show order detail",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Order detail",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminOrderResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function show(int $id)
    {
        $order = Order::with(['user', 'items.product'])->findOrFail($id);

        return $this->apiSuccess((new OrderResource($order))->toArray(request()));
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/orders/{id}/update-status",
     *     tags={"Admin - Orders"},
     *     summary="Update order status",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", example="preparing")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order status updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminOrderResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function updateStatus(Request $request, int $id)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:pending,preparing,shipped,delivered,returned,rejected,paid,cancelled'],
        ]);

        OrderAdminService::updateStatus($id, $validated['status'], (int) auth('admin')->id());

        return $this->apiSuccess((new OrderResource(Order::findOrFail($id)))->toArray(request()));
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/orders/{id}/tracking",
     *     tags={"Admin - Orders"},
     *     summary="Update order tracking",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="tracking_code", type="string", nullable=true),
     *             @OA\Property(property="tracking_url", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tracking updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminOrderResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function updateTracking(Request $request, int $id)
    {
        $validated = $request->validate([
            'tracking_code' => ['nullable', 'string', 'max:190'],
            'tracking_url' => ['nullable', 'string', 'max:500'],
        ]);

        OrderAdminService::updateTracking($id, $validated['tracking_code'] ?? null, $validated['tracking_url'] ?? null);

        return $this->apiSuccess((new OrderResource(Order::findOrFail($id)))->toArray(request()));
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/orders/{id}/discount",
     *     tags={"Admin - Orders"},
     *     summary="Apply discount to order",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(property="amount", type="number", format="float", example=10000),
     *             @OA\Property(property="note", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Discount applied",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AdminOrderResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function applyDiscount(Request $request, int $id)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        OrderAdminService::applyDiscount($id, (float) $validated['amount'], $validated['note'] ?? null, (int) auth('admin')->id());

        return $this->apiSuccess((new OrderResource(Order::findOrFail($id)))->toArray(request()));
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/orders/{id}/notes",
     *     tags={"Admin - Orders"},
     *     summary="Add note to order",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"content"},
     *             @OA\Property(property="content", type="string", example="Note content"),
     *             @OA\Property(property="visible_to_user", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Note added",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object", @OA\Property(property="note_id", type="integer"))
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function addNote(Request $request, int $id)
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:3000'],
            'visible_to_user' => ['boolean'],
        ]);

        $noteId = DB::table('order_admin_notes')->insertGetId([
            'order_id' => $id,
            'admin_id' => (int) auth('admin')->id(),
            'content' => $validated['content'],
            'visible_to_user' => $validated['visible_to_user'] ?? false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->apiSuccess(['note_id' => $noteId], [], 201);
    }
}
