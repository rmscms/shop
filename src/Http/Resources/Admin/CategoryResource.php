<?php

namespace RMS\Shop\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            'name' => (string) $this->name,
            'slug' => (string) $this->slug,
            'parent_id' => $this->parent_id ? (int) $this->parent_id : null,
            'parent' => $this->whenLoaded('parent', function () use ($request) {
                return $this->parent ? [
                    'id' => (int) $this->parent->id,
                    'name' => (string) $this->parent->name,
                    'slug' => (string) $this->parent->slug,
                ] : null;
            }),
            'active' => (bool) $this->active,
            'sort' => (int) ($this->sort ?? 0),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
