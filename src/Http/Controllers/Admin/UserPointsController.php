<?php

namespace RMS\Shop\Http\Controllers\Admin;

use Illuminate\Http\Request;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\List\HasList;
use App\Http\Controllers\Admin\AdminController;
use RMS\Core\Data\Field;

class UserPointsController extends AdminController implements HasList, ShouldFilter
{
    public function table(): string { return 'user_point_logs'; }
    public function modelName(): string { return \stdClass::class; }
    public function baseRoute(): string { return 'shop.user-points'; }
    public function routeParameter(): string { return 'user_point'; }

    protected function beforeGenerateList(\RMS\Core\View\HelperList\Generator &$generator): void
    {
        if (method_exists(get_parent_class($this), 'beforeGenerateList')) {
            parent::beforeGenerateList($generator);
        }
        // Disable create button and destructive actions
        $generator->create = false;
        $generator->removeActions('edit');
        $generator->removeActions('destroy');
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('shop.common.id'))->sortable()->width('80px'),
            Field::make('user_id')->withTitle(trans('shop.points.user'))->sortable()->width('100px'),
            Field::make('order_id')->withTitle(trans('shop.points.order'))->width('100px'),
            Field::make('product_id')->withTitle(trans('shop.points.product'))->width('100px'),
            Field::make('change')->withTitle(trans('shop.points.change'))->width('100px'),
            Field::make('reason')->withTitle(trans('shop.points.reason'))->width('140px'),
            Field::date('created_at')->withTitle(trans('shop.common.created_at'))->sortable()->width('160px'),
        ];
    }

    public function rules(): array { return []; }
}
