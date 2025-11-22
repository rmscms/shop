<?php

namespace RMS\Shop\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use RMS\Shop\Models\ProductFeatureCategory;
use RMS\Shop\Models\ProductFeatureDefinition;
use RMS\Shop\Models\ProductFeatureDefinitionValue;

class ProductFeatureLookupController extends Controller
{
    public function categories(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $limit = (int) min(20, max(1, $request->integer('limit', 10)));

        $items = ProductFeatureCategory::query()
            ->when(Str::length($query) >= 1, fn ($q) => $q->where('name', 'like', "%{$query}%"))
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'icon']);

        return response()->json([
            'data' => $items->map(fn ($item) => [
                'id' => (int) $item->id,
                'name' => (string) $item->name,
                'icon' => (string) ($item->icon ?? 'ph-info'),
            ]),
        ]);
    }

    public function features(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $limit = (int) min(20, max(1, $request->integer('limit', 10)));
        $categoryId = $request->integer('category_id');

        $builder = ProductFeatureDefinition::query()
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when(Str::length($query) >= 1, fn ($q) => $q->where('name', 'like', "%{$query}%"))
            ->where('active', true)
            ->orderByDesc('usage_count')
            ->orderBy('name')
            ->limit($limit);

        $items = $builder->get(['id', 'category_id', 'name']);

        return response()->json([
            'data' => $items->map(fn ($item) => [
                'id' => (int) $item->id,
                'name' => (string) $item->name,
                'category_id' => $item->category_id ? (int) $item->category_id : null,
            ]),
        ]);
    }

    public function values(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $limit = (int) min(20, max(1, $request->integer('limit', 10)));
        $featureId = (int) $request->integer('feature_id');

        abort_if($featureId <= 0, 422, 'feature_id is required');

        $items = ProductFeatureDefinitionValue::query()
            ->where('feature_id', $featureId)
            ->when(Str::length($query) >= 1, fn ($q) => $q->where('value', 'like', "%{$query}%"))
            ->where('active', true)
            ->orderByDesc('usage_count')
            ->orderBy('value')
            ->limit($limit)
            ->get(['id', 'value']);

        return response()->json([
            'data' => $items->map(fn ($item) => [
                'id' => (int) $item->id,
                'value' => (string) $item->value,
            ]),
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'icon' => ['nullable', 'string', 'max:50'],
        ]);

        $slugBase = Str::slug($data['name']);
        $slug = $slugBase;
        $counter = 1;
        while (ProductFeatureCategory::where('slug', $slug)->exists()) {
            $slug = $slugBase . '-' . $counter++;
        }

        $category = ProductFeatureCategory::create([
            'name' => $data['name'],
            'slug' => $slug,
            'icon' => $data['icon'] ?? 'ph-info',
            'active' => true,
        ]);

        return response()->json([
            'ok' => true,
            'category' => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'icon' => (string) $category->icon,
            ],
        ], 201);
    }

    public function storeFeature(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'category_id' => ['nullable', 'integer', 'exists:product_feature_categories,id'],
        ]);

        $slugBase = Str::slug($data['name']);
        $slug = $slugBase;
        $counter = 1;
        while (ProductFeatureDefinition::where('slug', $slug)->exists()) {
            $slug = $slugBase . '-' . $counter++;
        }

        $definition = ProductFeatureDefinition::create([
            'category_id' => $data['category_id'] ?? null,
            'name' => $data['name'],
            'slug' => $slug,
            'active' => true,
        ]);

        return response()->json([
            'ok' => true,
            'feature' => [
                'id' => (int) $definition->id,
                'name' => (string) $definition->name,
                'category_id' => $definition->category_id ? (int) $definition->category_id : null,
            ],
        ], 201);
    }

    public function storeValue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'feature_id' => ['required', 'integer', 'exists:product_feature_definitions,id'],
            'value' => ['required', 'string', 'max:500'],
        ]);

        $slugBase = Str::slug($data['value']);
        $slug = $slugBase;
        $counter = 1;
        while (
            ProductFeatureDefinitionValue::where('feature_id', (int) $data['feature_id'])
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $slugBase . '-' . $counter++;
        }

        $definitionValue = ProductFeatureDefinitionValue::create([
            'feature_id' => (int) $data['feature_id'],
            'value' => $data['value'],
            'slug' => $slug,
            'active' => true,
        ]);

        return response()->json([
            'ok' => true,
            'value' => [
                'id' => (int) $definitionValue->id,
                'feature_id' => (int) $definitionValue->feature_id,
                'value' => (string) $definitionValue->value,
            ],
        ], 201);
    }
}

