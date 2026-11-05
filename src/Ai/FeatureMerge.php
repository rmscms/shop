<?php

namespace RMS\Shop\Ai;

use RMS\Shop\Models\ProductFeature;
use RMS\Shop\Models\ProductFeatureCategory;

/**
 * Product features for AI work: read what a product has and merge suggested features in
 * without losing the ones it already has (ProductFeaturesService::saveFeatures replaces everything).
 */
class FeatureMerge
{
    /**
     * Current features as [{"group": name, "features": [{"name","value"}]}].
     *
     * @return array<int, array{group: string, features: array<int, array{name: string, value: string}>}>
     */
    public static function current(int $productId): array
    {
        $rows = ProductFeature::query()->where('product_id', $productId)->orderBy('sort')->get();
        $names = ProductFeatureCategory::query()->whereIn('id', $rows->pluck('category_id')->filter()->unique())->pluck('name', 'id');

        $groups = [];
        foreach ($rows as $row) {
            $key = (string) ($row->category_id ?: 0);
            $groups[$key] ??= ['group' => (string) ($names[$row->category_id] ?? ''), 'features' => []];
            $groups[$key]['features'][] = ['name' => (string) $row->name, 'value' => (string) $row->value];
        }

        return array_values($groups);
    }

    /** Lower-cased names of the features a product already has. */
    public static function currentNames(int $productId): array
    {
        return ProductFeature::query()->where('product_id', $productId)->pluck('name')
            ->map(fn ($n) => mb_strtolower(trim((string) $n)))->all();
    }

    /**
     * Keep the product's current features and add the suggested ones that are missing.
     *
     * @param  array<int, array<string, mixed>>  $groups  [{"group": "...", "features": [{"name","value"}]}]
     * @return array<int, array<string, mixed>>  the "categories" array ProductFeaturesService::saveFeatures expects
     */
    public static function merge(int $productId, array $groups): array
    {
        $existing = ProductFeature::query()
            ->where('product_id', $productId)
            ->orderBy('sort')
            ->get();

        $byCategory = [];
        $names = [];
        foreach ($existing as $feature) {
            $key = 'id:'.(int) $feature->category_id;
            $byCategory[$key] ??= ['category_id' => $feature->category_id ?: null, 'features' => []];
            $byCategory[$key]['features'][] = [
                'feature_id' => $feature->feature_definition_id,
                'value_id' => $feature->feature_value_id,
                'name' => $feature->name,
                'value' => $feature->value,
            ];
            $names[mb_strtolower(trim((string) $feature->name))] = true;
        }

        foreach ($groups as $group) {
            $groupName = trim((string) ($group['group'] ?? ''));
            $category = $groupName !== ''
                ? ProductFeatureCategory::query()->where('name', $groupName)->first()
                : null;
            $key = $category ? 'id:'.$category->id : 'name:'.$groupName;
            $byCategory[$key] ??= $category
                ? ['category_id' => $category->id, 'features' => []]
                : ['category_name' => $groupName ?: 'مشخصات', 'features' => []];

            foreach ((array) ($group['features'] ?? []) as $feature) {
                $name = trim((string) ($feature['name'] ?? ''));
                $value = trim((string) ($feature['value'] ?? ''));
                if ($name === '' || $value === '' || isset($names[mb_strtolower($name)])) {
                    continue;
                }
                $names[mb_strtolower($name)] = true;
                $byCategory[$key]['features'][] = ['name' => $name, 'value' => $value];
            }
        }

        return array_values(array_filter($byCategory, fn ($group) => $group['features'] !== []));
    }
}
