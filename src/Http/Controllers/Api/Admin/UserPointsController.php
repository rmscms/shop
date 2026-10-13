<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use Illuminate\Support\Facades\DB;

class UserPointsController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/admin/shop/user-points",
     *     tags={"Admin - Statistics"},
     *     summary="List user points logs",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="order_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="User points logs",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        $perPage = min(100, max(1, (int) $request->integer('per_page', 20)));

        $query = DB::table('user_point_logs');

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('order_id')) {
            $query->where('order_id', (int) $request->input('order_id'));
        }

        $logs = $query->orderByDesc('created_at')->paginate($perPage);

        $data = collect($logs->items())->map(function ($item) {
            return [
                'id' => (int) $item->id,
                'user_id' => (int) $item->user_id,
                'order_id' => $item->order_id ? (int) $item->order_id : null,
                'product_id' => $item->product_id ? (int) $item->product_id : null,
                'change' => (int) $item->change,
                'reason' => (string) $item->reason,
                'created_at' => $item->created_at,
            ];
        })->toArray();

        return $this->apiSuccess($data, [
            'pagination' => $this->paginationMeta($logs),
        ]);
    }
}
