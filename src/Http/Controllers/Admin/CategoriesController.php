<?php

namespace RMS\Shop\Http\Controllers\Admin;

use Illuminate\Http\Request;
use RMS\Core\Contracts\Actions\ChangeBoolField;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Data\Field;
use RMS\Shop\Services\CategoryTreeService;

class CategoriesController extends ShopAdminController implements HasList, HasForm, ShouldFilter, ChangeBoolField
{
    public function table(): string
    {
        return 'categories';
    }

    public function modelName(): string
    {
        return \RMS\Shop\Models\Category::class;
    }

    public function baseRoute(): string
    {
        return 'shop.categories';
    }

    public function routeParameter(): string
    {
        return 'category';
    }

    public function getFieldsForm(): array
    {
        $parentOptions = ['' => trans('shop.common.none')] + $this->getCategoryOptions();

        return [
            Field::string('name', trans('shop.category.name'))->required(),
            Field::string('slug', trans('shop.category.slug'))->required(),
            Field::select('parent_id', trans('shop.category.parent'))
                ->setOptions($parentOptions)
                ->advanced()
                ->optional(),
            Field::number('sort', trans('shop.common.sort'))->withDefaultValue(0),
            Field::boolean('active', trans('shop.common.active'))->withDefaultValue(true),
        ];
    }

    public function getListFields(): array
    {
        $filterOptions = ['' => trans('shop.common.all')] + $this->getCategoryOptions();

        return [
            Field::make('id')->withTitle(trans('shop.common.id'))->sortable()->width('80px'),
            Field::make('name')->withTitle(trans('shop.category.name'))->searchable()->sortable(),
            Field::make('slug')->withTitle(trans('shop.category.slug'))->searchable()->sortable(),
            Field::select('parent_id')
                ->withTitle(trans('shop.category.parent'))
                ->setOptions($filterOptions)
                ->filterType(Field::SELECT)
                ->width('160px'),
            Field::boolean('active')->withTitle(trans('shop.common.active'))->sortable()->width('100px'),
            Field::make('sort')->withTitle(trans('shop.common.sort'))->sortable()->width('100px'),
        ];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['required', 'string', 'max:190'],
            'parent_id' => ['nullable', 'integer'],
            'active' => ['boolean'],
            'sort' => ['nullable', 'integer'],
        ];
    }

    public function boolFields(): array
    {
        return ['active'];
    }

    public function tree(Request $request)
    {
        $service = app(CategoryTreeService::class);
        $treeData = $service->getTreeForPlugin(null, false);
        $defaultId = $service->defaultCategoryId();

        $this->title('درخت دسته‌بندی فروشگاه');

        $this->view->usePackageNamespace('shop')
            ->setTheme('admin')
            ->setTpl('categories.tree')
            ->withPlugins(['fancytree'])
            ->withJs('vendor/shop/admin/js/categories/tree.js', true)
            ->withVariables([
                'treeData' => $treeData,
                'defaultCategoryId' => $defaultId,
                'fallbackLabel' => (string) config('shop.categories.fallback_label', 'بدون دسته'),
            ])
            ->withJsVariables([
                'RMS' => [
                    'ADMIN_SHOP_CATEGORIES' => [
                        'treeData' => $treeData,
                        'defaultCategoryId' => $defaultId,
                        'treeEndpoint' => route('admin.shop.categories.tree.data'),
                        'routes' => [
                            'index' => route('admin.shop.categories.index'),
                            'create' => route('admin.shop.categories.create'),
                            'edit' => route('admin.shop.categories.edit', ['category' => '__ID__']),
                        ],
                    ],
                ],
            ]);

        return $this->view();
    }

    public function treeData(Request $request)
    {
        $service = app(CategoryTreeService::class);
        $includeInactive = $request->boolean('include_inactive', false);
        $selected = $request->input('selected');
        $selectedId = is_numeric($selected) ? (int) $selected : null;

        $tree = $service->getTreeForPlugin($selectedId, !$includeInactive);

        return response()->json([
            'success' => true,
            'data' => $tree,
            'meta' => [
                'default_category_id' => $service->defaultCategoryId(),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function getCategoryOptions(): array
    {
        return app(CategoryTreeService::class)->flatOptions(false);
    }
}
