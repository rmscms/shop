<?php

namespace RMS\Shop\Http\Controllers\Admin;

use App\Http\Controllers\Admin\AdminController;
use RMS\Shop\Models\Cart;
use Illuminate\Database\Query\Builder;
use RMS\Core\Contracts\Export\ShouldExport;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Stats\HasStats;
use RMS\Core\Data\Field;
use RMS\Core\Data\StatCard;
use Illuminate\Support\Facades\DB;

class CartsController extends AdminController implements HasList, ShouldFilter, HasStats, ShouldExport
{
    public function table(): string { return 'carts'; }
    public function modelName(): string { return Cart::class; }
    public function baseRoute(): string { return 'shop.carts'; }
    public function routeParameter(): string { return 'cart'; }

    public function getListConfig(): array
    {
        return [
            'simple_pagination' => true,
            'show_create' => false,
        ];
    }

    public function query(Builder $sql): void
    {
        // Alias base table as `a` (handled by core). Join users and aggregate items + totals
        $itemsCount = DB::raw('(select count(*) from cart_items ci where ci.cart_id = a.id) as items_count');
        $subtotal = DB::raw('(select coalesce(sum(ci.qty * ci.unit_price),0) from cart_items ci where ci.cart_id = a.id) as subtotal');

        $sql->leftJoin('users', 'a.user_id', '=', 'users.id')
            ->addSelect('a.*', 'users.name as user_name', 'users.mobile as user_mobile', $itemsCount, $subtotal)
            ->orderByDesc('a.id');
    }

    public function getListFields(): array
    {
        $statusOptions = [
            '' => trans('shop.common.all'),
            'open' => 'open',
            'converted' => 'converted',
        ];

        return [
            Field::make('id', 'a.id')->withTitle(trans('shop.common.id'))->sortable()->width('90px'),

            Field::make('user', 'users.name')
                ->withTitle(trans('admin.user') ?: 'کاربر')
                ->customMethod('renderUser')
                ->searchable()
                ->width('25%'),

            Field::make('items_count', '')
                ->withTitle(trans('admin.items') ?: 'آیتم‌ها')
                ->type(Field::NUMBER)
                ->skipDatabase()
                ->width('120px'),

            Field::make('subtotal', '')
                ->withTitle(trans('admin.amount') ?: 'مبلغ')
                ->type(Field::PRICE)
                ->skipDatabase()
                ->width('140px'),

            Field::select('status', 'a.status')
                ->withTitle(trans('admin.status') ?: 'وضعیت')
                ->setOptions($statusOptions)
                ->filterType(Field::SELECT)
                ->width('140px'),

            Field::make('created_at', 'a.created_at')->withTitle(trans('admin.created_at'))->type(Field::DATE_TIME)->filterType(Field::DATE_TIME)->width('170px'),
        ];
    }

    public function renderUser($row): string
    {
        if (!$row->user_id) { return '<span class="text-muted">-</span>'; }
        $name = e($row->user_name ?: ('#'.$row->user_id));
        $mobile = $row->user_mobile ? '<div class="text-muted small">'.e($row->user_mobile).'</div>' : '';
        $url = route('admin.users.edit', ['user' => (int)$row->user_id]);
        return "<a href=\"{$url}\" target=\"_blank\">{$name}</a>{$mobile}";
    }

    public function getStats(?Builder $query = null): array
    {
        $base = $query ?: DB::table('carts');
        $total = (clone $base)->count();
        $open = (clone $base)->where('status','open')->count();
        $converted = (clone $base)->where('status','converted')->count();

        return [
            StatCard::make(trans('admin.total') ?: 'کل سبدها', (string)$total)->withIcon('shopping-cart')->withColor('primary'),
            StatCard::make('باز', (string)$open)->withIcon('circle')->withColor('warning'),
            StatCard::make('تبدیل‌شده', (string)$converted)->withIcon('check-circle')->withColor('success'),
        ];
    }

    // حذف اکشن ادیت از لیست چون صفحه edit نداریم
    protected function beforeGenerateList(\RMS\Core\View\HelperList\Generator &$generator): void
    {
        parent::beforeGenerateList($generator);
        $generator->removeActions('edit');
        $generator->removeActions('destroy');
    }
}
