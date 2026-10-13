<?php

namespace RMS\Shop\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyResource extends JsonResource
{
    public function toArray($request): array
    {
        if (!$this->resource) {
            return [];
        }

        return [
            'id' => (int) $this->id,
            'code' => (string) $this->code,
            'name' => (string) $this->name,
            'symbol' => (string) ($this->symbol ?? ''),
            'decimals' => (int) ($this->decimals ?? 2),
            'is_base' => (bool) $this->is_base,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
