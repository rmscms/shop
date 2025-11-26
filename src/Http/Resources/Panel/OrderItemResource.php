<?php

namespace RMS\Shop\Http\Resources\Panel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $image = method_exists($this->resource, 'snapshotImageUrls')
            ? $this->snapshotImageUrls()
            : ['url' => null, 'avif_url' => null];

        return [
            'id' => (int) $this->id,
            'product_id' => (int) $this->product_id,
            'combination_id' => $this->combination_id ? (int) $this->combination_id : null,
            'name' => $this->product?->name,
            'sku' => $this->combination?->sku ?: $this->product?->sku,
            'qty' => (int) $this->qty,
            'unit_price' => (float) $this->unit_price,
            'total' => (float) $this->total,
            'image_url' => $image['url'] ?? null,
            'image_avif_url' => $image['avif_url'] ?? null,
            'brand_id' => $this->product?->brand_id ? (int) $this->product->brand_id : null,
            'brand' => $this->when(
                $this->product && $this->product->relationLoaded('brand') && $this->product->brand,
                fn () => (new BrandResource($this->product->brand))->toArray($request)
            ),
        ];
    }
}

