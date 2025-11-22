<?php
// git-trigger
namespace RMS\Shop\Http\Controllers\Admin;

use RMS\Shop\Models\Product;
use RMS\Shop\Models\Category;
use RMS\Shop\Models\CurrencyRate;
use RMS\Shop\Models\ProductAttribute;
use RMS\Shop\Models\ProductAttributeValue;
use RMS\Shop\Models\ProductCombination;
use RMS\Shop\Models\ProductCombinationValue;
use RMS\Shop\Models\ProductImage;
use RMS\Shop\Models\ProductVideo;
use RMS\Shop\Models\ProductFeatureCategory;
use RMS\Shop\Models\ProductFeature;
use RMS\Shop\Http\Requests\ProductStoreRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RMS\Core\Contracts\Actions\ChangeBoolField;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Data\Field;
use App\Http\Controllers\Admin\AdminController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RMS\Shop\Jobs\ConvertImageToAvif;
use Throwable;
use RMS\Shop\Http\Controllers\Admin\Traits\ProductImagesTrait;
use RMS\Shop\Http\Controllers\Admin\Traits\ProductFeaturesTrait;
use RMS\Shop\Http\Controllers\Admin\Traits\ProductVideoTrait;
use RMS\Shop\Services\CategoryTreeService;
use RMS\Shop\Services\VideoLibraryService;

class ProductsController extends AdminController implements HasList, HasForm, ShouldFilter, ChangeBoolField
{
    use ProductImagesTrait, ProductFeaturesTrait, ProductVideoTrait;

    public function table(): string { return 'products'; }
    public function modelName(): string { return Product::class; }
    public function baseRoute(): string { return 'shop.products'; }
    public function routeParameter(): string { return 'product'; }

    // Join categories to expose category_name for list rendering and sorting
    public function query($sql): void
    {
        // Core aliases base table as `a`, so join on `a.category_id`
        $sql->leftJoin('categories', 'categories.id', '=', 'a.category_id')
            // Ensure row identifier resolves to base table id (avoid collisions with joined tables)
            ->addSelect('a.id as id', 'categories.name as category_name', 'a.discount_type', 'a.discount_value')
            // Exclude soft-deleted products from admin list/search
            ->whereNull('a.deleted_at');
    }

    // Custom Create/Edit pages (Limitless custom page pattern)
    public function create(Request $request)
    {
        $categories = Category::query()->orderBy('name')->pluck('name','id')->toArray();
        $rate = CurrencyRate::query()->where('base_code','CNY')->where('quote_code','IRT')->orderByDesc('effective_at')->value('sell_rate');

        $categoryService = app(CategoryTreeService::class);
        $categoryTree = $categoryService->getTreeForPlugin(null, false);
        $defaultCategoryId = $categoryService->defaultCategoryId();

        $this->view->usePackageNamespace('shop')
            ->setTheme('admin')
            ->setTpl('products.edit')
            ->withPlugins([ 'confirm-modal', 'image-uploader', 'ckeditor', 'fancytree'])
            ->withCss('shop/products/edit.css')
            ->withJs('shop/products/edit.js')
            ->withJs('shop/products/attributes.js')
            ->withJs('shop/products/video.js')
            ->withJs('shop/products/features.js')
            ->withJs('shop/products/category-tree.js')
            ->withVariables([
                'product' => null,
                'categories' => $categories,
                'attributes' => [],
                'combinations' => [],
                'categoryTree' => $categoryTree,
                'defaultCategoryId' => $defaultCategoryId,
            ])
            ->withJsVariables([
                'apiEndpoints' => [
                    'store' => route('admin.shop.products.store'),
                ],
                'images' => null,
                'currency' => [ 'base' => 'IRT', 'cny' => 'CNY' ],
                'currencyRate' => $rate,
                'data' => [
                    'attributes' => [],
                    'combinations' => []
                ],
                'categories' => [
                    'tree' => $categoryTree,
                    'defaultId' => $defaultCategoryId,
                    'selectedId' => $request->old('category_id'),
                    'treeEndpoint' => route('admin.shop.categories.tree.data'),
                ],
                'flash' => [
                    'success' => session('success'),
                    'error' => session('error'),
                    'warning' => session('warning'),
                    'info' => session('info'),
                ],
            ]);

        return $this->view();
    }

    public function edit(Request $request, $id)
    {
        /** @var Product|null $product */
        $product = Product::query()
            ->with(['images','videos','combinations.values.value.attribute'])
            ->find((int)$id);
        abort_if(!$product, 404);
        $categories = Category::query()->orderBy('name')->pluck('name','id')->toArray();
        $selectedCategoryId = $request->old('category_id', $product->category_id);
        $categoryService = app(CategoryTreeService::class);
        $categoryTree = $categoryService->getTreeForPlugin($selectedCategoryId ? (int) $selectedCategoryId : null, false);
        $defaultCategoryId = $categoryService->defaultCategoryId();
        $attrRows = ProductAttribute::query()
            ->where('product_id', (int)$id)
            ->orderBy('sort')
            ->with('values')
            ->get();
        $attributes = $attrRows->map(function($a){
            $values = ($a->values ?? collect())->sortBy('sort')->map(function($v){
                return [
                    'id' => $v->id,
                    'value' => $v->value,
                    'image_path' => $v->image_path,
                    'color' => $v->color,
                    'sort' => $v->sort,
                    'definition_value_id' => $v->definition_value_id,
                ];
            })->values()->all();
            return [
                'id' => $a->id,
                'name' => $a->name,
                'type' => $a->type ?? 'text',
                'ui' => $a->ui ?? 'pill',
                'sort' => $a->sort,
                'attribute_definition_id' => $a->attribute_definition_id,
                'values' => $values,
            ];
        })->values()->all();

        $combRows = ProductCombination::query()
            ->where('product_id', (int)$id)
            ->with('values')
            ->orderBy('id','desc')
            ->get();
        $combinations = $combRows->map(function($c){
            $valIds = $c->relationLoaded('values') ? $c->values->sortBy('id')->pluck('attribute_value_id')->toArray()
                                                   : $c->values()->orderBy('id')->pluck('attribute_value_id')->toArray();
            return [
                'id' => $c->id,
                'sku' => $c->sku,
                'price' => $c->price,
                'price_cny' => $c->sale_price_cny,
                'stock' => $c->stock_qty,
                'active' => (bool)$c->active,
                'attribute_value_ids' => $valIds,
            ];
        })->values()->all();

        // image counts per combination (via Eloquent helper)
        $imageCounts = $product->imageCountsByCombination();

        // assigned images mapping: path => [combination_id,...] (via Eloquent helper)
        $assignedMap = $product->assignedImagesMap();

        // labels for combinations (SKU + attributes) via Eloquent helper
        $comboLabels = $product->comboLabels();

        $rate = CurrencyRate::query()->where('base_code','CNY')->where('quote_code','IRT')->orderByDesc('effective_at')->value('sell_rate');
        $productImages = ProductImage::query()->where('product_id', (int)$id)->orderBy('sort')->get()->map(function($r){
            return [
                'url' => Storage::disk('public')->url($r->path),
                'path' => $r->path,
                'filename' => basename($r->path),
                'size' => null,
            ];
        })->values()->all();

        $productVideos = ProductVideo::query()->where('product_id', (int)$id)->orderBy('sort')->orderBy('id')->get();

        $featureCategories = ProductFeatureCategory::query()
            ->where('active', true)
            ->orderBy('sort')
            ->get(['id','name','icon'])
            ->map(function($r){ return ['id'=>(int)$r->id,'name'=>(string)$r->name,'icon'=>(string)($r->icon??'ph-info')]; })
            ->values()->all();


        $this->view->usePackageNamespace('shop')
            ->setTheme('admin')
            ->setTpl('products.edit')
            ->withPlugins([ 'confirm-modal', 'image-uploader', 'ckeditor', 'fancytree'])
            ->withCss('shop/products/edit.css')
            ->withJs('shop/products/edit.js')
            ->withJs('shop/products/attributes.js')
            ->withJs('shop/products/videos.js')
            ->withJs('shop/products/features-new.js')
            ->withJs('shop/products/category-tree.js')
            ->withJs('shop/products/image-management.js')

            ->withVariables([
                'product' => $product,
                'categories' => $categories,
                'attributes' => $attributes,
                'combinations' => $combinations,
                'productImages' => $productImages,
                'productVideos' => $productVideos,
                'features' => $this->getProductFeaturesGrouped((int)$id),
                'featureCategories' => $featureCategories,
                'categoryTree' => $categoryTree,
                'defaultCategoryId' => $defaultCategoryId,
            ])
            ->withJsVariables([
                'productId' => (int)$id,
                'apiEndpoints' => [
                    'update' => route('admin.shop.products.update', ['product' => (int)$id]),
                    'saveCombinations' => route('admin.shop.products.save-combinations', ['product' => (int)$id]),
                    'saveBasic' => route('admin.shop.products.basic.update', ['product' => (int)$id]),
                    'savePricing' => route('admin.shop.products.pricing.update', ['product' => (int)$id]),
                ],
                'images' => [
                    'list' => route('admin.shop.products.images.list', ['product' => (int)$id]),
                    'upload' => route('admin.shop.products.upload-image', ['product' => (int)$id]),
                    'delete' => route('admin.shop.products.delete-image', ['product' => (int)$id, 'image' => '__ID__']),
                    'detach' => route('admin.shop.products.images.detach', ['product' => (int)$id]),
                    'counts' => $imageCounts,
                    'assigned' => $assignedMap,
                    'combo_labels' => $comboLabels,
                ],
                'currencyRate' => $rate,
                'data' => [
                    'attributes' => $attributes,
                    'combinations' => $combinations,
                    'product' => $product,
                    'features' => $this->getProductFeaturesGrouped((int)$id),
                    'featureCategories' => $featureCategories,
                ],
                'categories' => [
                    'tree' => $categoryTree,
                    'defaultId' => $defaultCategoryId,
                    'selectedId' => $selectedCategoryId,
                    'treeEndpoint' => route('admin.shop.categories.tree.data'),
                ],
                'endpoints' => [
                    'saveFeatures' => route('admin.shop.products.save-features', ['product'=>(int)$id]),
                    'createCategory' => route('admin.shop.products.features.create-category'),
                    'uploadGallery' => route('admin.shop.products.ajax_upload_gallery', ['product'=>(int)$id, 'gallery'=>'gallery']),
                    'deleteGallery' => route('admin.shop.products.ajax_delete_gallery', ['product'=>(int)$id, 'gallery'=>'gallery']),
                    'regenerateAvif' => route('admin.shop.products.regenerate-avif', ['product'=>(int)$id]),
                    'searchCategories' => route('admin.shop.products.features.categories.search'),
                    'searchFeatures' => route('admin.shop.products.features.search'),
                    'searchValues' => route('admin.shop.products.features.values.search'),
                    'createFeature' => route('admin.shop.products.features.definitions.store'),
                    'createValue' => route('admin.shop.products.features.values.store'),
                    'searchAttributeDefinitions' => route('admin.shop.products.attributes.definitions.search'),
                    'searchAttributeValues' => route('admin.shop.products.attributes.values.search'),
                    'createAttributeDefinition' => route('admin.shop.products.attributes.definitions.store'),
                    'createAttributeValue' => route('admin.shop.products.attributes.values.store'),
                ],
                'flash' => [
                    'success' => session('success'),
                    'error' => session('error'),
                    'warning' => session('warning'),
                    'info' => session('info'),
                ],
                'videos' => [
                    'list' => route('admin.shop.products.videos.list', ['product' => (int)$id]),
                    'library' => route('admin.shop.products.videos.library', ['product' => (int)$id]),
                    'assign' => route('admin.shop.products.videos.assign', ['product' => (int)$id]),
                    'detach' => route('admin.shop.products.videos.detach', ['product' => (int)$id]),
                    'set-main' => route('admin.shop.products.videos.set-main', ['product' => (int)$id]),
                    'sort' => route('admin.shop.products.videos.sort', ['product' => (int)$id]),
                ],
            ]);
        return $this->view();
    }




    public function getFieldsForm(): array
    {
        return [
            Field::string('name', trans('shop.product.name'))->required(),
            Field::string('slug', trans('shop.product.slug'))->required(),
            Field::string('sku', trans('shop.product.sku'))->optional(),

            Field::select('category_id', trans('shop.product.category'))
                ->setOptions($this->getCategoryOptions())
                ->advanced()
                ->optional(),

            Field::price('cost_cny', trans('shop.product.cost_cny'))->optional(),
            Field::price('sale_price_cny', trans('shop.product.sale_price_cny'))->optional(),
            Field::number('stock_qty', trans('shop.combinations.stock'))->withDefaultValue(0)->required(),
            Field::number('point_per_unit', trans('shop.product.point_per_unit'))->withDefaultValue(0)->required(),

            Field::boolean('active', trans('shop.product.active'))->withDefaultValue(true),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::make('thumb', 'id') // virtual column bound to existing DB col to avoid select errors
                ->withTitle(trans('shop.product.image'))
                ->skipDatabase()
                ->width('70px')
                ->searchable(false)
                ->sortable(false)
                ->customMethod('renderListThumb'),
            // Bind ID explicitly to base alias to avoid collisions (e.g., categories.id)
            Field::make('id', 'a.id')->withTitle(trans('shop.common.id'))->sortable()->width('80px'),
            Field::make('name')->withTitle(trans('shop.product.name'))->searchable()->sortable(),
            Field::make('sku')->withTitle(trans('shop.product.sku'))->searchable()->sortable()->width('140px'),
            Field::select('category_id')->withTitle(trans('shop.product.category'))
                ->setOptions(['' => trans('shop.common.all')] + $this->getCategoryOptions())
                ->filterType(Field::SELECT)
                ->customMethod('renderCategoryName')
                ->width('160px'),
            Field::make('price_irt', 'price')
                ->withTitle(trans('shop.product.price'))
                ->skipDatabase()
                ->customMethod('renderPriceIrt')
                ->searchable(false)
                ->sortable(false)
                ->width('120px'),
            // Availability badge (cached computation)
            Field::make('availability','')
                ->withTitle('موجودی')
                ->skipDatabase()
                ->customMethod('renderAvailabilityBadge')
                ->searchable(false)
                ->sortable(false)
                ->width('160px'),
            Field::boolean('active')->withTitle(trans('shop.product.active'))->sortable()->width('100px'),
            Field::date('created_at')->withTitle(trans('shop.common.created_at'))->sortable()->width('140px'),
        ];
    }

    public function renderAvailabilityBadge($row): string
    {
        $pid = (int)($row->id ?? 0);
        if ($pid <= 0) { return '<span class="text-muted">—</span>'; }
        $avail = \RMS\Shop\Models\Product::availabilityForId($pid);
        $label = e($avail['label'] ?? '—');
        $class = e($avail['badge_class'] ?? 'secondary');
        $hint = 'انبار محصول: '.(int)($avail['product_stock'] ?? 0).' | ترکیب‌ها: '.(int)($avail['comb_stock'] ?? 0);
        return '<span class="badge bg-'.$class.'" title="'.e($hint).'">'.$label.'</span>';
    }

    public function rules(): array
    {
        return [
            'name' => ['required','string','max:190'],
            'slug' => ['required','string','max:190'],
            'sku'  => ['nullable','string','max:190'],
            'price' => ['nullable','numeric'],
            'sale_price' => ['nullable','numeric'],
            'cost_cny' => ['nullable','numeric'],
            'sale_price_cny' => ['nullable','numeric'],
            'stock_qty' => ['nullable','integer','min:0'],
            'point_per_unit' => ['nullable','integer','min:0'],
            'discount_type' => ['nullable','in:percent,amount'],
            'discount_value' => ['nullable','numeric','min:0'],
            'active' => ['boolean'],
            'attributes_json' => ['nullable','string'],
            'combinations_json' => ['nullable','string'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => trans('shop.product.name'),
            'slug' => trans('shop.product.slug'),
            'sku' => trans('shop.product.sku'),
            'category_id' => trans('shop.product.category'),
            'cost_cny' => trans('shop.product.cost_cny'),
            'sale_price_cny' => trans('shop.product.sale_price_cny'),
            'stock_qty' => trans('shop.combinations.stock'),
            'point_per_unit' => trans('shop.product.point_per_unit'),
            'active' => trans('shop.product.active'),
            'short_desc' => 'توضیح کوتاه',
            'description' => 'توضیحات محصول',
            'attributes_json' => 'ویژگی‌ها',
            'combinations_json' => 'ترکیب‌ها',
        ];
    }

    protected function validationMessages(): array
    {
        return [
            'required' => ':attribute الزامی است.',
            'string' => ':attribute باید متن باشد.',
            'max.string' => ':attribute نباید بیش از :max کاراکتر باشد.',
            'integer' => ':attribute باید عدد صحیح باشد.',
            'numeric' => ':attribute باید عددی باشد.',
            'min' => ':attribute نباید کمتر از :min باشد.',
            'boolean' => ':attribute نامعتبر است.',
        ];
    }

    // Override default store to redirect back to edit (stay on edit after save)
    public function store(\RMS\Core\Requests\Store $request): \Illuminate\Http\RedirectResponse
    {
        $rules = (new ProductStoreRequest())->rules();
        $attributes = (new ProductStoreRequest())->attributes();
        $fields = $request->validate($rules, $this->validationMessages(), $attributes);
        // Conditional validation: if percent, cap at 100
        if (($fields['discount_type'] ?? null) === 'percent' && isset($fields['discount_value'])) {
            $fields['discount_value'] = max(0, min(100, (float)$fields['discount_value']));
        }
        $fields['active'] = !empty($fields['active']);
        // normalize discount percent to [0,100]
        if (($fields['discount_type'] ?? null) === 'percent') {
            $fields['discount_value'] = isset($fields['discount_value']) ? max(0, min(100, (float)$fields['discount_value'])) : null;
        }
        $product = app(\RMS\Shop\Services\ProductService::class)->create($fields);

        // Keep attribute/combo logic consistent
        $this->afterAdd($request, $product->id, $product);

        // Determine fragment to return to the same tab
        $allowedTabs = ['tab_basic','tab_pricing','tab_attributes','tab_features','tab_images','tab_video'];
        $activeTab = (string) $request->input('active_tab', 'tab_basic');
        if (!in_array($activeTab, $allowedTabs, true)) { $activeTab = 'tab_basic'; }

        return redirect()->to(route('admin.shop.products.edit', ['product' => (int)$product->id]).'#'.$activeTab)
            ->with('success', trans('admin.saved_successfully') ?? 'ذخیره شد');
    }

    // Override default update to redirect back to edit (stay on edit after save)
    public function update(\Illuminate\Http\Request $request, $id): \Illuminate\Http\RedirectResponse
    {
        $rules = (new \App\Http\Requests\Admin\Shop\ProductUpdateRequest())->rules();
        $attributes = (new \App\Http\Requests\Admin\Shop\ProductUpdateRequest())->attributes();
        $fields = $request->validate($rules, $this->validationMessages(), $attributes);
        // Conditional validation: if percent, cap at 100
        if (($fields['discount_type'] ?? null) === 'percent' && isset($fields['discount_value'])) {
            $fields['discount_value'] = max(0, min(100, (float)$fields['discount_value']));
        }
        /** @var Product $product */
        $product = Product::findOrFail((int)$id);
        // normalize discount percent to [0,100]
        if (($fields['discount_type'] ?? null) === 'percent') {
            $fields['discount_value'] = isset($fields['discount_value']) ? max(0, min(100, (float)$fields['discount_value'])) : null;
        }
        $product = app(\RMS\Shop\Services\ProductService::class)->update($product, $fields);

        $this->afterUpdate($request, (int)$id, $product);

        // Determine fragment to return to the same tab
        $allowedTabs = ['tab_basic','tab_pricing','tab_attributes','tab_features','tab_images','tab_video'];
        $activeTab = (string) $request->input('active_tab', 'tab_basic');
        if (!in_array($activeTab, $allowedTabs, true)) { $activeTab = 'tab_basic'; }

        return redirect()->to(route('admin.shop.products.edit', ['product' => (int)$id]).'#'.$activeTab)
            ->with('success', trans('admin.updated_successfully') ?? 'با موفقیت ذخیره شد');
    }

    protected function afterAdd(Request $request, string|int $id, Model $model): void
    {
        // Only mutate attributes/combinations when explicit payload is present (avoid unintended detach)
        if ($request->has('attributes_json') || $request->has('combinations_json')) {
            $this->saveAttributesAndCombinations((int)$id, $request);
        }
    }

    protected function afterUpdate(Request $request, string|int $id, Model $model): void
    {
        // Only mutate attributes/combinations when explicit payload is present (avoid unintended detach)
        if ($request->has('attributes_json') || $request->has('combinations_json')) {
            $this->saveAttributesAndCombinations((int)$id, $request);
        }
    }

    private function saveAttributesAndCombinations(int $productId, Request $request): void
    {
        $attrsJson = (string)$request->input('attributes_json','[]');
        $combsJson = (string)$request->input('combinations_json','[]');
        if (!\is_string($attrsJson) || !\is_string($combsJson)) { return; }
        \RMS\Shop\Services\ProductAttributesService::saveFromJson($productId, $attrsJson, $combsJson);
    }









    public function updateBasicAjax(\RMS\Shop\Http\Requests\Admin\Shop\UpdateBasicRequest $request, int $productId)
    {
        $fields = $request->validated();
        if (($fields['discount_type'] ?? null) === 'percent' && isset($fields['discount_value'])) {
            $fields['discount_value'] = max(0, min(100, (float)$fields['discount_value']));
        }
        $fields['active'] = !empty($fields['active']);
        // Eloquent update (keeps behavior)
        Product::query()->where('id', (int)$productId)->update(array_merge($fields, ['updated_at'=>now()]));
        return response()->json(['ok'=>true,'message'=>trans('admin.updated_successfully')]);
    }

    public function updatePricingAjax(\RMS\Shop\Http\Requests\Admin\Shop\UpdatePricingRequest $request, int $productId)
    {
        $fields = $request->validated();
        // Normalize discount percent to [0,100]
        if (($fields['discount_type'] ?? null) === 'percent' && isset($fields['discount_value'])) {
            $fields['discount_value'] = max(0, min(100, (float)$fields['discount_value']));
        }
        // Update only pricing-related fields
        Product::query()->where('id', (int)$productId)->update(array_merge($fields, ['updated_at'=>now()]));
        return response()->json(['ok'=>true,'message'=>trans('admin.updated_successfully')]);
    }

    private function generateVariant(string $src, string $dst, ?int $w, ?int $h, bool $crop, int $quality): void
    {
        try {
            if (class_exists(\Intervention\Image\ImageManager::class)) {
                $manager = \Intervention\Image\ImageManager::gd();
                $img = $manager->read($src);
                if ($w && $h) {
                    $img = $crop ? $img->cover($w,$h) : $img->scaleDown($w,$h);
                } elseif ($w || $h) {
                    $img = $img->scaleDown($w ?: null, $h ?: null);
                }
                @mkdir(dirname($dst), 0777, true);
                $img->save($dst, $quality);
                return;
            }
        } catch (\Throwable $e) { }
        @mkdir(dirname($dst), 0777, true);
        @copy($src, $dst);
    }

    public function boolFields(): array
    {
        return ['active'];
    }

    private function getCategoryOptions(): array
    {
        return app(CategoryTreeService::class)->flatOptions(false);
    }

    public function saveCombinations(Request $request, int $productId)
    {
        // expects attributes_json and combinations_json from UI state
        $request->validate([
            'attributes_json' => ['required','string'],
            'combinations_json' => ['required','string'],
        ]);
        // Delegate to service (keeps behavior)
        \RMS\Shop\Services\ProductAttributesService::saveFromJson($productId, (string)$request->input('attributes_json','[]'), (string)$request->input('combinations_json','[]'));

        // Return fresh data same as edit()
        $attrRows = ProductAttribute::query()->where('product_id', (int)$productId)->orderBy('sort')->with('values')->get();
        $attributes = $attrRows->map(function($a){
            $values = ($a->values ?? collect())->sortBy('sort')->map(function($v){
                return [
                    'id' => $v->id,
                    'value' => $v->value,
                    'image_path' => $v->image_path,
                    'color' => $v->color,
                    'sort' => $v->sort,
                ];
            })->values()->all();
            return [
                'id' => $a->id,
                'name' => $a->name,
                'type' => $a->type ?? 'text',
                'ui' => $a->ui ?? 'pill',
                'sort' => $a->sort,
                'values' => $values,
            ];
        })->values()->all();

        $combRows = ProductCombination::query()->where('product_id', (int)$productId)->orderBy('id','desc')->get();
        $combinations = $combRows->map(function($c){
            $valIds = $c->values()->orderBy('id')->pluck('attribute_value_id')->toArray();
            return [
                'id' => $c->id,
                'sku' => $c->sku,
                'price' => $c->price,
                'price_cny' => $c->sale_price_cny,
                'stock' => $c->stock_qty,
                'active' => (bool)$c->active,
                'attribute_value_ids' => $valIds,
            ];
        })->values()->all();

        return response()->json(['ok'=>true,'attributes'=>$attributes,'combinations'=>$combinations]);
    }

    // Custom list cell renderer: AVIF thumbnail (virtual field)
    public function renderListThumb($row): string
    {
        $productId = null;
        if (is_object($row)) { $productId = $row->id ?? ($row->product_id ?? null); }
        if (is_array($row)) { $productId = $productId ?: ($row['id'] ?? ($row['product_id'] ?? null)); }
        if (!$productId) { return ''; }

        $imgRow = ProductImage::query()
            ->where('product_id', (int)$productId)
            ->orderByDesc('is_main')
            ->orderBy('sort')
            ->first();
        if (!$imgRow) { return ''; }

        $rel = (string)$imgRow->path; // uploads/products/.../orig/uuid.jpg
        $dir = \Illuminate\Support\Str::beforeLast($rel, '/orig/');
        $name = \Illuminate\Support\Str::afterLast($rel, '/orig/');
        $base = pathinfo($name, PATHINFO_FILENAME);
        $avifRel = $dir.'/avif/'.$base.'.avif';
        $urlOrig = e(\Storage::disk('public')->url($rel));
        $urlAvif = \Storage::disk('public')->exists($avifRel) ? e(\Storage::disk('public')->url($avifRel)) : null;
        $alt = e((string)($row->name ?? ($row['name'] ?? '')));
        $src = $urlAvif ?: $urlOrig; // Prefer AVIF if available
        return "<img src=\"$src\" alt=\"$alt\" loading=\"lazy\" decoding=\"async\" style=\"width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid var(--bs-border-color);\">";
    }

    public function renderCategoryName($row): string
    {
        $val = null;
        if (is_object($row)) { $val = $row->category_name ?? null; }
        if (is_array($row)) { $val = $val ?? ($row['category_name'] ?? null); }
        return e((string)($val ?? ''));
    }

    // Get product features grouped by categories
    private function getProductFeaturesGrouped(int $productId): array
    {
        $features = ProductFeature::query()
            ->from('product_features as pf')
            ->leftJoin('product_feature_categories as pfc', 'pfc.id', '=', 'pf.category_id')
            ->where('pf.product_id', $productId)
            ->orderBy('pfc.sort')
            ->orderBy('pf.sort')
            ->select('pf.*', 'pfc.name as category_name', 'pfc.icon as category_icon')
            ->get();

        $grouped = [];
        foreach ($features as $feature) {
            $categoryId = $feature->category_id ?: 0; // 0 for uncategorized
            $categoryName = $feature->category_name ?: 'بدون دسته‌بندی';
            $categoryIcon = $feature->category_icon ?: 'ph-info';

            if (!isset($grouped[$categoryId])) {
                $grouped[$categoryId] = [
                    'id' => $categoryId,
                    'name' => $categoryName,
                    'icon' => $categoryIcon,
                    'features' => []
                ];
            }

            $grouped[$categoryId]['features'][] = [
                'id' => (int)$feature->id,
                'name' => (string)$feature->name,
                'value' => (string)($feature->value ?? ''),
                'sort' => (int)$feature->sort,
                'category_id' => $categoryId ?: null,
                'feature_definition_id' => $feature->feature_definition_id ? (int) $feature->feature_definition_id : null,
                'feature_value_id' => $feature->feature_value_id ? (int) $feature->feature_value_id : null,
            ];
        }

        return array_values($grouped);
    }

    // Compute IRT price for display using latest CNY→IRT rate and product's sale_price_cny (fallback: cost_cny)
    public function renderPriceIrt($row): string
    {
        $saleCny = null;
        $costCny = null;
        if (is_object($row)) { $saleCny = $row->sale_price_cny ?? null; $costCny = $row->cost_cny ?? null; }
        if (is_array($row)) { $saleCny = $saleCny ?? ($row['sale_price_cny'] ?? null); $costCny = $costCny ?? ($row['cost_cny'] ?? null); }
        $cny = $saleCny ?? $costCny;
        if ($cny === null) { return ''; }
        // Apply discount before converting
        $dt = null; $dv = null;
        if (is_object($row)) { $dt = $row->discount_type ?? null; $dv = $row->discount_value ?? null; }
        if (is_array($row)) { $dt = $dt ?? ($row['discount_type'] ?? null); $dv = $dv ?? ($row['discount_value'] ?? null); }
        $finalCny = \RMS\Shop\Services\PricingService::applyDiscount($cny, $dt, $dv);
        $currency = app(\RMS\Shop\Services\CurrencyService::class);
        $rate = $currency->getCnyToIrtRate();
        if ($rate === null || $rate <= 0) { return ''; }
        $irt = (float)$finalCny * (float)$rate;
        return number_format($irt, 0, '.', ',');
    }

    public function getProductVideos($productId, Request $request)
    {
        $product = Product::findOrFail($productId);
        $videoService = app(VideoLibraryService::class);
        $videos = $videoService->searchVideosForProduct($product, $request->query('search', ''), $request->query('page', 1));
        return response()->json($videos);
    }

    public function assignVideoToProduct($productId, Request $request)
    {
        $product = Product::findOrFail($productId);
        $request->validate(['video_ids' => 'required|array', 'video_ids.*' => 'integer|exists:video_library,id']);
        $videoService = app(VideoLibraryService::class);
        $videoService->assignVideosTo($product, $request->video_ids);
        return response()->json(['message' => 'Videos assigned']);
    }

    public function assignVideos($productId, Request $request)
    {
        $product = Product::findOrFail($productId);
        $request->validate(['video_ids' => 'required|array', 'video_ids.*' => 'integer|exists:video_library,id']);
        $videoService = app(VideoLibraryService::class);
        $videoService->assignVideosTo($product, $request->video_ids);
        return response()->json(['success' => true, 'message' => 'ویدیوها با موفقیت اختصاص یافتند']);
    }

    // detachVideos
    public function detachVideos($productId, Request $request)
    {
        $product = Product::findOrFail($productId);
        $request->validate(['video_ids' => 'required|array', 'video_ids.*' => 'integer|exists:video_library,id']);
        $videoService = app(VideoLibraryService::class);
        $videoService->detachVideosFrom($product, $request->video_ids);
        return response()->json(['message' => 'Videos detached']);
    }

    public function setMainVideo($productId, Request $request)
    {
        $product = Product::findOrFail($productId);
        $request->validate(['video_id' => 'required|integer|exists:video_library,id']);
        $videoService = app(VideoLibraryService::class);
        $video = \RMS\Shop\Models\VideoLibrary::findOrFail($request->video_id);
        $videoService->setMainVideo($video, $product);
        return response()->json(['message' => 'Main video set']);
    }

    public function sortVideos($productId, Request $request)
    {
        $product = Product::findOrFail($productId);
        $request->validate(['sort_order' => 'required|array']);
        $videoService = app(VideoLibraryService::class);
        $videoService->updateSort($product, $request->sort_order);
        return response()->json(['message' => 'Videos sorted']);
    }

    // Similar methods for setMainVideo, deleteVideo, etc.
}
