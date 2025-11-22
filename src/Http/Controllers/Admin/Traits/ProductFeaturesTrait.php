<?php

namespace RMS\Shop\Http\Controllers\Admin\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RMS\Shop\Models\ProductFeature;
use RMS\Shop\Models\ProductFeatureCategory;
use RMS\Shop\Models\ProductFeatureDefinition;
use RMS\Shop\Models\ProductFeatureDefinitionValue;

trait ProductFeaturesTrait
{
    // Save product features (key/value) via AJAX with categories
    public function saveFeatures(Request $request, int $productId)
    {
        $data = $request->validate([
            'categories' => ['array'],
            'categories.*.category_id' => ['nullable','integer'],
            'categories.*.category_name' => ['nullable','string','max:190'],
            'categories.*.features' => ['array'],
            'categories.*.features.*.feature_id' => ['nullable', 'integer'],
            'categories.*.features.*.value_id' => ['nullable', 'integer'],
            'categories.*.features.*.name' => ['required','string','max:190'],
            'categories.*.features.*.value' => ['nullable','string','max:2000'],
            'categories.*.features.*.sort' => ['nullable','integer','min:0'],
        ]);

        // Validate that IDs exist if provided
        foreach ($data['categories'] ?? [] as $catIndex => $cat) {
            if (!empty($cat['category_id']) && !ProductFeatureCategory::where('id', $cat['category_id'])->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "categories.{$catIndex}.category_id" => ['دسته‌بندی انتخاب شده معتبر نیست'],
                ]);
            }

            foreach ($cat['features'] ?? [] as $featIndex => $feature) {
                if (!empty($feature['feature_id']) && !ProductFeatureDefinition::where('id', $feature['feature_id'])->exists()) {
                    // Reset invalid feature_id
                    $data['categories'][$catIndex]['features'][$featIndex]['feature_id'] = null;
                }
                if (!empty($feature['value_id']) && !ProductFeatureDefinitionValue::where('id', $feature['value_id'])->exists()) {
                    // Reset invalid value_id
                    $data['categories'][$catIndex]['features'][$featIndex]['value_id'] = null;
                }
            }
        }
        $categories = $data['categories'] ?? [];
        DB::transaction(function() use ($productId, $categories){
            ProductFeature::query()->where('product_id',(int)$productId)->delete();
            foreach ($categories as $catIndex => $cat) {
                $categoryId = $this->resolveCategoryId(
                    $cat['category_id'] ?? null,
                    $cat['category_name'] ?? null
                );

                foreach (($cat['features'] ?? []) as $i => $feature) {
                    $featureName = (string) $feature['name'];
                    $featureValue = (string) ($feature['value'] ?? '');

                    $featureDefinitionId = $this->resolveFeatureDefinitionId(
                        $feature['feature_id'] ?? null,
                        $featureName,
                        $categoryId
                    );

                    $valueDefinitionId = $this->resolveFeatureValueId(
                        $featureDefinitionId,
                        $feature['value_id'] ?? null,
                        $featureValue
                    );

                    ProductFeature::create([
                        'product_id' => (int)$productId,
                        'category_id' => $categoryId,
                        'feature_definition_id' => $featureDefinitionId,
                        'feature_value_id' => $valueDefinitionId,
                        'name' => $featureName,
                        'value' => $featureValue,
                        'sort' => isset($feature['sort']) ? (int)$feature['sort'] : ($i + 1) + ($catIndex * 100),
                    ]);

                    $this->bumpUsageCounters($featureDefinitionId, $valueDefinitionId);
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

        $category = ProductFeatureCategory::create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name'] . '-' . time()),
            'icon' => $data['icon'] ?? 'ph-tag',
            'sort' => 99,
            'active' => true,
        ]);

        return response()->json([
            'ok' => true,
            'category' => [
                'id' => (int)$category->id,
                'name' => $category->name,
                'icon' => $category->icon ?? 'ph-tag',
            ]
        ]);
    }

    protected function resolveCategoryId(?int $categoryId, ?string $categoryName): ?int
    {
        if ($categoryId) {
            return $categoryId;
        }

        $categoryName = trim((string) $categoryName);
        if ($categoryName === '') {
            return null;
        }

        $existing = ProductFeatureCategory::query()
            ->where('name', $categoryName)
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $slugBase = Str::slug($categoryName);
        $slug = $slugBase ?: Str::slug($categoryName . '-' . now()->timestamp);
        $counter = 1;
        while (ProductFeatureCategory::where('slug', $slug)->exists()) {
            $slug = $slugBase . '-' . $counter++;
        }

        $category = ProductFeatureCategory::create([
            'name' => $categoryName,
            'slug' => $slug,
            'icon' => 'ph-tag',
            'active' => true,
        ]);

        return (int) $category->id;
    }

    protected function resolveFeatureDefinitionId(?int $featureId, string $featureName, ?int $categoryId): ?int
    {
        $featureName = trim($featureName);

        // اگر featureId ارسال شده، چک کن وجود دارد یا نه
        if ($featureId) {
            $existing = ProductFeatureDefinition::find($featureId);
            if ($existing) {
                return $featureId;
            }
            // اگر وجود ندارد، آن را نادیده بگیر و به نام نگاه کن
        }

        if ($featureName === '') {
            return null;
        }

        $query = ProductFeatureDefinition::query()
            ->where('name', $featureName);

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $existing = $query->first();
        if ($existing) {
            return (int) $existing->id;
        }

        $slugBase = Str::slug($featureName);
        $slug = $slugBase ?: Str::slug($featureName . '-' . now()->timestamp);
        $counter = 1;
        while (ProductFeatureDefinition::where('slug', $slug)->exists()) {
            $slug = $slugBase . '-' . $counter++;
        }

        $definition = ProductFeatureDefinition::create([
            'category_id' => $categoryId,
            'name' => $featureName,
            'slug' => $slug,
            'active' => true,
        ]);

        return (int) $definition->id;
    }

    protected function resolveFeatureValueId(?int $valueId, ?int $featureId, string $value): ?int
    {
        $value = trim($value);

        // اگر valueId ارسال شده، چک کن وجود دارد یا نه
        if ($valueId) {
            $existing = ProductFeatureDefinitionValue::find($valueId);
            if ($existing) {
                return $valueId;
            }
            // اگر وجود ندارد، آن را نادیده بگیر و به مقدار نگاه کن
        }

        if (!$featureId || $value === '') {
            return null;
        }

        $existing = ProductFeatureDefinitionValue::query()
            ->where('feature_id', $featureId)
            ->where('value', $value)
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $slugBase = Str::slug($value);
        $slug = $slugBase ?: Str::slug($value . '-' . now()->timestamp);
        $counter = 1;
        while (
            ProductFeatureDefinitionValue::where('feature_id', $featureId)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $slugBase . '-' . $counter++;
        }

        $definitionValue = ProductFeatureDefinitionValue::create([
            'feature_id' => $featureId,
            'value' => $value,
            'slug' => $slug,
            'active' => true,
        ]);

        return (int) $definitionValue->id;
    }

    protected function bumpUsageCounters(?int $featureDefinitionId, ?int $valueDefinitionId): void
    {
        if ($featureDefinitionId) {
            ProductFeatureDefinition::whereKey($featureDefinitionId)->increment('usage_count');
        }
        if ($valueDefinitionId) {
            ProductFeatureDefinitionValue::whereKey($valueDefinitionId)->increment('usage_count');
        }
    }
}
