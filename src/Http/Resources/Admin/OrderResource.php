<?php

namespace RMS\Shop\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        if (!$this->resource) {
            return [];
        }

        $statusInfo = \RMS\Shop\Models\Order::statuses()[$this->status] ?? null;

        return [
            'id' => (int) $this->id,
            'user_id' => $this->user_id ? (int) $this->user_id : null,
            'user' => $this->whenLoaded('user', function () {
                return $this->user ? [
                    'id' => (int) $this->user->id,
                    'name' => (string) $this->user->name,
                    'mobile' => $this->user->mobile ?? null,
                ] : null;
            }),
            'status' => (string) $this->status,
            'status_label' => $statusInfo->label ?? $this->status,
            'status_class' => $statusInfo->class ?? '',
            'subtotal' => (float) $this->subtotal,
            'discount' => (float) $this->discount,
            'shipping_cost' => (float) $this->shipping_cost,
            'total' => (float) $this->total,
            'items_count' => $this->when(isset($this->items_count), (int) $this->items_count, null),
            'tracking_code' => $this->tracking_code,
            'tracking_url' => $this->tracking_url,
            'payment_driver' => $this->payment_driver,
            'payment_reference' => $this->payment_reference,
            'shipping_name' => $this->shipping_name,
            'shipping_mobile' => $this->shipping_mobile,
            'shipping_postal_code' => $this->shipping_postal_code,
            'shipping_address' => $this->shipping_address,
            'customer_note' => $this->customer_note,
            'paid_at' => optional($this->paid_at)->toIso8601String(),
            'refunded_at' => optional($this->refunded_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'items' => $this->whenLoaded('items', function () use ($request) {
                return OrderItemResource::collection($this->items)->toArray($request);
            }),
        ];
    }
}
