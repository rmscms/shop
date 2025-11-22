<?php

namespace RMS\Shop\Http\Resources\Panel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailResource extends JsonResource
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
            'paid_at' => optional($this->paid_at)->toDateTimeString(),
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'tracking_code' => $this->tracking_code,
            'tracking_url' => $this->tracking_url,
            'shipping' => [
                'name' => $this->shipping_name,
                'mobile' => $this->shipping_mobile,
                'postal_code' => $this->shipping_postal_code,
                'address' => $this->shipping_address,
                'customer_note' => $this->customer_note,
            ],
            'items' => OrderItemResource::collection($this->whenLoaded('items', $this->items ?? collect()))->toArray($request),
            'notes' => OrderNoteResource::collection($this->visibleNotes ?? collect())->toArray($request),
        ];
    }
}

