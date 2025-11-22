<?php

namespace RMS\Shop\Http\Resources\Panel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderNoteResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $item = (array) $this->resource;

        $hasAdmin = !empty($item['admin_id']) || !empty($this->admin_id);
        $authorName = $item['admin_name']
            ?? $this->admin_name
            ?? ($hasAdmin ? 'ادمین' : 'کاربر');

        return [
            'id' => isset($item['id']) ? (int) $item['id'] : (isset($this->id) ? (int) $this->id : null),
            'text' => $item['note_text'] ?? $this->note_text ?? '',
            'visible_to_user' => (bool) ($item['visible_to_user'] ?? $this->visible_to_user ?? true),
            'author' => [
                'type' => $hasAdmin ? 'admin' : 'user',
                'name' => $authorName,
                'admin_id' => isset($item['admin_id']) ? (int) $item['admin_id'] : ($this->admin_id ?? null),
            ],
            'created_at' => isset($item['created_at'])
                ? (string) $item['created_at']
                : (string) optional($this->created_at)->toDateTimeString(),
        ];
    }
}

