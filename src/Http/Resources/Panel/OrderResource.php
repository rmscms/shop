<?php

namespace RMS\Shop\Http\Resources\Panel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'status' => (string) $this->status,
            'status_label' => method_exists($this->resource, 'getStatusLabelAttribute')
                ? $this->resource->status_label
                : $this->status,
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'shipping_cost' => (float) $this->shipping_cost,
            'total' => (float) $this->total,
            'items_count' => $this->when(isset($this->items_count), (int) $this->items_count, null),
            'tracking_code' => $this->tracking_code,
            'tracking_url' => $this->tracking_url,
            'paid_at' => optional($this->paid_at)->toDateTimeString(),
            'created_at' => optional($this->created_at)->toDateTimeString(),
        ];
    }
}

