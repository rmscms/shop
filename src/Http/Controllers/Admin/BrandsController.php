<?php

namespace RMS\Shop\Http\Controllers\Admin;

use RMS\Shop\Http\Controllers\Admin\ShopAdminController;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Data\Field;
use RMS\Shop\Models\Brand;

class BrandsController extends ShopAdminController implements HasList, HasForm, ShouldFilter
{
    public function table(): string
    {
        return 'shop_brands';
    }

    public function modelName(): string
    {
        return Brand::class;
    }

    public function baseRoute(): string
    {
        return 'shop.brands';
    }

    public function routeParameter(): string
    {
        return 'brand';
    }

    public function query(QueryBuilder $sql): void
    {
        $sql->addSelect('a.*', DB::raw('(select count(*) from products where products.brand_id = a.id) as products_count'))
            ->orderBy('a.sort')
            ->orderBy('a.name');
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('name', trans('shop.brand.name'))->required(),
            Field::string('slug', trans('shop.brand.slug'))->optional(),
            Field::textarea('description', trans('shop.brand.description'))->optional(),
            Field::number('sort', trans('shop.brand.sort'))->withDefaultValue(0)->required(),
            Field::boolean('is_active', trans('shop.brand.is_active'))->withDefaultValue(true),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('shop.common.id'))->sortable()->width('70px'),
            Field::make('name')->withTitle(trans('shop.brand.name'))->searchable()->sortable(),
            Field::make('slug')->withTitle(trans('shop.brand.slug'))->sortable()->width('160px'),
            Field::number('sort')->withTitle(trans('shop.brand.sort'))->width('90px'),
            Field::boolean('is_active')->withTitle(trans('shop.brand.is_active'))->width('110px'),
            Field::make('products_count')
                ->withTitle(trans('shop.brand.products_count'))
                ->skipDatabase()
                ->customMethod('renderProductsCount')
                ->width('130px'),
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', 'unique:shop_brands,slug,'.request()->route('brand')],
            'description' => ['nullable', 'string'],
            'sort' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    public function boolFields(): array
    {
        return ['is_active'];
    }

    public function renderProductsCount($row): string
    {
        $value = null;
        if (is_object($row)) {
            $value = $row->products_count ?? null;
        } elseif (is_array($row)) {
            $value = $row['products_count'] ?? null;
        }

        return (string) ((int) ($value ?? 0));
    }
}

