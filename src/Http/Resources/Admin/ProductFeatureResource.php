<?php

namespace RMS\Shop\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductFeatureResource extends JsonResource
{
    /**
     * @param \Illuminate\Http\Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        if (!$this->resource) {
            return [];
        }

        return [
            'id' => (int) $this->id,
            'product_id' => (int) $this->product_id,
            'category_id' => $this->category_id ? (int) $this->category_id : null,
            'category' => $this->whenLoaded('category', function () {
                return $this->category ? [
                    'id' => (int) $this->category->id,
                    'name' => (string) $this->category->name,
                    'icon' => (string) ($this->category->icon ?? 'ph-tag'),
                ] : null;
            }),
            'feature_definition_id' => $this->feature_definition_id ? (int) $this->feature_definition_id : null,
            'feature_value_id' => $this->feature_value_id ? (int) $this->feature_value_id : null,
            'name' => (string) $this->name,
            'value' => $this->value,
            'sort' => (int) ($this->sort ?? 0),
            'definition' => $this->whenLoaded('definition', function () {
                return $this->definition ? [
                    'id' => (int) $this->definition->id,
                    'name' => (string) $this->definition->name,
                ] : null;
            }),
            'definition_value' => $this->whenLoaded('definitionValue', function () {
                return $this->definitionValue ? [
                    'id' => (int) $this->definitionValue->id,
                    'value' => (string) $this->definitionValue->value,
                ] : null;
            }),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
