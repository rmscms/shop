<?php
// git-trigger
namespace RMS\Shop\Http\Controllers\Admin;

use RMS\Shop\Models\Product;
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
use App\Jobs\ConvertImageToAvif;
use App\Jobs\CleanupLegacyImageSizes;
use Throwable;

class ProductsController extends AdminController implements HasList, HasForm, ShouldFilter, ChangeBoolField
{
    public function table(): string { return 'products'; }
    public function modelName(): string { return Product::class; }
    public function baseRoute(): string { return 'shop_products'; }
    public function routeParameter(): string { return 'shop_product'; }

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
        $categories = \DB::table('categories')->orderBy('name')->pluck('name','id')->toArray();
        $rate = \DB::table('currency_rates')->where('base_code','CNY')->where('quote_code','IRT')->orderByDesc('effective_at')->value('sell_rate');


        $this->view->setTpl('pages.shop.products.edit')
            ->withPlugins([ 'confirm-modal', 'image-uploader', 'ckeditor'])
            ->withJs('vendor/shop/admin/js/products/edit.js', true)
            ->withJs('vendor/shop/admin/js/products/attributes.js', true)
            ->withJs('vendor/shop/admin/js/products/video.js', true)
            ->withJs('vendor/shop/admin/js/products/features.js', true)
            ->withVariables([
                'product' => null,
                'categories' => $categories,
                'attributes' => [],
                'combinations' => [],
            ])
            ->withJsVariables([
                'apiEndpoints' => [
                    'store' => route('admin.shop_products.store'),
                ],
                'images' => null,
                'currency' => [ 'base' => 'IRT', 'cny' => 'CNY' ],
                'currencyRate' => $rate,
                'data' => [
                    'attributes' => [],
                    'combinations' => []
                ],
                'flash' => [
                    'success' => session('success'),
                    'error' => session('error'),
                    'warning' => session('warning'),
                    'info' => session('info'),
                ],
            ]);
        $this->useUserTemplates();
        return $this->view(false);
    }

    public function edit(Request $request, $id)
    {
        $product = \DB::table('products')->where('id', (int)$id)->first();
        abort_if(!$product, 404);
        $categories = \DB::table('categories')->orderBy('name')->pluck('name','id')->toArray();
        $attrRows = \DB::table('product_attributes')->where('product_id', (int)$id)->orderBy('sort')->get();
        $attributes = $attrRows->map(function($a){
            $values = DB::table('product_attribute_values')->where('attribute_id', $a->id)->orderBy('sort')->get()->map(function($v){
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

        $combRows = \DB::table('product_combinations')->where('product_id', (int)$id)->orderBy('id','desc')->get();
        $combinations = $combRows->map(function($c){
            $valIds = DB::table('product_combination_values')->where('combination_id', $c->id)->orderBy('id')->pluck('attribute_value_id')->toArray();
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

        // image counts per combination
        $imageCounts = DB::table('product_combination_images')
            ->join('product_combinations','product_combinations.id','=','product_combination_images.combination_id')
            ->where('product_combinations.product_id', (int)$id)
            ->select('product_combination_images.combination_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('product_combination_images.combination_id')
            ->pluck('cnt','combination_id')
            ->toArray();

        // assigned images mapping: path => [combination_id,...]
        $assignedRows = DB::table('product_combination_images')
            ->join('product_combinations','product_combinations.id','=','product_combination_images.combination_id')
            ->where('product_combinations.product_id', (int)$id)
            ->select('product_combination_images.path','product_combination_images.combination_id')
            ->get();
        $assignedMap = [];
        foreach ($assignedRows as $ar) {
            $assignedMap[$ar->path] = $assignedMap[$ar->path] ?? [];
            $assignedMap[$ar->path][] = (int)$ar->combination_id;
        }

        // labels for combinations (SKU + attributes)
        $combIds = $combRows->pluck('id')->all();
        $vals = DB::table('product_combination_values as pcv')
            ->join('product_attribute_values as pav','pav.id','=','pcv.attribute_value_id')
            ->join('product_attributes as pa','pa.id','=','pav.attribute_id')
            ->whereIn('pcv.combination_id', $combIds)
            ->select('pcv.combination_id','pa.name as attr','pav.value as val')
            ->orderBy('pcv.combination_id')
            ->orderBy('pa.sort')
            ->get();
        $comboLabels = [];
        $grouped = [];
        foreach ($vals as $v) { $grouped[$v->combination_id][] = $v->attr.': '.$v->val; }
        foreach ($combRows as $cr) {
            $label = isset($grouped[$cr->id]) ? implode(' / ', $grouped[$cr->id]) : '';
            $comboLabels[$cr->id] = ($cr->sku ? ($cr->sku.' — ') : '') . $label;
        }

        $rate = \DB::table('currency_rates')->where('base_code','CNY')->where('quote_code','IRT')->orderByDesc('effective_at')->value('sell_rate');
        $productImages = DB::table('product_images')->where('product_id', (int)$id)->orderBy('sort')->get()->map(function($r){
            return [
                'url' => Storage::disk('public')->url($r->path),
                'path' => $r->path,
                'filename' => basename($r->path),
                'size' => null,
            ];
        })->values()->all();

        $productVideos = DB::table('product_videos')->where('product_id', (int)$id)->orderBy('sort')->orderBy('id')->get();

        $this->useUserTemplates();
        $this->view->setTpl('pages.shop.products.edit')
            ->withPlugins([ 'confirm-modal', 'image-uploader', 'ckeditor'])
            ->withJs('vendor/shop/admin/js/products/edit.js', true)
            ->withJs('vendor/shop/admin/js/products/attributes.js', true)
            ->withJs('vendor/shop/admin/js/products/video.js', true)
            ->withJs('vendor/shop/admin/js/products/features-new.js', true)
            ->withVariables([
                'product' => $product,
                'categories' => $categories,
                'attributes' => $attributes,
                'combinations' => $combinations,
                'productImages' => $productImages,
                'productVideos' => $productVideos,
                'features' => $this->getProductFeaturesGrouped((int)$id),
                'featureCategories' => DB::table('product_feature_categories')->where('active', 1)->orderBy('sort')->get(['id','name','icon'])->map(function($r){ return ['id'=>(int)$r->id,'name'=>(string)$r->name,'icon'=>(string)($r->icon??'ph-info')]; })->values()->all(),
            ])
            ->withJsVariables([
                'apiEndpoints' => [
                    'update' => route('admin.shop_products.update', ['shop_product' => (int)$id]),
                    'saveCombinations' => route('admin.shop_products.combinations.save', ['shop_product' => (int)$id]),
                    'saveBasic' => route('admin.shop_products.basic.update', ['shop_product' => (int)$id]),
                ],
                'images' => [
                    'list' => route('admin.shop_products.images.list', ['shop_product'=>(int)$id]),
                    'upload' => route('admin.shop_products.images.upload', ['shop_product'=>(int)$id]),
                    'delete' => route('admin.shop_products.images.delete', ['shop_product'=>(int)$id, 'image'=>'__ID__']),
                    'detach' => route('admin.shop_products.images.detach', ['shop_product'=>(int)$id]),
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
                    'featureCategories' => DB::table('product_feature_categories')->where('active', 1)->orderBy('sort')->get(['id','name','icon'])->map(function($r){ return ['id'=>(int)$r->id,'name'=>(string)$r->name,'icon'=>(string)($r->icon??'ph-info')]; })->values()->all(),
                ],
                'endpoints' => [
                    'saveFeatures' => route('admin.shop_products.features.save', ['shop_product'=>(int)$id]),
                    'createCategory' => route('admin.shop_products.features.create-category'),
                ],
                'flash' => [
                    'success' => session('success'),
                    'error' => session('error'),
                    'warning' => session('warning'),
                    'info' => session('info'),
                ],
            ]);
        return $this->view(false);
    }

    // Save product features (key/value) via AJAX with categories
    public function saveFeatures(Request $request, int $productId)
    {
        $data = $request->validate([
            'categories' => ['array'],
            'categories.*.category_id' => ['nullable','integer','exists:product_feature_categories,id'],
            'categories.*.features' => ['array'],
            'categories.*.features.*.name' => ['required','string','max:190'],
            'categories.*.features.*.value' => ['nullable','string','max:2000'],
            'categories.*.features.*.sort' => ['nullable','integer','min:0'],
        ]);
        $categories = $data['categories'] ?? [];
        DB::transaction(function() use ($productId, $categories){
            DB::table('product_features')->where('product_id',(int)$productId)->delete();
            $now = now();
            $customCategoryMap = []; // Map custom IDs to real IDs

            foreach ($categories as $cat) {
                $categoryId = !empty($cat['category_id']) ? (int)$cat['category_id'] : null;

                foreach (($cat['features'] ?? []) as $i => $feature) {
                    DB::table('product_features')->insert([
                        'product_id' => (int)$productId,
                        'category_id' => $categoryId,
                        'name' => (string)$feature['name'],
                        'value' => (string)($feature['value'] ?? ''),
                        'sort' => isset($feature['sort']) ? (int)$feature['sort'] : ($i+1),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
        return response()->json(['ok'=>true]);
    }

    // Create new feature category via AJAX
    public function createFeatureCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:190'],
            'icon' => ['nullable','string','max:50'],
        ]);

        $now = now();
        $categoryId = DB::table('product_feature_categories')->insertGetId([
            'name' => $data['name'],
            'slug' => Str::slug($data['name'] . '-' . time()),
            'icon' => $data['icon'] ?? 'ph-tag',
            'sort' => 99,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return response()->json([
            'ok' => true,
            'category' => [
                'id' => $categoryId,
                'name' => $data['name'],
                'icon' => $data['icon'] ?? 'ph-tag',
            ]
        ]);
    }

    /**
     * بازسازی فایل‌های AVIF برای یک محصول
     */
    public function regenerateAvif(Request $request, int $productId)
    {
        $product = DB::table('products')->where('id', (int)$productId)->first();
        abort_if(!$product, 404);

        try {
            $results = [
                'product_images' => 0,
                'combination_images' => 0,
                'converted' => 0,
                'failed' => 0,
                'errors' => []
            ];

            // بازسازی تصاویر اصلی محصول
            $productImages = DB::table('product_images')
                ->where('product_id', (int)$productId)
                ->get(['path']);

            foreach ($productImages as $img) {
                $results['product_images']++;
                $relativePath = $img->path; // مثال: uploads/products/123/product/orig/uuid.jpg

                // ارسال به صف AVIF (از Job موجود استفاده می‌کنیم)
                ConvertImageToAvif::dispatch($relativePath, 70)
                    ->delay(now()->addSeconds(2)); // تاخیر کوتاه

                $results['converted']++;

                Log::info('Product image queued for AVIF regeneration', [
                    'product_id' => $productId,
                    'path' => $relativePath
                ]);
            }

            // بازسازی تصاویر combination ها
            $combinationImages = DB::table('product_combination_images as pci')
                ->join('product_combinations as pc', 'pc.id', '=', 'pci.combination_id')
                ->where('pc.product_id', (int)$productId)
                ->get(['pci.path']);

            foreach ($combinationImages as $img) {
                $results['combination_images']++;
                $relativePath = $img->path;

                ConvertImageToAvif::dispatch($relativePath, 70)
                    ->delay(now()->addSeconds(2));

                $results['converted']++;

                Log::info('Combination image queued for AVIF regeneration', [
                    'product_id' => $productId,
                    'path' => $relativePath
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "بازسازی آغاز شد! {$results['converted']} تصویر در صف قرار گرفت.",
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Error regenerating product AVIF files', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'خطا در بازسازی: ' . $e->getMessage()
            ], 500);
        }
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
        $rules = $this->rules();
        // Extra fields used on basic tab
        $rules['category_id'] = ['nullable','integer'];
        $rules['short_desc'] = ['nullable','string'];
        $rules['description'] = ['nullable','string'];
        $fields = $request->validate($rules, $this->validationMessages(), $this->validationAttributes());
        // Conditional validation: if percent, cap at 100
        if (($fields['discount_type'] ?? null) === 'percent' && isset($fields['discount_value'])) {
            $fields['discount_value'] = max(0, min(100, (float)$fields['discount_value']));
        }
        $fields['active'] = !empty($fields['active']);
        // normalize discount percent to [0,100]
        if (($fields['discount_type'] ?? null) === 'percent') {
            $fields['discount_value'] = isset($fields['discount_value']) ? max(0, min(100, (float)$fields['discount_value'])) : null;
        }
        $product = Product::create([
            'name' => (string)$fields['name'],
            'slug' => (string)$fields['slug'],
            'sku' => $fields['sku'] ?? null,
            'category_id' => !empty($fields['category_id']) ? (int)$fields['category_id'] : null,
            'active' => (bool)$fields['active'],
            'point_per_unit' => (int)($fields['point_per_unit'] ?? 0),
            'cost_cny' => $fields['cost_cny'] ?? null,
            'sale_price_cny' => $fields['sale_price_cny'] ?? null,
            'discount_type' => $fields['discount_type'] ?? null,
            'discount_value' => $fields['discount_value'] ?? null,
            'stock_qty' => (int)($fields['stock_qty'] ?? 0),
            'short_desc' => $fields['short_desc'] ?? null,
            'description' => $fields['description'] ?? null,
            'price' => $fields['price'] ?? null,
            'sale_price' => $fields['sale_price'] ?? null,
        ]);

        // Keep attribute/combo logic consistent
        $this->afterAdd($request, $product->id, $product);

        // Determine fragment to return to the same tab
        $allowedTabs = ['tab_basic','tab_pricing','tab_attributes','tab_features','tab_images','tab_video'];
        $activeTab = (string) $request->input('active_tab', 'tab_basic');
        if (!in_array($activeTab, $allowedTabs, true)) { $activeTab = 'tab_basic'; }

        return redirect()->to(route('admin.shop_products.edit', ['shop_product' => (int)$product->id]).'#'.$activeTab)
            ->with('success', trans('admin.saved_successfully') ?? 'ذخیره شد');
    }

    // Override default update to redirect back to edit (stay on edit after save)
    public function update(\Illuminate\Http\Request $request, $id): \Illuminate\Http\RedirectResponse
    {
        $rules = $this->rules();
        // Extra fields used on basic tab
        $rules['category_id'] = ['nullable','integer'];
        $rules['short_desc'] = ['nullable','string'];
        $rules['description'] = ['nullable','string'];
        $fields = $request->validate($rules, $this->validationMessages(), $this->validationAttributes());
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
        $product->fill([
            'name' => (string)$fields['name'],
            'slug' => (string)$fields['slug'],
            'sku' => $fields['sku'] ?? null,
            'category_id' => !empty($fields['category_id']) ? (int)$fields['category_id'] : null,
            'active' => !empty($fields['active']),
            'point_per_unit' => (int)($fields['point_per_unit'] ?? 0),
            'cost_cny' => $fields['cost_cny'] ?? null,
            'sale_price_cny' => $fields['sale_price_cny'] ?? null,
            'discount_type' => $fields['discount_type'] ?? null,
            'discount_value' => $fields['discount_value'] ?? null,
            'stock_qty' => (int)($fields['stock_qty'] ?? 0),
            'short_desc' => $fields['short_desc'] ?? null,
            'description' => $fields['description'] ?? null,
            'price' => $fields['price'] ?? null,
            'sale_price' => $fields['sale_price'] ?? null,
        ]);
        $product->save();

        $this->afterUpdate($request, (int)$id, $product);

        // Determine fragment to return to the same tab
        $allowedTabs = ['tab_basic','tab_pricing','tab_attributes','tab_features','tab_images','tab_video'];
        $activeTab = (string) $request->input('active_tab', 'tab_basic');
        if (!in_array($activeTab, $allowedTabs, true)) { $activeTab = 'tab_basic'; }

        return redirect()->to(route('admin.shop_products.edit', ['shop_product' => (int)$id]).'#'.$activeTab)
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
        $attrs = json_decode((string)$request->input('attributes_json','[]'), true) ?: [];
        $combs = json_decode((string)$request->input('combinations_json','[]'), true) ?: [];
        // Guard: if neither payload is provided, do nothing
        if (empty($attrs) && empty($combs)) { return; }
        DB::transaction(function() use ($productId, $attrs, $combs) {
            // cleanup existing
            DB::table('product_combination_values')->whereIn('combination_id', function($q) use($productId){
                $q->select('id')->from('product_combinations')->where('product_id', $productId);
            })->delete();
            DB::table('product_combinations')->where('product_id', $productId)->delete();
            DB::table('product_attribute_values')->whereIn('attribute_id', function($q) use($productId){
                $q->select('id')->from('product_attributes')->where('product_id', $productId);
            })->delete();
            DB::table('product_attributes')->where('product_id', $productId)->delete();

            // map tmp value ids to real ids after insert
            $valIdMap = [];

            foreach ($attrs as $i => $a) {
                $attrId = DB::table('product_attributes')->insertGetId([
                    'product_id' => $productId,
                    'name' => (string)($a['name'] ?? ''),
                    'type' => (string)($a['type'] ?? 'text'),
                    'ui' => (string)($a['ui'] ?? 'pill'),
                    'sort' => (int)($a['sort'] ?? $i),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach (($a['values'] ?? []) as $j => $v) {
                    $newValId = DB::table('product_attribute_values')->insertGetId([
                        'attribute_id' => $attrId,
                        'value' => (string)($v['value'] ?? ''),
                        'image_path' => $v['image_path'] ?? null,
                        'color' => $v['color'] ?? null,
                        'sort' => (int)($v['sort'] ?? $j),
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    if (!empty($v['tmpId']) && is_string($v['tmpId'])) {
                        $valIdMap[$v['tmpId']] = $newValId;
                    }
                    if (!empty($v['id']) && is_numeric($v['id'])) {
                        $valIdMap[(int)$v['id']] = $newValId;
                    }
                }
            }

            foreach ($combs as $c) {
                $skuVal = trim((string)($c['sku'] ?? ''));
                $combId = DB::table('product_combinations')->insertGetId([
                    'product_id' => $productId,
                    'sku' => ($skuVal !== '' ? $skuVal : null),
                    'price' => $c['price'] ?? null,
                    'sale_price' => null,
                    'sale_price_cny' => $c['price_cny'] ?? null,
                    'stock_qty' => (int)($c['stock'] ?? 0),
                    'active' => !empty($c['active']),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach (($c['attribute_value_ids'] ?? []) as $vid) {
                    $destId = null;
                    if (is_numeric($vid)) {
                        $vidInt = (int)$vid;
                        $destId = $valIdMap[$vidInt] ?? $vidInt; // map old id to new if available
                    } elseif (is_string($vid)) {
                        if (isset($valIdMap[$vid])) { $destId = (int)$valIdMap[$vid]; }
                    }
                    if ($destId) {
                        DB::table('product_combination_values')->insert([
                            'combination_id' => $combId,
                            'attribute_value_id' => $destId,
                            'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                }
            }
        });
    }

    // Fallback JSON list (used by custom UI if needed)
    public function listImages(Request $request, int $productId)
    {
        $combinationId = $request->query('combination_id');
        if ($combinationId) {
            $rows = DB::table('product_combination_images')->where('combination_id', (int)$combinationId)->orderBy('sort')->get();
        } else {
            $rows = DB::table('product_images')->where('product_id', $productId)->orderBy('sort')->get();
        }
        return response()->json(['ok'=>true,'data'=>$rows]);
    }

    public function uploadImage(Request $request, int $productId, ?int $combination = null)
    {
        // Validate any incoming file fields as images (supports dynamic field names like gallery__comb_123 or arrays)
        $rules = [];
        $filesArr = $request->allFiles();
        $addRules = function($value, $prefix) use (&$addRules, &$rules) {
            if (is_array($value)) {
                foreach ($value as $k => $v) { $addRules($v, $prefix.($prefix!==''?'.':'').$k); }
            } else if ($value instanceof \Illuminate\Http\UploadedFile) {
                $rules[$prefix] = ['file','image','max:5120'];
            }
        };
        foreach ($filesArr as $key => $value) { $addRules($value, $key); }
        if (!empty($rules)) { Validator::make($request->all(), $rules)->validate(); }

        // Collect first uploaded file from any depth
        $filesArr = $request->allFiles();
        $collected = [];
        $collect = function($v) use (&$collect, &$collected) {
            if (is_array($v)) { foreach ($v as $vv) { $collect($vv); } }
            else if ($v instanceof UploadedFile) { $collected[] = $v; }
        };
        foreach ($filesArr as $v) { $collect($v); }
        $file = $collected[0] ?? null;
        if (!$file) { return response()->json(['success'=>false,'message'=>'no_file'], 400); }
        $ext = strtolower($file->getClientOriginalExtension());
        $name = Str::uuid().'.'.$ext;
        $baseDir = 'uploads/products/'.$productId;
        $dir = $combination ? ($baseDir.'/combinations/'.$combination) : ($baseDir.'/product');
        $path = $file->storeAs($dir.'/orig', $name, 'public');

        // Generate AVIF variant (keep original in /orig)
        ConvertImageToAvif::dispatch($path);
        // Cleanup legacy size files like code_*.ext (optional async)
        CleanupLegacyImageSizes::dispatch($path);

        if ($combination) {
            $imageId = DB::table('product_combination_images')->insertGetId([
                'combination_id' => $combination,
                'path' => $dir.'/orig/'.$name,
                'is_main' => false,
                'sort' => 0,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        } else {
            $imageId = DB::table('product_images')->insertGetId([
                'product_id' => $productId,
                'path' => $dir.'/orig/'.$name,
                'is_main' => false,
                'sort' => 0,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        }

        return response()->json(['ok'=>true,'id'=>$imageId,'path'=>Storage::disk('public')->url($dir.'/orig/'.$name)]);
    }

    public function deleteImage(Request $request, int $productId, int $image)
    {
        $row = DB::table('product_images')->where('id',$image)->first();
        $scope = 'product';
        if (!$row) { $row = DB::table('product_combination_images')->where('id',$image)->first(); $scope = 'combination'; }
        if (!$row) return response()->json(['ok'=>false,'error'=>'not_found'],404);

        $path = $row->path;
        $dir = Str::beforeLast($path, '/orig/');
        $name = Str::afterLast($path, '/orig/');

        // Delete physical files
        Storage::disk('public')->delete($path);
        // Delete AVIF variant if exists
        $avifRel = $dir.'/avif/'.pathinfo($name, PATHINFO_FILENAME).'.avif';
        Storage::disk('public')->delete($avifRel);
        // Cleanup any legacy code_* variants
        $files = Storage::disk('public')->files($dir);
        $suffix = '_'.$name;
        foreach ($files as $f) { if (str_ends_with($f, $suffix)) { Storage::disk('public')->delete($f); } }

        // Delete from database - از هر دو جدول پاک کن اگه همین path رو دارن
        DB::table('product_images')->where('path', $path)->delete();
        DB::table('product_combination_images')->where('path', $path)->delete();

        return response()->json(['ok'=>true]);
    }

    // =====================
    // Video: single per product
    // =====================
    public function videoPage(Request $request, int $productId)
    {
        $product = \DB::table('products')->where('id', (int)$productId)->first();
        abort_if(!$product, 404);
        $video = \DB::table('product_videos')->where('product_id', (int)$productId)->first();
        $this->useUserTemplates();
        return view('admin.pages.shop.products.video', [
            'product' => $product,
            'video' => $video,
            'uploadUrl' => route('admin.shop_products.video.upload', ['shop_product' => (int)$productId]),
            'deleteUrl' => route('admin.shop_products.video.delete', ['shop_product' => (int)$productId]),
        ]);
    }

    public function uploadVideo(Request $request, int $productId)
    {
        $request->validate([
            'video' => ['required','file','mimetypes:video/mp4,video/webm,video/quicktime,video/x-matroska','max:512000'], // max 500MB
        ]);
        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('video');
        $ext = strtolower($file->getClientOriginalExtension());
        $name = (string) Str::uuid().'.'.$ext;
        $dir = 'uploads/products/'.$productId.'/video/orig';
        $rel = $file->storeAs($dir, $name, 'public');

        // Insert new video row
        $videoId = DB::table('product_videos')->insertGetId([
            'product_id' => (int)$productId,
            'title' => null,
            'source_path' => $rel,
            'hls_master_path' => null,
            'poster_path' => null,
            'size_bytes' => (int)($file->getSize() ?: 0),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Dispatch transcode job
        \App\Jobs\TranscodeProductVideo::dispatch((int)$productId, (int)$videoId, $rel);

        return response()->json(['ok' => true, 'video_id' => (int)$videoId, 'message' => trans('admin.file_uploaded') ?? 'ویدیو آپلود شد و در صف پردازش قرار گرفت.']);
    }

    public function deleteVideo(Request $request, int $productId, int $videoId)
    {
        $row = DB::table('product_videos')->where(['id'=>(int)$videoId,'product_id'=>(int)$productId])->first();
        if (!$row) { return response()->json(['ok'=>false,'error'=>'not_found'],404); }
        // Delete stored files
        $disk = Storage::disk('public');
        if (!empty($row->source_path)) { $disk->delete((string)$row->source_path); }
        if (!empty($row->poster_path)) { $disk->delete((string)$row->poster_path); }
        if (!empty($row->hls_master_path)) {
            $hlsDir = dirname((string)$row->hls_master_path);
            foreach ($disk->allFiles($hlsDir) as $f) { $disk->delete($f); }
            @rmdir($disk->path($hlsDir.'/h480'));
            @rmdir($disk->path($hlsDir.'/h720'));
            @rmdir($disk->path($hlsDir));
        }
        DB::table('product_videos')->where('id', (int)$videoId)->delete();
        return response()->json(['ok'=>true]);
    }

    public function videoChunkInit(Request $request, int $productId)
    {
        $request->validate([
            'filename' => ['required','string','max:190'],
        ]);
        $uploadId = (string) Str::uuid();
        $tmpDir = storage_path('app/tmp/video/'.$productId);
        @mkdir($tmpDir, 0777, true);
        // touch marker with meta (optional)
        @file_put_contents($tmpDir.DIRECTORY_SEPARATOR.$uploadId.'.json', json_encode([
            'product_id' => (int)$productId,
            'filename' => (string)$request->input('filename'),
            'created_at' => now()->toISOString(),
        ], JSON_UNESCAPED_SLASHES));
        return response()->json(['ok'=>true,'upload_id'=>$uploadId]);
    }

    public function videoChunk(Request $request, int $productId)
    {
        // Pre-validate diagnostics (to investigate validation.uploaded)
        $preChunk = $request->file('chunk');
        $preErrCode = ($preChunk instanceof UploadedFile) ? $preChunk->getError() : null;
        $preErrMsg = ($preChunk instanceof UploadedFile && method_exists($preChunk, 'getErrorMessage')) ? $preChunk->getErrorMessage() : null;
        $serverLimits = [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_file_uploads' => ini_get('max_file_uploads'),
            'file_uploads' => ini_get('file_uploads'),
            'memory_limit' => ini_get('memory_limit'),
        ];
        Log::info('videoChunk: pre-validate', [
            'product_id' => $productId,
            'upload_id' => (string)$request->input('upload_id'),
            'index' => (int)$request->input('index'),
            'count' => (int)$request->input('count'),
            'filename' => (string)$request->input('filename'),
            'content_length' => $request->headers->get('Content-Length'),
            'upload_err_code' => $preErrCode,
            'upload_err_message' => $preErrMsg,
            'server_limits' => $serverLimits,
        ]);

        try {
            $request->validate([
                'upload_id' => ['required','string'],
                'index' => ['required','integer','min:0'],
                'count' => ['required','integer','min:1'],
                'filename' => ['required','string','max:190'],
                'chunk' => ['required','file','max:20480'], // 20MB per chunk limit by server validation
            ]);
        } catch (ValidationException $e) {
            // Detailed logging for failed uploads
            Log::warning('videoChunk: validation failed', [
                'product_id' => $productId,
                'upload_id' => (string)$request->input('upload_id'),
                'index' => (int)$request->input('index'),
                'count' => (int)$request->input('count'),
                'filename' => (string)$request->input('filename'),
                'errors' => $e->errors(),
                'upload_err_code' => $preErrCode,
                'upload_err_message' => $preErrMsg,
                'server_limits' => $serverLimits,
            ]);
            return response()->json([
                'ok' => false,
                'message' => 'upload_validation_failed',
                'errors' => $e->errors(),
            ], 422);
        }

        $uploadId = (string)$request->input('upload_id');
        $index = (int)$request->input('index');
        $count = (int)$request->input('count');
        $filename = (string)$request->input('filename');
        /** @var UploadedFile $chunk */
        $chunk = $request->file('chunk');

        // Post-validate diagnostics
        $postErrCode = $chunk?->getError();
        $postErrMsg = method_exists($chunk, 'getErrorMessage') ? $chunk->getErrorMessage() : null;
        Log::info('videoChunk: received file', [
            'product_id' => $productId,
            'upload_id' => $uploadId,
            'index' => $index,
            'count' => $count,
            'filename' => $filename,
            'size_bytes' => $chunk?->getSize(),
            'mime' => $chunk?->getMimeType(),
            'is_valid' => $chunk?->isValid(),
            'upload_err_code' => $postErrCode,
            'upload_err_message' => $postErrMsg,
        ]);

        $tmpDir = storage_path('app/tmp/video/'.$productId);
        @mkdir($tmpDir, 0777, true);
        $tmpFile = $tmpDir.DIRECTORY_SEPARATOR.$uploadId.'.part';
        // append chunk contents to tmp file
        $in = fopen($chunk->getRealPath(), 'rb');
        $out = fopen($tmpFile, $index === 0 ? 'wb' : 'ab');
        stream_copy_to_stream($in, $out);
        fclose($in); fclose($out);

        $isLast = ($index + 1) >= $count;
        if ($isLast) {
            // finalize: move to public storage and dispatch job
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION) ?: 'mp4');
            $destName = (string) Str::uuid().'.'.$ext;
            $dir = 'uploads/products/'.$productId.'/video/orig';
            @mkdir(Storage::disk('public')->path($dir), 0777, true);
            $destAbs = Storage::disk('public')->path($dir.'/'.$destName);
            // move tmp to dest
            @rename($tmpFile, $destAbs);
            $rel = $dir.'/'.$destName;

            // Insert new video row
            $videoId = DB::table('product_videos')->insertGetId([
                'product_id' => (int)$productId,
                'title' => null,
                'source_path' => $rel,
                'hls_master_path' => null,
                'poster_path' => null,
                'size_bytes' => is_file($destAbs) ? filesize($destAbs) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // cleanup meta
            @unlink($tmpDir.DIRECTORY_SEPARATOR.$uploadId.'.json');
            // dispatch job
            \App\Jobs\TranscodeProductVideo::dispatch((int)$productId, (int)$videoId, $rel);

            return response()->json(['ok'=>true,'done'=>true,'video_id'=>(int)$videoId]);
        }

        return response()->json(['ok'=>true,'done'=>false,'received'=>$index]);
    }

    public function assignImage(Request $request, int $productId)
    {
        $data = $request->validate([
            'combination_id' => ['required','integer','exists:product_combinations,id'],
            'image_id' => ['nullable','integer'],
            'file_path' => ['nullable','string'],
        ]);
        // Ensure combination belongs to product
        $comb = DB::table('product_combinations')->where(['id'=>(int)$data['combination_id'],'product_id'=>$productId])->first();
        if (!$comb) { return response()->json(['ok'=>false,'message'=>'combination_not_found'], 404); }
        $combId = (int)$comb->id;
        // Resolve path either by image_id or by file_path
        $path = null;
        if (!empty($data['image_id'])) {
            $row = DB::table('product_images')->where(['id'=>(int)$data['image_id'],'product_id'=>$productId])->first();
            if ($row) { $path = $row->path; }
        }
        if (!$path && !empty($data['file_path'])) {
            $row = DB::table('product_images')->where(['product_id'=>$productId,'path'=>$data['file_path']])->first();
            if ($row) { $path = $row->path; }
        }
        if (!$path) {
            return response()->json(['ok'=>false,'message'=>'image_not_found'], 404);
        }

        // Avoid duplicate link
        $exists = DB::table('product_combination_images')->where(['combination_id'=>$combId,'path'=>$path])->exists();
        if (!$exists) {
            DB::table('product_combination_images')->insert([
                'combination_id' => $combId,
                'path' => $path,
                'is_main' => false,
                'sort' => 0,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
        // Build human-friendly label for this combination (SKU + attributes)
        $vals = DB::table('product_combination_values as pcv')
            ->join('product_attribute_values as pav','pav.id','=','pcv.attribute_value_id')
            ->join('product_attributes as pa','pa.id','=','pav.attribute_id')
            ->where('pcv.combination_id', $combId)
            ->orderBy('pa.sort')
            ->get(['pa.name as attr','pav.value as val']);
        $parts = [];
        foreach ($vals as $v) { $parts[] = ($v->attr.': '.$v->val); }
        $label = ($comb->sku ? ($comb->sku.' — ') : '').implode(' / ', $parts);
        return response()->json(['ok'=>true,'message'=>trans('admin.file_uploaded'),'combination_id'=>$combId,'file_path'=>$path,'label'=>$label]);
    }

    // Detach an image from a specific combination WITHOUT deleting physical file or product-level image
    public function detachImage(Request $request, int $productId)
    {
        $data = $request->validate([
            'combination_id' => ['required','integer','exists:product_combinations,id'],
            'combination_image_id' => ['nullable','integer','exists:product_combination_images,id'],
            'image_id' => ['nullable','integer'],
            'file_path' => ['nullable','string'],
        ]);
        // Ensure combination belongs to product
        $comb = DB::table('product_combinations')->where(['id'=>(int)$data['combination_id'],'product_id'=>$productId])->first();
        if (!$comb) { return response()->json(['ok'=>false,'message'=>'combination_not_found'], 404); }
        $combId = (int)$comb->id;

        // If explicit combination_image_id provided, delete that record directly
        if (!empty($data['combination_image_id'])) {
            $deleted = DB::table('product_combination_images')->where(['id'=>(int)$data['combination_image_id'],'combination_id'=>$combId])->delete();
            if ($deleted) { return response()->json(['ok'=>true]); }
            return response()->json(['ok'=>false,'message'=>'not_found'], 404);
        }

        // Resolve by path via product image id or file_path
        $path = null;
        if (!empty($data['image_id'])) {
            $row = DB::table('product_images')->where(['id'=>(int)$data['image_id'],'product_id'=>$productId])->first();
            if ($row) { $path = $row->path; }
        }
        if (!$path && !empty($data['file_path'])) {
            $path = (string)$data['file_path'];
        }
        if (!$path) { return response()->json(['ok'=>false,'message'=>'image_not_found'], 404); }

        $deleted = DB::table('product_combination_images')->where(['combination_id'=>$combId,'path'=>$path])->delete();
        if ($deleted) { return response()->json(['ok'=>true]); }
        return response()->json(['ok'=>false,'message'=>'not_found'], 404);
    }

    // image-uploader plugin endpoints (RouteHelper::ajaxFileRoutes)
    public function ajaxUpload(Request $request, $id, string $fieldName)
    {
        // Validate any incoming file fields as images (supports dynamic names like gallery__comb_123 and arrays)
        $rules = [];
        $filesArr = $request->allFiles();
        $addRules = function($value, $prefix) use (&$addRules, &$rules) {
            if (is_array($value)) {
                foreach ($value as $k => $v) { $addRules($v, $prefix.($prefix!==''?'.':'').$k); }
            } else if ($value instanceof \Illuminate\Http\UploadedFile) {
                $rules[$prefix] = ['file','image','max:5120'];
            }
        };
        foreach ($filesArr as $key => $value) { $addRules($value, $key); }
        if (!empty($rules)) { Validator::make($request->all(), $rules)->validate(); }

        // support multiple via gallery[0], gallery[1]...
        // Flatten all files recursively
        $files = [];
        $flatten = function($v) use (&$flatten, &$files) {
            if (is_array($v)) { foreach ($v as $vv) { $flatten($vv); } }
            else if ($v instanceof UploadedFile) { $files[] = $v; }
        };
        foreach ($request->allFiles() as $value) { $flatten($value); }
        $uploaded = [];
        foreach ($files as $file) {
            $ext = strtolower($file->getClientOriginalExtension());
            $name = Str::uuid().'.'.$ext;
            $productId = (int)$id;
            $combId = null;
            if (preg_match('/^gallery__(?:comb_(\d+))$/', $fieldName, $m)) { $combId = (int)($m[1] ?? 0) ?: null; }
            $dir = 'uploads/products/'.$productId.($combId ? '/combinations/'.$combId : '/product');
            $path = $file->storeAs($dir.'/orig', $name, 'public');
            // Generate AVIF variant and cleanup legacy code_* files
            ConvertImageToAvif::dispatch($path);
            CleanupLegacyImageSizes::dispatch($path);
            if ($combId) {
                DB::table('product_combination_images')->insert(['combination_id'=>$combId,'path'=>$dir.'/orig/'.$name,'is_main'=>false,'sort'=>0,'created_at'=>now(),'updated_at'=>now()]);
            } else {
                DB::table('product_images')->insert(['product_id'=>$productId,'path'=>$dir.'/orig/'.$name,'is_main'=>false,'sort'=>0,'created_at'=>now(),'updated_at'=>now()]);
            }
            $uploaded[] = Storage::disk('public')->url($dir.'/orig/'.$name);
        }
        return response()->json(['success'=>true,'message'=>trans('admin.file_uploaded') ?? 'آپلود انجام شد','uploaded_files'=>$uploaded,'file_info'=>array_map(fn($u)=>['url'=>$u], $uploaded),'field'=>$fieldName]);
    }

    public function ajaxDeleteFile(Request $request, $id, $fieldName)
    {
        $productId = (int)$id;
        $path = (string)$request->query('file_path','');
        if (!$path) return response()->json(['success'=>false,'message'=>'file_path empty'], 400);
        // convert public URL to storage path if needed
        if (str_starts_with($path, url('/storage'))) {
            $rel = str_replace(url('/storage').'/', '', $path);
        } else if (str_starts_with($path, '/storage/')) {
            $rel = substr($path, 9);
        } else {
            $rel = $path; // assume relative storage path
        }
        $scope = str_contains($rel, '/combinations/') ? 'combination' : 'product';
        $dir = Str::beforeLast($rel, '/orig/');
        $name = Str::afterLast($rel, '/orig/');
        // Delete original
        Storage::disk('public')->delete($rel);
        // Delete AVIF variant if exists
        $avifRel = $dir.'/avif/'.pathinfo($name, PATHINFO_FILENAME).'.avif';
        Storage::disk('public')->delete($avifRel);
        // Cleanup any legacy code_* variants
        $files = Storage::disk('public')->files($dir);
        $suffix = '_'.$name;
        foreach ($files as $f) { if (str_ends_with($f, $suffix)) { Storage::disk('public')->delete($f); } }
        if ($scope==='product') {
            DB::table('product_images')->where(['product_id'=>$productId,'path'=>$rel])->delete();
        } else {
            DB::table('product_combination_images')->where(['path'=>$rel])->delete();
        }
        return response()->json(['success'=>true,'message'=>trans('admin.file_deleted') ?? 'حذف انجام شد']);
    }

    public function updateBasicAjax(Request $request, int $productId)
    {
        $fields = $request->validate([
            'name' => ['required','string','max:190'],
            'slug' => ['required','string','max:190'],
            'sku'  => ['nullable','string','max:190'],
            'category_id' => ['nullable','integer'],
            'active' => ['nullable','boolean'],
            'point_per_unit' => ['nullable','integer','min:0'],
            'cost_cny' => ['nullable','numeric'],
            'sale_price_cny' => ['nullable','numeric'],
            'discount_type' => ['nullable','in:percent,amount'],
            'discount_value' => ['nullable','numeric','min:0'],
            'stock_qty' => ['nullable','integer','min:0'],
            'short_desc' => ['nullable','string'],
            'description' => ['nullable','string'],
        ], $this->validationMessages(), $this->validationAttributes());
        if (($fields['discount_type'] ?? null) === 'percent' && isset($fields['discount_value'])) {
            $fields['discount_value'] = max(0, min(100, (float)$fields['discount_value']));
        }
        $fields['active'] = !empty($fields['active']);
        DB::table('products')->where('id', (int)$productId)->update(array_merge($fields, ['updated_at'=>now()]));
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
        // Lazy simple options; replace with cache if needed
        return \DB::table('categories')->orderBy('name')->pluck('name','id')->toArray();
    }

    public function saveCombinations(Request $request, int $productId)
    {
        // expects attributes_json and combinations_json from UI state
        $request->validate([
            'attributes_json' => ['required','string'],
            'combinations_json' => ['required','string'],
        ]);
        $this->saveAttributesAndCombinations($productId, $request);

        // Return fresh data same as edit()
        $attrRows = \DB::table('product_attributes')->where('product_id', (int)$productId)->orderBy('sort')->get();
        $attributes = $attrRows->map(function($a){
            $values = DB::table('product_attribute_values')->where('attribute_id', $a->id)->orderBy('sort')->get()->map(function($v){
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

        $combRows = \DB::table('product_combinations')->where('product_id', (int)$productId)->orderBy('id','desc')->get();
        $combinations = $combRows->map(function($c){
            $valIds = DB::table('product_combination_values')->where('combination_id', $c->id)->orderBy('id')->pluck('attribute_value_id')->toArray();
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

        $imgRow = \DB::table('product_images')
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
        $features = DB::table('product_features as pf')
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
        $rate = \DB::table('currency_rates')->where('base_code','CNY')->where('quote_code','IRT')->orderByDesc('effective_at')->value('sell_rate');
        if (!$rate) { return ''; }
        $cny = $saleCny ?? $costCny;
        if ($cny === null) { return ''; }
        $irt = (float)$cny * (float)$rate;
        // Apply discount before converting
        $dt = null; $dv = null;
        if (is_object($row)) { $dt = $row->discount_type ?? null; $dv = $row->discount_value ?? null; }
        if (is_array($row)) { $dt = $dt ?? ($row['discount_type'] ?? null); $dv = $dv ?? ($row['discount_value'] ?? null); }
        $finalCny = \RMS\Shop\Services\PricingService::applyDiscount($cny, $dt, $dv);
        $irt = (float)$finalCny * (float)$rate;
        return number_format($irt, 0, '.', ',');
    }
}
