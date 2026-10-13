<?php

namespace RMS\Shop\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class VideoLibraryResource extends JsonResource
{
    public function toArray($request): array
    {
        if (!$this->resource) {
            return [];
        }

        return [
            'id' => (int) $this->id,
            'filename' => (string) $this->filename,
            'title' => (string) ($this->title ?? ''),
            'path' => (string) ($this->path ?? ''),
            'hls_path' => (string) ($this->hls_path ?? ''),
            'poster_path' => (string) ($this->poster_path ?? ''),
            'url' => $this->url,
            'hls_url' => $this->hls_url,
            'poster_url' => $this->poster_url,
            'duration_seconds' => $this->duration_seconds ? (int) $this->duration_seconds : null,
            'size_bytes' => $this->size_bytes ? (int) $this->size_bytes : null,
            'is_transcoded' => (bool) $this->is_transcoded,
            'display_name' => (string) $this->display_name,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
