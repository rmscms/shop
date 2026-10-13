<?php

namespace RMS\Shop\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RMS\Shop\Models\ImageLibrary;
use RMS\Shop\Models\ImageSize;
use RMS\Shop\Models\ImageSizeVariant;

class GenerateImageSizeVariant implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120; // seconds

    public function __construct(
        public int $imageId,
        public int $imageSizeId
    ) {
        $queueName = config('shop.queues.avif', 'shop-avif');
        $this->onQueue($queueName);
    }

    public function handle(): void
    {
        $image = ImageLibrary::find($this->imageId);
        $size = ImageSize::find($this->imageSizeId);

        if (!$image || !$size) {
            Log::warning('GenerateImageSizeVariant: Image or Size not found', [
                'image_id' => $this->imageId,
                'size_id' => $this->imageSizeId,
            ]);
            return;
        }

        // Check if variant already exists (race condition protection)
        if (ImageSizeVariant::where('image_id', $image->id)
            ->where('image_size_id', $size->id)
            ->exists()) {
            Log::info('GenerateImageSizeVariant: Variant already exists, skipping', [
                'image_id' => $image->id,
                'size_id' => $size->id,
            ]);
            return;
        }

        $src = storage_path('app/public/' . $image->path);

        if (!file_exists($src)) {
            Log::warning('GenerateImageSizeVariant: Source file not found', [
                'path' => $image->path,
                'full_path' => $src,
            ]);
            return;
        }

        try {
            // Determine output directory structure
            // Original: uploads/products/library/orig/filename.jpg
            // Variant: uploads/products/library/sizes/{size-slug}/filename.avif
            if (Str::contains($image->path, '/orig/')) {
                $baseDir = Str::beforeLast($image->path, '/orig/');
            } else {
                $baseDir = dirname($image->path);
            }

            $filename = pathinfo($image->filename, PATHINFO_FILENAME);
            $sizeDir = $baseDir . '/sizes/' . $size->slug;
            $variantPath = $sizeDir . '/' . $filename . '.avif';
            $dst = storage_path('app/public/' . $variantPath);

            @mkdir(dirname($dst), 0777, true);

            // Load and process image
            $manager = null;
            try {
                $manager = \Intervention\Image\ImageManager::imagick();
            } catch (\Throwable $e) {
                $manager = \Intervention\Image\ImageManager::gd();
            }

            $img = $manager->read($src);

            // Resize if dimensions are specified
            if ($size->width || $size->height) {
                $img->scale(
                    width: $size->width,
                    height: $size->height
                );
            }

            // Get actual dimensions after resize
            $actualWidth = $img->width();
            $actualHeight = $img->height();

            // Save as AVIF
            // Change extension to .avif and save with quality
            $img->save($dst, $size->quality);

            // Get file size
            $fileSize = file_exists($dst) ? filesize($dst) : null;

            // Create variant record
            ImageSizeVariant::create([
                'image_id' => $image->id,
                'image_size_id' => $size->id,
                'path' => $variantPath,
                'width' => $actualWidth,
                'height' => $actualHeight,
                'size_bytes' => $fileSize,
            ]);

            Log::info('GenerateImageSizeVariant: Variant created successfully', [
                'image_id' => $image->id,
                'size_id' => $size->id,
                'path' => $variantPath,
                'dimensions' => "{$actualWidth}x{$actualHeight}",
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateImageSizeVariant: Failed to generate variant', [
                'image_id' => $image->id,
                'size_id' => $size->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
