<?php

namespace RMS\Shop\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductCombinationResource extends JsonResource
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
            'product_id' => (int) $this->product_id,
            'sku' => (string) ($this->sku ?? ''),
            'price' => $this->price ? (float) $this->price : null,
            'sale_price' => $this->sale_price ? (float) $this->sale_price : null,
            'sale_price_cny' => $this->sale_price_cny ? (float) $this->sale_price_cny : null,
            'stock_qty' => (int) ($this->stock_qty ?? 0),
            'active' => (bool) $this->active,
            'values' => $this->whenLoaded('values', function () use ($request) {
                return $this->values->map(function ($value) {
                    return [
                        'id' => (int) $value->id,
                        'attribute_id' => (int) $value->attribute_id,
                        'attribute_value_id' => (int) $value->attribute_value_id,
                        'attribute' => $value->value && $value->value->attribute ? [
                            'id' => (int) $value->value->attribute->id,
                            'name' => (string) $value->value->attribute->name,
                        ] : null,
                        'value' => $value->value ? [
                            'id' => (int) $value->value->id,
                            'value' => (string) $value->value->value,
                        ] : null,
                    ];
                });
            }),
            'assigned_images' => $this->whenLoaded('assignedImages', function () use ($request) {
                return $this->assignedImages->map(function ($image) {
                    return [
                        'id' => (int) $image->id,
                        'url' => $image->url ?? null,
                        'is_main' => (bool) ($image->pivot->is_main ?? false),
                        'sort' => (int) ($image->pivot->sort ?? 0),
                    ];
                });
            }),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
