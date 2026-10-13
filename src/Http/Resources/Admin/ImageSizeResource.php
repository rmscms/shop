<?php

namespace RMS\Shop\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class ImageSizeResource extends JsonResource
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
            'name' => (string) ($this->name ?? ''),
            'slug' => (string) ($this->slug ?? ''),
            'width' => $this->width ? (int) $this->width : null,
            'height' => $this->height ? (int) $this->height : null,
            'quality' => (int) ($this->quality ?? 85),
            'active' => (bool) $this->active,
            'sort' => (int) ($this->sort ?? 0),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
