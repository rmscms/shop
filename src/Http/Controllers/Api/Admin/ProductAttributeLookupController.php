<?php

namespace RMS\Shop\Http\Controllers\Api\Admin;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RMS\Shop\Models\ProductAttributeDefinition;
use RMS\Shop\Models\ProductAttributeDefinitionValue;
use Illuminate\Support\Str;

class ProductAttributeLookupController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/admin/shop/product-attributes/definitions/search",
     *     tags={"Admin - Products"},
     *     summary="Search attribute definitions",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="q", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Response(
     *         response=200,
     *         description="Attribute definitions",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function definitions(Request $request)
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

        $data = $items->map(fn ($item) => [
            'id' => (int) $item->id,
            'name' => (string) $item->name,
            'type' => (string) $item->type,
            'ui' => (string) $item->ui,
        ]);

        return $this->apiSuccess($data);
    }

    /**
     * @OA\Get(
     *     path="/admin/shop/product-attributes/values/search",
     *     tags={"Admin - Products"},
     *     summary="Search attribute definition values",
     *     security={{"admin":{}}},
     *     @OA\Parameter(name="definition_id", in="query", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="q", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="limit", in="query", @OA\Schema(type="integer", default=10)),
     *     @OA\Response(
     *         response=200,
     *         description="Attribute definition values",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function values(Request $request)
    {
        $definitionId = (int) $request->integer('definition_id');
        if ($definitionId <= 0) {
            return $this->apiError(['definition_id' => ['definition_id is required']], 422);
        }

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

        $data = $items->map(fn ($item) => [
            'id' => (int) $item->id,
            'value' => (string) $item->value,
            'color' => $item->color,
            'image_path' => $item->image_path,
        ]);

        return $this->apiSuccess($data);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/product-attributes/definitions",
     *     tags={"Admin - Products"},
     *     summary="Create attribute definition",
     *     security={{"admin":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Color"),
     *             @OA\Property(property="type", type="string", example="text", nullable=true),
     *             @OA\Property(property="ui", type="string", example="pill", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Attribute definition created or found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function storeDefinition(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'type' => ['nullable', 'string', 'max:50'],
            'ui' => ['nullable', 'string', 'max:50'],
        ]);

        $name = trim($data['name']);
        $type = $data['type'] ?? 'text';
        $ui = $data['ui'] ?? 'pill';

        $existingDefinition = ProductAttributeDefinition::where('name', $name)
            ->where('type', $type)
            ->where('ui', $ui)
            ->first();

        if ($existingDefinition) {
            return $this->apiSuccess([
                'id' => (int) $existingDefinition->id,
                'name' => (string) $existingDefinition->name,
                'type' => (string) $existingDefinition->type,
                'ui' => (string) $existingDefinition->ui,
            ]);
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

        return $this->apiSuccess([
            'id' => (int) $definition->id,
            'name' => (string) $definition->name,
            'type' => (string) $definition->type,
            'ui' => (string) $definition->ui,
        ], [], 201);
    }

    /**
     * @OA\Post(
     *     path="/admin/shop/product-attributes/values",
     *     tags={"Admin - Products"},
     *     summary="Create attribute definition value",
     *     security={{"admin":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"definition_id", "value"},
     *             @OA\Property(property="definition_id", type="integer", example=1),
     *             @OA\Property(property="value", type="string", example="Red"),
     *             @OA\Property(property="color", type="string", nullable=true),
     *             @OA\Property(property="image_path", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Attribute definition value created",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function storeValue(Request $request)
    {
        $data = $request->validate([
            'definition_id' => ['required', 'integer', 'exists:product_attribute_definitions,id'],
            'value' => ['required', 'string', 'max:190'],
            'color' => ['nullable', 'string', 'max:50'],
            'image_path' => ['nullable', 'string', 'max:500'],
        ]);

        $valueText = trim($data['value']);
        $slugBase = Str::slug($valueText);
        $slug = $slugBase ?: Str::slug($valueText . '-' . now()->timestamp);
        $counter = 1;
        
        // Ensure unique slug
        while (ProductAttributeDefinitionValue::where('attribute_definition_id', $data['definition_id'])
                ->where('slug', $slug)
                ->exists()) {
            $slug = $slugBase . '-' . $counter++;
        }
        
        $value = ProductAttributeDefinitionValue::firstOrCreate(
            [
                'attribute_definition_id' => $data['definition_id'],
                'value' => $valueText,
            ],
            [
                'slug' => $slug,
                'active' => true,
                'color' => $data['color'] ?? null,
                'image_path' => $data['image_path'] ?? null,
            ]
        );

        return $this->apiSuccess([
            'id' => (int) $value->id,
            'value' => (string) $value->value,
            'color' => $value->color,
            'image_path' => $value->image_path,
        ], [], 201);
    }
}
