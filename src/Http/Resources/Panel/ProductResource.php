<?php

namespace RMS\Shop\Http\Resources\Panel;

use Illuminate\Http\Resources\Json\JsonResource;
use RMS\Shop\Services\ShopPriceService;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        $mainImage = $this->mainImageUrl();
        $priceService = app(ShopPriceService::class);
        [$salePrice, $salePriceCny] = $this->convertPriceValue(
            $priceService,
            $this->sale_price,
            $this->sale_price_cny,
            'sale'
        );
        [$price, $priceCny] = $this->convertPriceValue(
            $priceService,
            $this->price,
            $this->cost_cny,
            'base'
        );

        return [
            'id' => (int) $this->id,
            'name' => (string) ($this->name ?? ''),
            'slug' => (string) ($this->slug ?? ''),
            'sku' => (string) ($this->sku ?? ''),
            'price' => $price,
            'price_cny' => $priceCny,
            'sale_price' => $salePrice,
            'sale_price_cny' => $salePriceCny,
            'stock_qty' => (int) ($this->stock_qty ?? 0),
            'active' => (bool) $this->active,
            'category' => $this->whenLoaded('category', function () use ($request) {
                return (new CategoryBriefResource($this->category))->toArray($request);
            }),
            'brand_id' => $this->brand_id ? (int) $this->brand_id : null,
            'brand' => $this->whenLoaded('brand', function () use ($request) {
                return (new BrandResource($this->brand))->toArray($request);
            }),
            'main_image' => $mainImage,
            'main_video' => $this->whenLoaded('assignedVideos', function () {
                $mainVideoModel = $this->assignedVideos->first();
                if ($mainVideoModel) {
                    return [
                        'id' => (int) $mainVideoModel->id,
                        'hls_url' => $mainVideoModel->hls_url,
                        'poster_url' => $mainVideoModel->poster_url,
                        'title' => (string) ($mainVideoModel->title ?: $mainVideoModel->filename),
                    ];
                }
                return null;
            }),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'currency' => [
                'code' => $priceService->displayCurrency(),
                'label' => $priceService->displayLabel(),
                'base_code' => $priceService->baseCurrency(),
            ],
        ];
    }

    protected function convertPriceValue(ShopPriceService $service, ?float $fallbackDisplay, ?float $baseCny, string $context): array
    {
        $base = $baseCny !== null ? (float) $baseCny : null;
        $converted = $base !== null
            ? $service->convertFromBase($base, 'product:'.$this->id.':'.$context)
            : null;

        if ($converted === null && $fallbackDisplay !== null) {
            $converted = (float) $fallbackDisplay;
        }

        return [$converted, $base];
    }
}

