<?php

namespace RMS\Shop\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'name' => (string) ($this->name ?? ''),
            'slug' => (string) ($this->slug ?? ''),
            'sku' => (string) ($this->sku ?? ''),
            'price' => $this->price ? (float) $this->price : null,
            'sale_price' => $this->sale_price ? (float) $this->sale_price : null,
            'cost_cny' => $this->cost_cny ? (float) $this->cost_cny : null,
            'sale_price_cny' => $this->sale_price_cny ? (float) $this->sale_price_cny : null,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value ? (float) $this->discount_value : null,
            'stock_qty' => (int) ($this->stock_qty ?? 0),
            'point_per_unit' => (int) ($this->point_per_unit ?? 0),
            'active' => (bool) $this->active,
            'short_desc' => $this->short_desc,
            'description' => $this->description,
            'category_id' => $this->category_id ? (int) $this->category_id : null,
            'category' => $this->whenLoaded('category', function () use ($request) {
                return $this->category ? [
                    'id' => (int) $this->category->id,
                    'name' => (string) $this->category->name,
                    'slug' => (string) $this->category->slug,
                ] : null;
            }),
            'brand_id' => $this->brand_id ? (int) $this->brand_id : null,
            'brand' => $this->whenLoaded('brand', function () use ($request) {
                return $this->brand ? [
                    'id' => (int) $this->brand->id,
                    'name' => (string) $this->brand->name,
                    'slug' => (string) $this->brand->slug,
                ] : null;
            }),
            'main_image_url' => $this->mainImageUrl(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'deleted_at' => optional($this->deleted_at)->toIso8601String(),
        ];
    }
}
