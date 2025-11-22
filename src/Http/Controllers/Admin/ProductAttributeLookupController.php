<?php

namespace RMS\Shop\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use RMS\Shop\Models\ProductAttributeDefinition;
use RMS\Shop\Models\ProductAttributeDefinitionValue;

class ProductAttributeLookupController extends Controller
{
    public function definitions(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $limit = min(20, max(1, (int) $request->integer('limit', 10)));

        $items = ProductAttributeDefinition::query()
            ->when(Str::length($term) >= 1, fn ($q) => $q->where('name', 'like', "%{$term}%"))
            ->where('active', true)
            ->orderByDesc('usage_count')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'type', 'ui']);

        return response()->json([
            'data' => $items->map(fn ($item) => [
                'id' => (int) $item->id,
                'name' => (string) $item->name,
                'type' => (string) $item->type,
                'ui' => (string) $item->ui,
            ]),
        ]);
    }

    public function values(Request $request): JsonResponse
    {
        $definitionId = (int) $request->integer('definition_id');
        abort_if($definitionId <= 0, 422, 'definition_id is required');

        $term = trim((string) $request->query('q', ''));
        $limit = min(20, max(1, (int) $request->integer('limit', 10)));

        $items = ProductAttributeDefinitionValue::query()
            ->where('attribute_definition_id', $definitionId)
            ->when(Str::length($term) >= 1, fn ($q) => $q->where('value', 'like', "%{$term}%"))
            ->where('active', true)
            ->orderByDesc('usage_count')
            ->orderBy('value')
            ->limit($limit)
            ->get(['id', 'value', 'color', 'image_path']);

        return response()->json([
            'data' => $items->map(fn ($item) => [
                'id' => (int) $item->id,
                'value' => (string) $item->value,
                'color' => $item->color,
                'image_path' => $item->image_path,
            ]),
        ]);
    }

    public function storeDefinition(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'type' => ['nullable', 'string', 'max:50'],
            'ui' => ['nullable', 'string', 'max:50'],
        ]);

        $name = trim($data['name']);
        $type = $data['type'] ?? 'text';
        $ui = $data['ui'] ?? 'pill';

        // Check if definition already exists with same name, type, and ui
        $existingDefinition = ProductAttributeDefinition::where('name', $name)
            ->where('type', $type)
            ->where('ui', $ui)
            ->first();

        if ($existingDefinition) {
            return response()->json([
                'ok' => true,
                'definition' => [
                    'id' => (int) $existingDefinition->id,
                    'name' => (string) $existingDefinition->name,
                    'type' => (string) $existingDefinition->type,
                    'ui' => (string) $existingDefinition->ui,
                ],
                'message' => 'تعریف موجود استفاده شد',
            ], 200);
        }

        $slugBase = Str::slug($name);
        $slug = $slugBase ?: Str::slug($name . '-' . now()->timestamp);
        $counter = 1;
        while (ProductAttributeDefinition::where('slug', $slug)->exists()) {
            $slug = $slugBase . '-' . $counter++;
        }

        $definition = ProductAttributeDefinition::create([
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
            'ui' => $ui,
            'active' => true,
        ]);

        return response()->json([
            'ok' => true,
            'definition' => [
                'id' => (int) $definition->id,
                'name' => (string) $definition->name,
                'type' => (string) $definition->type,
                'ui' => (string) $definition->ui,
            ],
        ], 201);
    }

    public function storeValue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'definition_id' => ['required', 'integer', 'exists:product_attribute_definitions,id'],
            'value' => ['required', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'max:50'],
            'image_path' => ['nullable', 'string', 'max:255'],
        ]);

        $definitionId = (int) $data['definition_id'];
        $valueStr = trim($data['value']);

        // Check if value already exists for this definition
        $existingValue = ProductAttributeDefinitionValue::where('attribute_definition_id', $definitionId)
            ->where('value', $valueStr)
            ->first();

        if ($existingValue) {
            return response()->json([
                'ok' => true,
                'value' => [
                    'id' => (int) $existingValue->id,
                    'value' => (string) $existingValue->value,
                    'color' => $existingValue->color,
                    'image_path' => $existingValue->image_path,
                ],
                'message' => 'مقدار موجود استفاده شد',
            ], 200);
        }

        $slugBase = Str::slug($valueStr);
        $slug = $slugBase ?: Str::slug($valueStr . '-' . now()->timestamp);
        $counter = 1;
        while (
            ProductAttributeDefinitionValue::where('attribute_definition_id', $definitionId)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $slugBase . '-' . $counter++;
        }

        $value = ProductAttributeDefinitionValue::create([
            'attribute_definition_id' => $definitionId,
            'value' => $valueStr,
            'slug' => $slug,
            'color' => $data['color'] ?? null,
            'image_path' => $data['image_path'] ?? null,
            'active' => true,
        ]);

        return response()->json([
            'ok' => true,
            'value' => [
                'id' => (int) $value->id,
                'value' => (string) $value->value,
                'color' => $value->color,
                'image_path' => $value->image_path,
            ],
        ], 201);
    }
}

