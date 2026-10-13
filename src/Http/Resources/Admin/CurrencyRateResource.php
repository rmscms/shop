<?php

namespace RMS\Shop\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyRateResource extends JsonResource
{
    public function toArray($request): array
    {
        if (!$this->resource) {
            return [];
        }

        return [
            'id' => (int) $this->id,
            'base_code' => (string) $this->base_code,
            'quote_code' => (string) $this->quote_code,
            'sell_rate' => (float) $this->sell_rate,
            'effective_at' => optional($this->effective_at)->toIso8601String(),
            'notes' => $this->notes,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
