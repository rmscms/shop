<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RMS\Shop\Models\ProductPurchaseStats;
use Illuminate\Support\Facades\DB;

class ProductPurchaseStatsController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/admin/shop/product-purchase-stats",
     *     tags={"Admin - Statistics"},
     *     summary="List product purchase statistics",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=20)),
     *     @OA\Parameter(name="user_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="product_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="date_from", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="date_to", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Response(
     *         response=200,
     *         description="Purchase statistics",
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

        $query = DB::table('product_purchase_stats as a')
            ->leftJoin('users as u', 'a.user_id', '=', 'u.id')
            ->leftJoin('products as p', 'a.product_id', '=', 'p.id')
            ->select([
                'a.*',
                'u.name as user_name',
                'p.name as product_name',
                'p.slug as product_slug',
            ]);

        if ($request->filled('user_id')) {
            $query->where('a.user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('product_id')) {
            $query->where('a.product_id', (int) $request->input('product_id'));
        }

        if ($request->filled('date_from')) {
            $query->where('a.purchase_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('a.purchase_date', '<=', $request->input('date_to'));
        }

        $stats = $query->orderByDesc('a.purchase_date')
            ->orderByDesc('a.total_amount')
            ->paginate($perPage);

        $data = collect($stats->items())->map(function ($item) {
            return [
                'id' => (int) $item->id,
                'user_id' => $item->user_id ? (int) $item->user_id : null,
                'user_name' => $item->user_name,
                'product_id' => $item->product_id ? (int) $item->product_id : null,
                'product_name' => $item->product_name,
                'product_slug' => $item->product_slug,
                'purchase_date' => $item->purchase_date,
                'total_quantity' => (int) $item->total_quantity,
                'total_amount' => (float) $item->total_amount,
                'order_count' => (int) $item->order_count,
            ];
        })->toArray();

        return $this->apiSuccess($data, [
            'pagination' => $this->paginationMeta($stats),
        ]);
    }
}
