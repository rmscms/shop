<?php

namespace RMS\Shop\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray($request): array
    {
        if (!$this->resource) {
            return [];
        }

        return [
            'id' => (int) $this->id,
            'cart_id' => (int) $this->cart_id,
            'product_id' => (int) $this->product_id,
            'qty' => (int) $this->qty,
            'unit_price' => (float) $this->unit_price,
            'total' => (float) $this->total,
            'product' => $this->whenLoaded('product', function () {
                return $this->product ? [
                    'id' => (int) $this->product->id,
                    'name' => (string) $this->product->name,
                    'slug' => (string) $this->product->slug,
                ] : null;
            }),
        ];
    }
}
