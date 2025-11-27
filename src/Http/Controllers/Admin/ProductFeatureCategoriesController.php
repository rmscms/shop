<?php

namespace RMS\Shop\Http\Controllers\Admin;

use RMS\Shop\Models\ProductFeatureCategory;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use RMS\Shop\Http\Controllers\Admin\ShopAdminController;
use RMS\Core\Data\Field;
use RMS\Core\Data\StatCard;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Stats\HasStats;
use RMS\Core\Contracts\Actions\ChangeBoolField;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ProductFeatureCategoriesController extends ShopAdminController implements
    HasList,
    HasForm,
    ShouldFilter,
    HasStats,
    ChangeBoolField
{
    public function table(): string
    {
        return 'product_feature_categories';
    }

    public function modelName(): string
    {
        return ProductFeatureCategory::class;
    }

    public function baseRoute(): string
    {
        return 'shop.product-feature-categories';
    }

    public function routeParameter(): string
    {
        return 'product_feature_category';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('name', trans('shop.feature_categories.fields.name'))
                ->required()
                ->withHint(trans('shop.feature_categories.hints.name')),

            Field::string('slug', trans('shop.feature_categories.fields.slug'))
                ->optional()
                ->withHint(trans('shop.feature_categories.hints.slug')),

            Field::select('icon', trans('shop.feature_categories.fields.icon'))
                ->setOptions([
                    'ph-info' => 'اطلاعات عمومی',
                    'ph-wrench' => 'فنی و طراحی',
                    'ph-ruler' => 'ابعاد و اندازه',
                    'ph-plug' => 'اتصالات',
                    'ph-battery-charging' => 'باتری و برق',
                    'ph-shield-check' => 'گارانتی',
                    'ph-tag' => 'برچسب',
                    'ph-cpu' => 'سخت‌افزار',
                    'ph-monitor' => 'نمایشگر',
                    'ph-camera' => 'دوربین',
                    'ph-speaker-high' => 'صدا',
                    'ph-wifi-high' => 'شبکه',
                ])
                ->withDefaultValue('ph-info')
                ->required(),

            Field::textarea('description', trans('shop.feature_categories.fields.description'))
                ->optional()
                ->withHint(trans('shop.feature_categories.hints.description')),

            Field::number('sort', trans('shop.feature_categories.fields.sort'))
                ->withDefaultValue(0)
                ->withHint(trans('shop.feature_categories.hints.sort')),

            Field::boolean('active', trans('shop.feature_categories.fields.active'))
                ->withDefaultValue(true),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('id')
                ->withTitle(trans('shop.common.id'))
                ->sortable()
                ->width('80px'),

            Field::make('icon', 'name')
                ->withTitle(trans('shop.feature_categories.fields.icon'))
                ->customMethod('renderIcon')
                ->searchable(false)
                ->sortable(false)
                ->width('60px'),

            Field::make('name')
                ->withTitle(trans('shop.feature_categories.fields.name'))
                ->searchable()
                ->sortable(),

            Field::make('slug')
                ->withTitle(trans('shop.feature_categories.fields.slug'))
                ->searchable()
                ->sortable()
                ->width('150px'),

            Field::make('features_count', 'id')
                ->withTitle(trans('shop.feature_categories.fields.features_count'))
                ->customMethod('renderFeaturesCount')
                ->searchable(false)
                ->sortable(false)
                ->width('120px'),

            Field::make('sort')
                ->withTitle(trans('shop.feature_categories.fields.sort'))
                ->sortable()
                ->width('80px'),

            Field::boolean('active')
                ->withTitle(trans('shop.feature_categories.fields.active'))
                ->sortable()
                ->width('100px'),

            Field::date('created_at')
                ->withTitle(trans('shop.common.created_at'))
                ->sortable()
                ->width('140px'),
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190', 'unique:product_feature_categories,name,' . $this->getRouteKey()],
            'slug' => ['nullable', 'string', 'max:190', 'unique:product_feature_categories,slug,' . $this->getRouteKey()],
            'icon' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'active' => ['boolean'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => trans('shop.feature_categories.fields.name'),
            'slug' => trans('shop.feature_categories.fields.slug'),
            'icon' => trans('shop.feature_categories.fields.icon'),
            'description' => trans('shop.feature_categories.fields.description'),
            'sort' => trans('shop.feature_categories.fields.sort'),
            'active' => trans('shop.feature_categories.fields.active'),
        ];
    }

    public function getStats(?QueryBuilder $query = null): array
    {
        $total = DB::table('product_feature_categories')->count();
        $active = DB::table('product_feature_categories')->where('active', 1)->count();
        $totalFeatures = DB::table('product_features')->whereNotNull('category_id')->count();

        return [
            StatCard::make(trans('shop.feature_categories.stats.total'), (string)$total)
                ->withIcon('folder')
                ->withColor('primary'),

            StatCard::make(trans('shop.feature_categories.stats.active'), (string)$active)
                ->withIcon('check-circle')
                ->withColor('success'),

            StatCard::make(trans('shop.feature_categories.stats.total_features'), (string)$totalFeatures)
                ->withIcon('list-bullets')
                ->withColor('info'),
        ];
    }

    public function boolFields(): array
    {
        return ['active'];
    }

    public function beforeAdd(Request &$request): void
    {
        // Auto-generate slug if not provided
        if (empty($request->slug) && !empty($request->name)) {
            $request->merge([
                'slug' => Str::slug($request->name)
            ]);
        }
    }

    public function beforeUpdate(Request &$request, string|int $id): void
    {
        // Auto-generate slug if not provided
        if (empty($request->slug) && !empty($request->name)) {
            $request->merge([
                'slug' => Str::slug($request->name)
            ]);
        }
    }

    // Custom render methods
    public function renderIcon($row): string
    {
        $icon = null;
        if (is_object($row)) {
            $icon = $row->icon ?? 'ph-info';
        }
        if (is_array($row)) {
            $icon = $row['icon'] ?? 'ph-info';
        }

        return '<i class="' . e($icon) . ' text-primary fs-5"></i>';
    }

    public function renderFeaturesCount($row): string
    {
        $categoryId = null;
        if (is_object($row)) {
            $categoryId = $row->id ?? null;
        }
        if (is_array($row)) {
            $categoryId = $row['id'] ?? null;
        }

        if (!$categoryId) return '0';

        $count = DB::table('product_features')->where('category_id', (int)$categoryId)->count();

        return '<span class="badge bg-secondary">' . $count . ' ویژگی</span>';
    }

    private function getRouteKey(): ?string
    {
        return request()->route()?->parameter($this->routeParameter());
    }
}
