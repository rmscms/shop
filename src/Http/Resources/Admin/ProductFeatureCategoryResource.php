<?php

namespace RMS\Shop\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductFeatureCategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        if (!$this->resource) {
            return [];
        }

        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'slug' => (string) ($this->slug ?? ''),
            'icon' => (string) ($this->icon ?? 'ph-tag'),
            'description' => $this->description,
            'sort' => (int) ($this->sort ?? 0),
            'active' => (bool) $this->active,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
