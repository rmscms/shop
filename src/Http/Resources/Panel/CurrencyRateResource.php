<?php

namespace RMS\Shop\Http\Resources\Panel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyRateResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'base_code' => (string) $this->base_code,
            'quote_code' => (string) $this->quote_code,
            'sell_rate' => (float) $this->sell_rate,
            'effective_at' => optional($this->effective_at)->toDateTimeString(),
            'notes' => $this->notes,
        ];
    }
}

