<?php

namespace RMS\Shop\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RMS\Shop\Helpers\AvifHelper;

class RegenerateAvifForDirectories implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int, array{type?:string,dir?:string}>  $directories
     */
    public function __construct(
        public array $directories,
        public bool $cleanExisting = false,
    ) {
        $this->onQueue(config('shop.queues.avif', 'shop-avif'));
    }

    public function handle(): void
    {
        foreach ($this->directories as $directory) {
            $type = ($directory['type'] ?? 'public') === 'storage' ? 'storage' : 'public';
            $dir = trim((string) ($directory['dir'] ?? ''), '/');
            if ($dir === '') {
                continue;
            }

            if ($this->cleanExisting) {
                AvifHelper::cleanAvifFiles($dir);
            }

            if ($type === 'storage') {
                $this->convertStorage($dir);
                continue;
            }

            $this->convertPublic($dir);
        }
    }

    protected function convertPublic(string $relativeDir): void
    {
        $absolute = public_path($relativeDir);
        if (!File::isDirectory($absolute)) {
            return;
        }

        $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        foreach (File::allFiles($absolute) as $file) {
            if (!in_array(strtolower($file->getExtension()), $extensions, true)) {
                continue;
            }

            AvifHelper::convertToAvif($file->getPathname(), 85);
        }
    }

    protected function convertStorage(string $relativeDir): void
    {
        $disk = Storage::disk('public');
        if (!$disk->exists($relativeDir)) {
            return;
        }

        $extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        foreach ($disk->allFiles($relativeDir) as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, $extensions, true) || !Str::contains($file, '/orig/')) {
                continue;
            }

            ConvertImageToAvif::dispatch($file, 85);
        }
    }
}
