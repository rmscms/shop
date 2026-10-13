<?php

namespace RMS\Shop\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RMS\Shop\Models\ImageLibrary;

class ConvertImageToAvif implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Relative path from storage/app/public to the original file, e.g.
     * uploads/products/123/product/orig/uuid.jpg
     */
    public string $relativePath;

    public int $tries = 3;
    public int $timeout = 120; // seconds

    /** Quality 0-100 (lower = more compression) */
    public int $quality;

    public function __construct(string $relativePath, int $quality = 65)
    {
        $this->relativePath = $relativePath;
        $this->quality = $quality;
        $queueName = config('shop.queues.avif', 'shop-avif');
        $this->onQueue($queueName);
    }

    public function handle(): void
    {
        $src = storage_path('app/public/' . $this->relativePath);
        
        // Check if source file exists
        if (!file_exists($src)) {
            Log::warning('AVIF conversion failed: source file not found', [
                'path' => $this->relativePath,
                'full_path' => $src,
            ]);
            return;
        }

        $dstRel = ImageLibrary::resolveAvifPath($this->relativePath);
        $dst = storage_path('app/public/' . $dstRel);

        if (! is_dir(dirname($dst)) && ! @mkdir(dirname($dst), 0777, true) && ! is_dir(dirname($dst))) {
            Log::warning('AVIF conversion failed: could not create destination directory', [
                'dst' => $dstRel,
            ]);

            return;
        }

        try {
            if (class_exists(\Intervention\Image\ImageManager::class)) {
                $manager = null;
                try {
                    // Prefer Imagick when available for AVIF support
                    $manager = \Intervention\Image\ImageManager::imagick();
                } catch (\Throwable $e) {
                    $manager = \Intervention\Image\ImageManager::gd();
                }

                $img = $manager->read($src);
                // Keep original dimensions; just re-encode to AVIF
                $img->save($dst, $this->quality); // extension decides encoder
                
                Log::info('AVIF created successfully via Intervention', [
                    'src' => $this->relativePath,
                    'dst' => $dstRel,
                ]);
                return;
            }
        } catch (\Throwable $e) {
            Log::warning('AVIF encode via Intervention failed: ' . $e->getMessage(), [
                'src' => $this->relativePath,
                'dst' => $dstRel,
            ]);
        }

        // Fallback to native GD if available
        try {
            if (function_exists('imageavif')) {
                $data = @file_get_contents($src);
                if ($data !== false) {
                    $im = @imagecreatefromstring($data);
                    if ($im !== false) {
                        // preserve alpha if present
                        if (function_exists('imagesavealpha')) { @imagesavealpha($im, true); }
                        if (function_exists('imagealphablending')) { @imagealphablending($im, true); }
                        @imageavif($im, $dst, $this->quality);
                        if (is_resource($im) || (\PHP_VERSION_ID >= 80000 && $im instanceof \GdImage)) { @imagedestroy($im); }
                        
                        Log::info('AVIF created successfully via GD', [
                            'src' => $this->relativePath,
                            'dst' => $dstRel,
                        ]);
                        return;
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AVIF encode via GD failed: ' . $e->getMessage(), [
                'src' => $this->relativePath,
                'dst' => $dstRel,
            ]);
        }

        // If we reached here, AVIF encoding is not supported; keep original only.
        Log::warning('AVIF encoding not supported or failed', [
            'src' => $this->relativePath,
        ]);
    }
}

