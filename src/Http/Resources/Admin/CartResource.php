<?php

namespace RMS\Shop\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    public function toArray($request): array
    {
        if (!$this->resource) {
            return [];
        }

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
            'items_count' => $this->when(isset($this->items_count), (int) $this->items_count, null),
            'subtotal' => $this->when(isset($this->subtotal), (float) $this->subtotal, null),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'items' => $this->whenLoaded('items', function () use ($request) {
                return CartItemResource::collection($this->items)->toArray($request);
            }),
        ];
    }
}
