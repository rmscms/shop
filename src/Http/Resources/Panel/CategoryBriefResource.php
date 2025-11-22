<?php

namespace RMS\Shop\Http\Resources\Panel;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryBriefResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) ($this->name ?? ''),
            'slug' => (string) ($this->slug ?? ''),
        ];
    }
}

