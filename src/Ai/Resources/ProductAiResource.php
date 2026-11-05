<?php

namespace RMS\Shop\Ai\Resources;

use Illuminate\Database\Eloquent\Model;
use RMS\Shop\Ai\FeatureMerge;
use RMS\Shop\Models\Product;
use RMS\Shop\Models\ProductFeatureCategory;
use RMS\Shop\Models\ProductFeatureDefinition;
use RMS\Shop\Services\ProductFeaturesService;

class ProductAiResource extends AiResource
{
    public function key(): string
    {
        return 'product';
    }

    public function label(): string
    {
        return 'محصول';
    }

    protected function modelClass(): string
    {
        return Product::class;
    }

    public function readPath(): string
    {
        return 'products/{id}';
    }

    public function fields(): array
    {
        return [
            'name' => ['label' => 'نام', 'type' => 'string', 'max' => 255],
            'slug' => ['label' => 'آدرس (slug)', 'type' => 'slug', 'max' => 190],
            'short_desc' => ['label' => 'توضیح کوتاه', 'type' => 'html', 'max' => 5000],
            'description' => ['label' => 'توضیحات', 'type' => 'html', 'max' => 60000],
            'meta_title' => ['label' => 'عنوان سئو', 'type' => 'string', 'max' => 120],
            'meta_description' => ['label' => 'توضیح متا', 'type' => 'text', 'max' => 320],
            'focus_keyword' => ['label' => 'کلمهٔ کلیدی', 'type' => 'string', 'max' => 100],
            'features' => ['label' => 'ویژگی‌ها', 'type' => 'features'],
        ];
    }

    public function hints(): array
    {
        $hints = [
            'features' => 'فهرست گروه‌ها: [{"group":"ابعاد","features":[{"name":"ارتفاع","value":"35 میلی‌متر"}]}]. '
                .'ویژگی‌های فعلی محصول حفظ می‌شوند و فقط موردهای جدید (نام تازه) اضافه می‌شوند. '
                .'فقط اطلاعات ثابت (ابعاد، وزن، جنس…) بنویس؛ اگر مطمئن نیستی ننویس. نام گروه و ویژگی را از فهرست زیر انتخاب کن.',
        ];

        try {
            $hints['feature_groups'] = ProductFeatureCategory::query()->orderBy('sort')->limit(60)->pluck('name')->all();
            $hints['common_feature_names'] = ProductFeatureDefinition::query()->orderByDesc('usage_count')->limit(80)->pluck('name')->all();
        } catch (\Throwable) {
            // Feature tables not migrated yet.
        }

        return $hints;
    }

    public function current(Model $model, array $fields): array
    {
        $out = parent::current($model, array_diff($fields, ['features']));
        if (in_array('features', $fields, true)) {
            $out['features'] = FeatureMerge::current((int) $model->getKey());
        }

        return $out;
    }

    /** Features already on the product are no change: keep only the new ones. */
    public function reduce(?Model $model, string $field, mixed $cleaned): mixed
    {
        if ($field !== 'features') {
            return parent::reduce($model, $field, $cleaned);
        }
        if ($model === null) {
            return $cleaned;
        }

        $have = FeatureMerge::currentNames((int) $model->getKey());
        $groups = [];
        foreach ((array) $cleaned as $group) {
            $features = array_values(array_filter(
                (array) $group['features'],
                fn ($f) => ! in_array(mb_strtolower($f['name']), $have, true)
            ));
            if ($features !== []) {
                $groups[] = ['group' => $group['group'], 'features' => $features];
            }
        }

        return $groups === [] ? null : $groups;
    }

    public function apply(Model $model, array $changes): void
    {
        $features = $changes['features'] ?? null;
        unset($changes['features']);

        if ($changes !== []) {
            parent::apply($model, $changes);
        }
        if ($features) {
            app(ProductFeaturesService::class)->saveFeatures(
                (int) $model->getKey(),
                ['categories' => FeatureMerge::merge((int) $model->getKey(), $features)]
            );
        }
    }

    public function adminUrl(Model $model): ?string
    {
        return route('admin.shop.products.edit', $model->getKey());
    }
}
