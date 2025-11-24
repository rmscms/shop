<?php

namespace RMS\Shop\Http\Resources\Panel;

use Illuminate\Http\Resources\Json\JsonResource;
use RMS\Shop\Services\ShopPriceService;

class ProductDetailResource extends ProductResource
{
    public function toArray($request): array
    {
        $base = parent::toArray($request);

        $base['short_desc'] = $this->short_desc;
        $base['description'] = $this->description;
        $base['availability'] = method_exists($this->resource, 'availability')
            ? $this->resource->availability()
            : null;

        $base['images'] = $this->whenLoaded('assignedImages', function () {
            return $this->assignedImages->map(function ($image) {
                return [
                    'id' => (int) $image->id,
                    'path' => $image->path,
                    'url' => $image->url,
                    'avif_url' => $image->avif_url,
                    'is_main' => (bool) ($image->pivot->is_main ?? false),
                    'sort' => (int) ($image->pivot->sort ?? 0),
                ];
            })->values();
        });

        $base['attributes'] = $this->whenLoaded('attributes', function () {
            return $this->attributes->map(function ($attr) {
                return [
                    'id' => (int) $attr->id,
                    'name' => (string) $attr->name,
                    'type' => $attr->type,
                    'ui' => $attr->ui,
                    'values' => $attr->values->map(function ($value) {
                        return [
                            'id' => (int) $value->id,
                            'value' => (string) $value->value,
                            'color' => $value->color,
                        ];
                    })->values(),
                ];
            })->values();
        });

        $priceService = app(ShopPriceService::class);

        $base['combinations'] = $this->whenLoaded('combinations', function () use ($priceService) {
            return $this->combinations->map(function ($combination) use ($priceService) {
                [$comboPrice, $comboPriceCny] = $this->convertPriceValue(
                    $priceService,
                    $combination->sale_price,
                    $combination->sale_price_cny,
                    'comb:'.$combination->id.':price'
                );
                [$comboSalePrice, $comboSalePriceCny] = $this->convertPriceValue(
                    $priceService,
                    $combination->sale_price,
                    $combination->sale_price_cny,
                    'comb:'.$combination->id.':sale'
                );

                return [
                    'id' => (int) $combination->id,
                    'sku' => $combination->sku,
                    'price' => $comboPrice,
                    'price_cny' => $comboPriceCny,
                    'sale_price' => $comboSalePrice,
                    'sale_price_cny' => $comboSalePriceCny,
                    'stock_qty' => (int) ($combination->stock_qty ?? 0),
                    'active' => (bool) $combination->active,
                    'main_image' => $combination->mainImageUrl(),
                    'images' => $combination->relationLoaded('assignedImages') 
                        ? $combination->assignedImages->map(function ($image) {
                            return [
                                'id' => (int) $image->id,
                                'path' => $image->path,
                                'url' => $image->url,
                                'avif_url' => $image->avif_url,
                                'is_main' => (bool) ($image->pivot->is_main ?? false),
                                'sort' => (int) ($image->pivot->sort ?? 0),
                            ];
                        })->values()
                        : [],
                    'videos' => $combination->relationLoaded('assignedVideos')
                        ? $combination->assignedVideos->map(function ($video) {
                            return [
                                'id' => (int) $video->id,
                                'title' => (string) ($video->title ?: $video->filename),
                                'hls_url' => $video->hls_url,
                                'poster_url' => $video->poster_url,
                                'is_main' => (bool) ($video->pivot->is_main ?? false),
                                'sort' => (int) ($video->pivot->sort ?? 0),
                            ];
                        })->values()
                        : [],
                    'main_video' => $combination->relationLoaded('assignedVideos')
                        ? (function () use ($combination) {
                            $mainVideoModel = $combination->assignedVideos->firstWhere('pivot.is_main', true);
                            if ($mainVideoModel) {
                                return [
                                    'id' => (int) $mainVideoModel->id,
                                    'hls_url' => $mainVideoModel->hls_url,
                                    'poster_url' => $mainVideoModel->poster_url,
                                    'title' => (string) ($mainVideoModel->title ?: $mainVideoModel->filename),
                                ];
                            }
                            return null;
                        })()
                        : null,
                    'attributes' => $combination->values->map(function ($value) {
                        $attrValue = $value->value;
                        $attribute = $attrValue?->attribute;

                        return [
                            'attribute_id' => (int) ($attribute?->id ?? 0),
                            'attribute' => $attribute?->name,
                            'attribute_ui' => $attribute?->ui,
                            'value_id' => (int) ($attrValue?->id ?? 0),
                            'value' => $attrValue?->value,
                            'color' => $attrValue?->color,
                        ];
                    })->filter(fn ($row) => $row['attribute_id'] && $row['value_id'])->values(),
                ];
            })->values();
        });

        $base['videos'] = $this->whenLoaded('assignedVideos', function () {
            return $this->assignedVideos->map(function ($video) {
                return [
                    'id' => (int) $video->id,
                    'title' => (string) ($video->title ?: $video->filename),
                    'filename' => (string) $video->filename,
                    'url' => $video->url,
                    'hls_url' => $video->hls_url,
                    'poster_url' => $video->poster_url,
                    'duration_seconds' => (int) ($video->duration_seconds ?? 0),
                    'size_bytes' => (int) ($video->size_bytes ?? 0),
                    'is_transcoded' => (bool) $video->is_transcoded,
                    'is_main' => (bool) ($video->pivot->is_main ?? false),
                    'sort' => (int) ($video->pivot->sort ?? 0),
                ];
            })->values();
        });

        $mainVideo = $this->whenLoaded('assignedVideos', function () {
            $mainVideoModel = $this->assignedVideos->firstWhere('pivot.is_main', true);
            if ($mainVideoModel) {
                return [
                    'id' => (int) $mainVideoModel->id,
                    'hls_url' => $mainVideoModel->hls_url,
                    'poster_url' => $mainVideoModel->poster_url,
                    'title' => (string) ($mainVideoModel->title ?: $mainVideoModel->filename),
                ];
            }
            return null;
        });
        
        $base['main_video'] = $mainVideo;

        return $base;
    }
}

