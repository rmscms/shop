<?php

namespace RMS\Shop\Http\Controllers\Admin;

use Illuminate\Http\Request;
use RMS\Core\Contracts\Actions\ChangeBoolField;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Data\Field;

class ImageSizesController extends ShopAdminController implements HasList, HasForm, ShouldFilter, ChangeBoolField
{
    public function table(): string
    {
        return 'image_sizes';
    }

    public function modelName(): string
    {
        return \RMS\Shop\Models\ImageSize::class;
    }

    public function baseRoute(): string
    {
        return 'shop.image-sizes';
    }

    public function routeParameter(): string
    {
        return 'image-size';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('name', trans('shop.image_size.name'))->required(),
            Field::string('slug', trans('shop.image_size.slug'))->required(),
            Field::number('width', trans('shop.image_size.width'))->optional(),
            Field::number('height', trans('shop.image_size.height'))->optional(),
            Field::number('quality', trans('shop.image_size.quality'))->withDefaultValue(85),
            Field::number('sort', trans('shop.common.sort'))->withDefaultValue(0),
            Field::boolean('active', trans('shop.common.active'))->withDefaultValue(true),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')->withTitle(trans('shop.common.id'))->sortable()->width('80px'),
            Field::make('name')->withTitle(trans('shop.image_size.name'))->searchable()->sortable(),
            Field::make('slug')->withTitle(trans('shop.image_size.slug'))->searchable()->sortable(),
            Field::make('width')->withTitle(trans('shop.image_size.width'))->sortable()->width('100px'),
            Field::make('height')->withTitle(trans('shop.image_size.height'))->sortable()->width('100px'),
            Field::make('quality')->withTitle(trans('shop.image_size.quality'))->sortable()->width('100px'),
            Field::boolean('active')->withTitle(trans('shop.common.active'))->sortable()->width('100px'),
            Field::make('sort')->withTitle(trans('shop.common.sort'))->sortable()->width('100px'),
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['required', 'string', 'max:190'],
            'width' => ['nullable', 'integer', 'min:1'],
            'height' => ['nullable', 'integer', 'min:1'],
            'quality' => ['required', 'integer', 'min:1', 'max:100'],
            'active' => ['boolean'],
            'sort' => ['nullable', 'integer'],
        ];
    }

    public function boolFields(): array
    {
        return ['active'];
    }
}
