<?php

namespace RMS\Shop\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminController;
use Illuminate\Http\Request;
use RMS\Shop\Services\SystemRequirementsService;
use RMS\Shop\Models\Product;
use RMS\Shop\Models\Order;
use RMS\Shop\Models\Category;

class ShopDashboardController extends AdminController
{
    public function table(): string { return 'products'; }
    public function modelName(): string { return \RMS\Shop\Models\Product::class; }
    public function baseRoute(): string { return 'shop.dashboard'; }
    public function routeParameter(): string { return 'dashboard'; }

    public function index(Request $request)
    {
        // Get system requirements
        $requirements = SystemRequirementsService::checkAll();
        $overallStatus = SystemRequirementsService::getOverallStatus();

        // Get shop statistics
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

        // Use package namespace 'shop' instead of default 'cms'
        // Note: theme is 'admin', so we just need 'dashboard' not 'admin.dashboard'
        $this->view->usePackageNamespace('shop')
            ->setTpl('dashboard')
            ->withPlugins(['confirm-modal'])
            ->withVariables([
                'title' => 'داشبورد فروشگاه',
                'requirements' => $requirements,
                'overallStatus' => $overallStatus,
                'stats' => $stats,
            ])
            ->withJs('shop/dashboard.js');
        return $this->view();
    }

    /**
     * Get system requirements (AJAX)
     */
    public function systemRequirements(Request $request)
    {
        $requirements = SystemRequirementsService::checkAll();
        $overallStatus = SystemRequirementsService::getOverallStatus();

        return response()->json([
            'success' => true,
            'data' => [
                'requirements' => $requirements,
                'status' => $overallStatus,
            ],
        ]);
    }
}
