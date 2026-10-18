<?php

namespace RMS\Shop\Http\Controllers\Admin;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Upload\HasUploadConfig;
use RMS\Core\Data\Field;
use RMS\Core\Data\UploadConfig;
use RMS\Core\Traits\Controllers\UploadFileControllerHelper;
use RMS\Shop\Models\Brand;

class BrandsController extends ShopAdminController implements HasList, HasForm, ShouldFilter, HasUploadConfig
{
    use UploadFileControllerHelper;

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
            Field::image('logo_path', trans('shop.brand.logo'))
                ->withHint('JPG, PNG, WebP — حداکثر ۲ مگابایت')
                ->optional(),
            Field::textarea('description', trans('shop.brand.description'))->optional(),
            Field::number('sort', trans('shop.brand.sort'))->withDefaultValue(0)->required(),
            Field::boolean('is_active', trans('shop.brand.is_active'))->withDefaultValue(true),
            Field::string('meta_title', 'عنوان سئو (حداکثر ۶۰ کاراکتر)')->optional(),
            Field::textarea('meta_description', 'توضیح متا (۱۲۰ تا ۱۵۵ کاراکتر)')->optional(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('shop.common.id'))->sortable()->width('70px'),
            Field::image('logo_path', trans('shop.brand.logo'))->thumbnail(36, 36)->width('70px'),
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
            'logo_path' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'description' => ['nullable', 'string'],
            'sort' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:120'],
            'meta_description' => ['nullable', 'string', 'max:320'],
        ];
    }

    public function boolFields(): array
    {
        return ['is_active'];
    }

    public function getUploadConfig(): array
    {
        return [
            'logo_path' => UploadConfig::create('logo_path')
                ->path('uploads/brands/logos')
                ->types(['jpg', 'jpeg', 'png', 'webp'])
                ->maxSize(2048)
                ->ajaxUpload(true)
                ->listThumbnailSize(36, 36),
        ];
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
