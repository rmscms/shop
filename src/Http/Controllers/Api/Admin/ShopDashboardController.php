<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use OpenApi\Annotations as OA;
use RMS\Shop\Services\SystemRequirementsService;
use RMS\Shop\Models\Product;
use RMS\Shop\Models\Order;
use RMS\Shop\Models\Category;

class ShopDashboardController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/admin/shop/dashboard",
     *     tags={"Admin - Dashboard"},
     *     summary="Get shop dashboard statistics",
     *     security={{"admin":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard statistics",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="stats", type="object"),
     *                 @OA\Property(property="requirements", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function index()
    {
        $requirements = SystemRequirementsService::checkAll();
        $overallStatus = SystemRequirementsService::getOverallStatus();

        $stats = [
            'products' => [
                'total' => Product::count(),
                'active' => Product::where('active', true)->count(),
            ],
            'categories' => Category::count(),
            'orders' => [
                'total' => Order::count(),
                'today' => Order::whereDate('created_at', today())->count(),
            ],
        ];

        return $this->apiSuccess([
            'stats' => $stats,
            'requirements' => $requirements,
            'overall_status' => $overallStatus,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/dashboard/system-requirements",
     *     tags={"Admin - Dashboard"},
     *     summary="Get system requirements",
     *     security={{"admin":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="System requirements",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="requirements", type="object"),
     *                 @OA\Property(property="status", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function systemRequirements()
    {
        $requirements = SystemRequirementsService::checkAll();
        $overallStatus = SystemRequirementsService::getOverallStatus();

        return $this->apiSuccess([
            'requirements' => $requirements,
            'status' => $overallStatus,
        ]);
    }
}
