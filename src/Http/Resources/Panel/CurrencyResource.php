<?php

namespace RMS\Shop\Http\Resources\Panel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'code' => (string) $this->code,
            'name' => (string) $this->name,
            'symbol' => $this->symbol,
            'decimals' => (int) $this->decimals,
            'is_base' => (bool) $this->is_base,
        ];
    }
}

