<?php

namespace RMS\Shop\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RMS\Core\Contracts\Actions\ChangeBoolField;
use RMS\Shop\Models\Category;
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
            Field::string('meta_title', 'عنوان سئو (حداکثر ۶۰ کاراکتر)')->optional(),
            Field::textarea('meta_description', 'توضیح متا (۱۲۰ تا ۱۵۵ کاراکتر)')->optional(),
            Field::textarea('seo_content', 'متن معرفی دسته (بالای صفحهٔ دسته نمایش داده می‌شود)')->optional(),
            Field::boolean('noindex', 'در گوگل نمایش داده نشود (noindex)')->withDefaultValue(false),
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
            'meta_title' => ['nullable', 'string', 'max:120'],
            'meta_description' => ['nullable', 'string', 'max:320'],
            'seo_content' => ['nullable', 'string'],
            'noindex' => ['boolean'],
            'menu_image_id' => ['nullable', 'integer', 'exists:image_library,id'],
        ];
    }

    public function boolFields(): array
    {
        return ['active', 'noindex'];
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
                            'menuImage' => route('admin.shop.categories.menu-image', ['category' => '__ID__']),
                            'picker' => route('admin.shop.image-library.picker'),
                        ],
                        'csrf' => csrf_token(),
                    ],
                ],
            ]);

        return $this->view();
    }

    public function updateMenuImage(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'menu_image_id' => ['nullable', 'integer', 'exists:image_library,id'],
        ]);

        $category->menu_image_id = $validated['menu_image_id'] ?? null;
        $category->save();
        $category->load('menuImage:id,path,filename');

        return response()->json([
            'success' => true,
            'message' => $category->menu_image_id ? 'تصویر منو ذخیره شد' : 'تصویر منو حذف شد',
            'data' => [
                'menu_image_id' => $category->menu_image_id,
                'menu_image_url' => $category->menuImage?->url,
            ],
        ]);
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
