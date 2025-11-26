<?php

namespace RMS\Shop\Http\Controllers\Api\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;
use RMS\Shop\Http\Requests\Api\Panel\StoreOrderNoteRequest;
use RMS\Shop\Http\Requests\Api\Panel\UpdateOrderStatusRequest;
use RMS\Shop\Http\Resources\Panel\OrderDetailResource;
use RMS\Shop\Http\Resources\Panel\OrderNoteResource;
use RMS\Shop\Http\Resources\Panel\OrderResource;
use RMS\Shop\Models\Order;
use RMS\Shop\Services\OrderAdminService;

class OrderController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/orders",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     summary="List customer orders",
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15)),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated orders",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/OrderResource")
     *             ),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginatedMeta")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $perPage = max(1, min(50, (int) $request->integer('per_page', 15)));

        $query = Order::query()
            ->where('user_id', $userId)
            ->withCount('items');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $orders = $query->orderByDesc('id')->paginate($perPage);
        $data = OrderResource::collection($orders->items())->toArray($request);

        return $this->apiSuccess($data, [
            'pagination' => $this->paginationMeta($orders),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/orders/{order}",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     summary="Get order detail",
     *     @OA\Parameter(name="order", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Order detail",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/OrderDetailResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(Request $request, int $order): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $model = Order::query()
            ->where('user_id', $userId)
            ->with(['items.product', 'items.combination', 'items.image'])
            ->findOrFail($order);

        $model->setRelation('visibleNotes', $this->visibleNotes($model->id));

        return $this->apiSuccess((new OrderDetailResource($model))->toArray($request));
    }

    /**
     * @OA\Get(
     *     path="/orders/{order}/notes",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     summary="List visible order notes",
     *     @OA\Parameter(name="order", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Order notes",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/OrderNoteResource")
     *             )
     *         )
     *     )
     * )
     */
    public function notes(Request $request, int $order): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $model = Order::query()
            ->where('user_id', $userId)
            ->findOrFail($order);

        $notes = $this->visibleNotes($model->id);

        return $this->apiSuccess(OrderNoteResource::collection($notes)->toArray($request));
    }

    /**
     * @OA\Post(
     *     path="/orders/{order}/notes",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     summary="Add customer note to order",
     *     @OA\Parameter(name="order", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"note_text"},
     *             @OA\Property(property="note_text", type="string", maxLength=3000)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Note created",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/OrderNoteResource")
     *             )
     *         )
     *     )
     * )
     */
    public function storeNote(StoreOrderNoteRequest $request, int $order): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $model = Order::query()
            ->where('user_id', $userId)
            ->findOrFail($order);

        DB::table('order_admin_notes')->insert([
            'order_id' => $model->id,
            'admin_id' => null,
            'note_text' => $request->input('note_text'),
            'visible_to_user' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $notes = $this->visibleNotes($model->id);

        return $this->apiSuccess(OrderNoteResource::collection($notes)->toArray($request), status: 201);
    }

    /**
     * @OA\Post(
     *     path="/orders/{order}/status",
     *     tags={"Orders"},
     *     security={{"sanctum":{}}},
     *     summary="Request status change (cancel / received)",
     *     @OA\Parameter(name="order", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", example="cancelled")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/OrderDetailResource")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Transition not allowed")
     * )
     */
    public function updateStatus(UpdateOrderStatusRequest $request, int $order): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $model = Order::query()
            ->where('user_id', $userId)
            ->findOrFail($order);

        $newStatus = $request->input('status');
        $allowedFrom = $request->allowedTransitionsFor($newStatus);
        if (!empty($allowedFrom) && !in_array($model->status, $allowedFrom, true)) {
            return $this->apiError([
                'status' => ['امکان تغییر وضعیت از '.$model->status.' به '.$newStatus.' وجود ندارد'],
            ], 422);
        }

        OrderAdminService::updateStatus($model->id, $newStatus, null);
        $model->refresh()->load(['items.product', 'items.combination', 'items.image']);
        $model->setRelation('visibleNotes', $this->visibleNotes($model->id));

        return $this->apiSuccess((new OrderDetailResource($model))->toArray($request));
    }

    protected function visibleNotes(int $orderId): Collection
    {
        return DB::table('order_admin_notes as n')
            ->leftJoin('admins as a', 'a.id', '=', 'n.admin_id')
            ->where('n.order_id', $orderId)
            ->where('n.visible_to_user', true)
            ->orderByDesc('n.id')
            ->get([
                'n.id',
                'n.note_text',
                'n.visible_to_user',
                'n.created_at',
                'n.admin_id',
                'a.name as admin_name',
            ]);
    }
}

