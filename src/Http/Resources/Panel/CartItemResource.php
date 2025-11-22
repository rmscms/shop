<?php

namespace RMS\Shop\Http\Resources\Panel;

use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'line_id' => $this['line_id'],
            'qty' => (int) $this['qty'],
            'unit_price' => (float) $this['unit_price'],
            'subtotal' => (float) $this['subtotal'],
            'product' => $this['product'],
            'combination' => $this['combination'],
        ];
    }
}

