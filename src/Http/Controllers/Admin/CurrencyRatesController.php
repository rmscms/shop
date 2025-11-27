<?php

namespace RMS\Shop\Http\Controllers\Admin;

use Illuminate\Http\Request;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\List\HasList;
use RMS\Shop\Http\Controllers\Admin\ShopAdminController;
use RMS\Core\Data\Field;
use RMS\Shop\Models\Currency;

class CurrencyRatesController extends ShopAdminController implements HasList, HasForm, ShouldFilter
{
    /**
     * Clean formatted amount fields before validation.
     *
     * @var array
     */
    protected array $amounts = ['sell_rate'];

    public function table(): string { return 'currency_rates'; }
    public function modelName(): string { return \RMS\Shop\Models\CurrencyRate::class; }
    public function baseRoute(): string { return 'shop.currency-rates'; }
    public function routeParameter(): string { return 'currency_rate'; }

    public function getFieldsForm(): array
    {
        $currencyOptions = Currency::query()
            ->orderBy('code')
            ->pluck('code', 'code')
            ->toArray();

        return [
            Field::select('base_code', trans('shop.rate.base_code'))
                ->setOptions($currencyOptions)
                ->required(),
            Field::select('quote_code', trans('shop.rate.quote_code'))
                ->setOptions($currencyOptions)
                ->required(),
            Field::price('sell_rate', trans('shop.rate.sell_rate'))->required(),
            Field::datetime('effective_at', trans('shop.rate.effective_at'))->required(),
            Field::string('notes', trans('shop.rate.notes'))->optional(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('shop.common.id'))->sortable()->width('80px'),
            Field::make('base_code')->withTitle(trans('shop.rate.base_code'))->searchable()->sortable()->width('120px'),
            Field::make('quote_code')->withTitle(trans('shop.rate.quote_code'))->searchable()->sortable()->width('120px'),
            Field::make('sell_rate')->withTitle(trans('shop.rate.sell_rate'))->sortable()->width('140px'),
            Field::date('effective_at')->withTitle(trans('shop.rate.effective_at'))->filterType(Field::DATE)->sortable()->width('160px'),
        ];
    }

    public function rules(): array
    {
        return [
            'base_code' => ['required','string','max:10'],
            'quote_code' => ['required','string','max:10'],
            'sell_rate' => ['required','numeric'],
            'effective_at' => ['required','date'],
            'notes' => ['nullable','string','max:255'],
        ];
    }
}
