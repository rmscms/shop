<?php

namespace RMS\Shop\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ImageLibraryResource extends JsonResource
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

        $url = $this->path ? Storage::disk('public')->url($this->path) : null;

        return [
            'id' => (int) $this->id,
            'filename' => (string) $this->filename,
            'path' => (string) $this->path,
            'url' => $url,
            'folder_id' => $this->folder_id !== null ? (int) $this->folder_id : null,
            'mime_type' => (string) $this->mime_type,
            'size_bytes' => (int) ($this->size_bytes ?? 0),
            'formatted_size' => $this->formatted_size ?? null,
            'assignments_count' => $this->when(isset($this->assignments_count), fn() => (int) $this->assignments_count),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
