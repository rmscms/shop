<?php

namespace RMS\Shop\Http\Controllers\Admin;

use Illuminate\Http\Request;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\List\HasList;
use App\Http\Controllers\Admin\AdminController;
use RMS\Core\Data\Field;

class CurrenciesController extends AdminController implements HasList, HasForm, ShouldFilter
{
    public function table(): string
    {
        return 'currencies';
    }

    public function modelName(): string
    {
        return \RMS\Shop\Models\Currency::class;
    }

    public function baseRoute(): string
    {
        return 'shop.currencies';
    }

    public function routeParameter(): string
    {
        return 'currency';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('code', trans('shop.currency.code'))->required(),
            Field::string('name', trans('shop.currency.name'))->required(),
            Field::string('symbol', trans('shop.currency.symbol'))->optional(),
            Field::number('decimals', trans('shop.currency.decimals'))->withDefaultValue(2)->required(),
            Field::boolean('is_base', trans('shop.currency.is_base'))->withDefaultValue(false),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('shop.common.id'))->sortable()->width('80px'),
            Field::make('code')->withTitle(trans('shop.currency.code'))->searchable()->sortable()->width('120px'),
            Field::make('name')->withTitle(trans('shop.currency.name'))->searchable()->sortable(),
            Field::make('symbol')->withTitle(trans('shop.currency.symbol'))->width('100px'),
            Field::make('decimals')->withTitle(trans('shop.currency.decimals'))->width('100px'),
            Field::boolean('is_base')->withTitle(trans('shop.currency.is_base'))->width('100px'),
        ];
    }
    public function boolFields(): array
    {
        return ['is_base'];
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:190'],
            'symbol' => ['nullable', 'string', 'max:8'],
            'decimals' => ['required', 'integer', 'min:0', 'max:8'],
            'is_base' => ['boolean'],
        ];
    }
}
