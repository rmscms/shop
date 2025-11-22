<?php

namespace RMS\Shop\Http\Resources\Panel;

use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'cart_key' => $this['cart_key'],
            'items' => CartItemResource::collection($this['items'])->toArray($request),
            'summary' => $this['summary'],
        ];
    }
}

