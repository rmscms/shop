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
        app(\RMS\Shop\Services\ProductFeaturesService::class)->saveFeatures($productId, $data);
        return response()->json(['ok'=>true]);
    }

    // Create new feature category via AJAX
    public function createFeatureCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:190'],
            'icon' => ['nullable','string','max:50'],
        ]);

        $category = app(\RMS\Shop\Services\ProductFeaturesService::class)->createFeatureCategory($data);

        return response()->json([
            'ok' => true,
            'category' => [
                'id' => (int)$category->id,
                'name' => $category->name,
                'icon' => $category->icon ?? 'ph-tag',
            ]
        ]);
    }

}
