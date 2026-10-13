<?php

namespace RMS\Shop\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
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
            'description' => $this->description,
            'is_active' => (bool) $this->is_active,
            'sort' => (int) ($this->sort ?? 0),
            'products_count' => $this->when(isset($this->products_count), fn() => (int) $this->products_count),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
