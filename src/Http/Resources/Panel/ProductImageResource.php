<?php

namespace RMS\Shop\Http\Resources\Panel;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageResource extends JsonResource
{
    public function toArray($request): array
    {
        $path = (string) ($this->path ?? '');
        $publicUrl = $path ? Storage::disk('public')->url($path) : null;
        $avifUrl = $this->resolveAvifUrl($path);

        return [
            'id' => (int) $this->id,
            'path' => $path,
            'url' => $publicUrl,
            'avif_url' => $avifUrl,
            'is_main' => (bool) $this->is_main,
            'sort' => (int) ($this->sort ?? 0),
        ];
    }

    protected function resolveAvifUrl(?string $path): ?string
    {
        if (!$path || !Str::contains($path, '/orig/')) {
            return null;
        }

        $directory = Str::beforeLast($path, '/orig/');
        $filename = Str::afterLast($path, '/orig/');
        if ($directory === $path || !$filename) {
            return null;
        }

        $basename = pathinfo($filename, PATHINFO_FILENAME);
        if (!$basename) {
            return null;
        }

        $avifRelativePath = $directory.'/avif/'.$basename.'.avif';
        if (!Storage::disk('public')->exists($avifRelativePath)) {
            return null;
        }

        return Storage::disk('public')->url($avifRelativePath);
    }
}

