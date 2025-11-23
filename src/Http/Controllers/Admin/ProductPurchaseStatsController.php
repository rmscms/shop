<?php

namespace RMS\Shop\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminController;
use RMS\Shop\Models\ProductPurchaseStats;
use RMS\Shop\Services\ProductPurchaseStatsService;
use Illuminate\Http\Request;
use Illuminate\Database\Query\Builder;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Stats\HasStats;
use RMS\Core\Data\Field;
use RMS\Core\Data\StatCard;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use RMS\Core\View\HelperList\Generator as ListGenerator;
use function RMS\Helper\displayAmount;

class ProductPurchaseStatsController extends AdminController implements HasList, ShouldFilter, HasStats
{
    public function table(): string
    {
        return 'product_purchase_stats';
    }

    public function modelName(): string
    {
        return ProductPurchaseStats::class;
    }

    public function baseRoute(): string
    {
        return 'shop.product-purchase-stats';
    }

    public function routeParameter(): string
    {
        return 'product_purchase_stat';
    }

    public function getListConfig(): array
    {
        return [
            'simple_pagination' => true,
            'show_create' => false,
            'show_edit' => false,
        ];
    }

    public function query(Builder $sql): void
    {
        $sql->leftJoin('users as u', 'a.user_id', '=', 'u.id')->leftJoin('products as p', 'a.product_id', '=', 'p.id')->addSelect([
                'a.*',
                'u.name as user_name',
                'p.name as product_name',
                'p.slug as product_slug',
            ])->orderByDesc('a.purchase_date')->orderByDesc('a.total_amount');
    }

    public function getListFields(): array
    {
        return [
            Field::make('id', 'a.id')->withTitle(trans('shop.common.id'))->sortable()->width('80px'),

            Field::make('user', 'u.name')->withTitle(trans('admin.user') ?: 'کاربر')->customMethod('renderUser')->searchable()->width('20%'),

            Field::make('product', 'p.name')->withTitle(trans('admin.product') ?: 'محصول')->customMethod('renderProduct')->searchable()->width('25%'),

            Field::make('purchase_date', 'a.purchase_date')->withTitle('تاریخ خرید')->type(Field::DATE)->filterType(Field::DATE)->sortable()->width('130px'),

            Field::make('total_quantity', 'a.total_quantity')->withTitle('تعداد خریداری شده')->type(Field::NUMBER)->sortable()->width('140px'),

            Field::make('total_amount', 'a.total_amount')->withTitle('جمع مبلغ')->type(Field::PRICE)->sortable()->width('140px'),

            Field::make('order_count', 'a.order_count')->withTitle('تعداد سفارش‌ها')->type(Field::NUMBER)->sortable()->width('120px'),
        ];
    }
    protected function beforeGenerateList(ListGenerator &$generator): void
    {
        parent::beforeGenerateList($generator);
        $generator->removeActions('edit');
        $generator->removeActions('destroy');
    }

    public function renderUser($row): string
    {
        if (!$row->user_id) {
            return '<span class="text-muted">-</span>';
        }
        $name = e($row->user_name ?: ('#' . $row->user_id));
        $url = route('admin.users.edit', ['user' => (int)$row->user_id]);
        return '<a href="' . $url . '" target="_blank">' . $name . '</a>';
    }

    public function renderProduct($row): string
    {
        if (!$row->product_id) {
            return '<span class="text-muted">-</span>';
        }
        $name = e($row->product_name ?: ('#' . $row->product_id));
        $url = route('admin.shop_products.edit', ['shop_product' => (int)$row->product_id]);
        return '<a href="' . $url . '" target="_blank">' . $name . '</a>';
    }

    public function getStats(?Builder $query = null): array
    {
        $base = $query ?: DB::table('product_purchase_stats');

        $total = (clone $base)->count();
        $uniqueUsers = (clone $base)->distinct('user_id')->count('user_id');
        $uniqueProducts = (clone $base)->distinct('product_id')->count('product_id');
        $totalRevenue = (clone $base)->sum('total_amount');
        $totalQuantity = (clone $base)->sum('total_quantity');

        // Last 30 days stats
        $last30Days = (clone $base)->where('purchase_date', '>=', Carbon::now()->subDays(30)->toDateString())->sum('total_amount');

        // Top product (by quantity)
        $topProduct = (clone $base)->select('product_id', DB::raw('SUM(total_quantity) as total_qty'))->groupBy('product_id')->orderByDesc('total_qty')->first();

        return [
            StatCard::make('کل رکوردها', \RMS\Helper\displayAmount((int)$total))->withIcon('database')->withColor('primary'),
            StatCard::make('کاربران منحصر به فرد', \RMS\Helper\displayAmount((int)$uniqueUsers))->withIcon('users')->withColor('info'),
            StatCard::make('محصولات منحصر به فرد', \RMS\Helper\displayAmount((int)$uniqueProducts))->withIcon('package')->withColor('success'),
            StatCard::make('جمع درآمد', \RMS\Helper\displayAmount((float)$totalRevenue))->withIcon('currency-circle-dollar')->withColor('warning'),
            StatCard::make('جمع تعداد', \RMS\Helper\displayAmount((int)$totalQuantity))->withIcon('shopping-cart')->withColor('teal'),
            StatCard::make('30 روز اخیر', \RMS\Helper\displayAmount((float)$last30Days))->withIcon('chart-line')->withColor('danger'),
        ];
    }

    /**
     * Dashboard with charts
     */
    public function dashboard(Request $request)
    {
        $this->title(trans('admin.shop_purchase_stats') ?: 'آمار خرید محصولات');

        $service = app(ProductPurchaseStatsService::class);

        // Date range
        $days = max(7, min(365, (int)$request->query('days', 30)));
        $since = Carbon::now()->subDays($days);

        // Popular products (top 10) - load with product names
        $popularProductsData = $service->getPopularProducts(10, $since);
        $popularProducts = [];
        foreach ($popularProductsData as $stat) {
            $product = \RMS\Shop\Models\Product::find($stat->product_id);
            $popularProducts[] = (object)[
                'product_id' => $stat->product_id,
                'product_name' => $product ? $product->name : ('#' . $stat->product_id),
                'total_purchases' => (int)$stat->total_purchases,
                'total_revenue' => (float)$stat->total_revenue,
                'unique_buyers' => (int)$stat->unique_buyers,
            ];
        }

        // Daily revenue chart data (last 30 days)
        $dailyRevenue = DB::table('product_purchase_stats')->where('purchase_date', '>=', Carbon::now()->subDays(30)->toDateString())->selectRaw('purchase_date, SUM(total_amount) as revenue, SUM(total_quantity) as quantity')->groupBy('purchase_date')->orderBy('purchase_date')->get();

        // Top users by purchase amount
        $topUsers = DB::table('product_purchase_stats as pps')->leftJoin('users as u', 'pps.user_id', '=', 'u.id')->where('pps.purchase_date', '>=', $since->toDateString())->selectRaw('pps.user_id, u.name as user_name, SUM(pps.total_amount) as total_spent, SUM(pps.total_quantity) as total_items')->groupBy('pps.user_id', 'u.name')->orderByDesc('total_spent')->limit(10)->get();

        $this->view->usePackageNamespace('shop')
            ->setTheme('admin')
            ->setTpl('purchase-stats.dashboard')
            ->withPlugins(['chart-js'])
            ->withCss('vendor/shop/admin/css/purchase-stats.css', true)
            ->withJs('vendor/shop/admin/js/purchase-stats.js', true)
            ->withVariables([
                'popularProducts' => $popularProducts,
                'dailyRevenue' => $dailyRevenue,
                'topUsers' => $topUsers,
                'days' => $days,
                'since' => $since,
            ])->withJsVariables([
                'chartData' => [
                    'labels' => $dailyRevenue->pluck('purchase_date')->all(),
                    'revenue' => $dailyRevenue->pluck('revenue')->all(),
                    'quantity' => $dailyRevenue->pluck('quantity')->all(),
                ],
            ]);

        return $this->view();
    }
}

