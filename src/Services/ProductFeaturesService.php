<?php

namespace RMS\Shop\Services;

use RMS\Shop\Models\ProductFeature;
use RMS\Shop\Models\ProductFeatureCategory;
use RMS\Shop\Models\ProductFeatureDefinition;
use RMS\Shop\Models\ProductFeatureDefinitionValue;
use Illuminate\Support\Facades\DB;

class ProductFeaturesService
{
    /**
     * Save product features with categories
     */
    public function saveFeatures(int $productId, array $featuresData): void
    {
        $categories = $featuresData['categories'] ?? [];

        DB::transaction(function() use ($productId, $categories) {
            ProductFeature::query()->where('product_id', (int)$productId)->delete();
            
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
                        $feature['value_id'] ?? null,
                        $featureDefinitionId,
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
    }

    /**
     * Create new feature category
     */
    public function createFeatureCategory(array $data): ProductFeatureCategory
    {
        return ProductFeatureCategory::create([
            'name' => $data['name'],
            'slug' => \Illuminate\Support\Str::slug($data['name'] . '-' . time()),
            'icon' => $data['icon'] ?? 'ph-tag',
            'sort' => 99,
            'active' => true,
        ]);
    }

    /**
     * Resolve category ID - create if needed
     */
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

        $slugBase = \Illuminate\Support\Str::slug($categoryName);
        $slug = $slugBase ?: \Illuminate\Support\Str::slug($categoryName . '-' . now()->timestamp);
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

    /**
     * Resolve feature definition ID - create if needed
     */
    protected function resolveFeatureDefinitionId(?int $featureId, string $featureName, ?int $categoryId): ?int
    {
        $featureName = trim($featureName);

        // اگر featureId ارسال شده، چک کن وجود دارد یا نه
        if ($featureId) {
            $existing = ProductFeatureDefinition::find($featureId);
            if ($existing) {
                return $featureId;
            }
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

        $slugBase = \Illuminate\Support\Str::slug($featureName);
        $slug = $slugBase ?: \Illuminate\Support\Str::slug($featureName . '-' . now()->timestamp);
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

    /**
     * Resolve feature value ID - create if needed
     */
    protected function resolveFeatureValueId(?int $valueId, ?int $featureDefinitionId, string $value): ?int
    {
        $value = trim($value);

        // اگر valueId ارسال شده، چک کن وجود دارد یا نه
        if ($valueId) {
            $existing = ProductFeatureDefinitionValue::find($valueId);
            if ($existing) {
                return $valueId;
            }
        }

        if (!$featureDefinitionId || $value === '') {
            return null;
        }

        $existing = ProductFeatureDefinitionValue::query()
            ->where('feature_id', $featureDefinitionId)
            ->where('value', $value)
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $slugBase = \Illuminate\Support\Str::slug($value);
        $slug = $slugBase ?: \Illuminate\Support\Str::slug($value . '-' . now()->timestamp);
        $counter = 1;
        while (
            ProductFeatureDefinitionValue::where('feature_id', $featureDefinitionId)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $slugBase . '-' . $counter++;
        }

        $definitionValue = ProductFeatureDefinitionValue::create([
            'feature_id' => $featureDefinitionId,
            'value' => $value,
            'slug' => $slug,
            'active' => true,
        ]);

        return (int) $definitionValue->id;
    }

    /**
     * Bump usage counters for feature definitions and values
     */
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
