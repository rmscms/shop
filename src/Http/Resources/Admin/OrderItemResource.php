<?php

namespace RMS\Shop\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray($request): array
    {
        if (!$this->resource) {
            return [];
        }

        return [
            'id' => (int) $this->id,
            'order_id' => (int) $this->order_id,
            'product_id' => $this->product_id ? (int) $this->product_id : null,
            'product_name' => (string) ($this->product_name ?? ''),
            'product_sku' => $this->product_sku,
            'qty' => (int) $this->qty,
            'unit_price' => (float) $this->unit_price,
            'total' => (float) $this->total,
            'product' => $this->whenLoaded('product', function () use ($request) {
                return $this->product ? [
                    'id' => (int) $this->product->id,
                    'name' => (string) $this->product->name,
                    'slug' => (string) $this->product->slug,
                ] : null;
            }),
        ];
    }
}
