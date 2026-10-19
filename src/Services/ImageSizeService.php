<?php

namespace RMS\Shop\Services;

use Illuminate\Support\Facades\Storage;
use RMS\Shop\Jobs\GenerateImageSizeVariant;
use RMS\Shop\Models\ImageLibrary;
use RMS\Shop\Models\ImageSize;
use RMS\Shop\Models\ImageSizeVariant;

class ImageSizeService
{
    /**
     * Generate size variants for an image
     * Only generates variants that don't already exist (deduplication)
     */
    public static function generateVariantsForImage(ImageLibrary $image): void
    {
        $activeSizes = ImageSize::getActiveSizes();

        foreach ($activeSizes as $size) {
            // Check if variant already exists
            if (static::shouldGenerateVariant($image, $size)) {
                // Dispatch job to generate variant
                GenerateImageSizeVariant::dispatch($image->id, $size->id);
            }
        }
    }

    /**
     * Queue regeneration for every library image × active size.
     * When $force is true, existing size variants are removed first so
     * dimension/quality changes actually rebuild files.
     */
    public static function regenerateAll(bool $force = true): int
    {
        $activeSizes = ImageSize::getActiveSizes();
        if ($activeSizes->isEmpty()) {
            return 0;
        }

        $queued = 0;

        ImageLibrary::query()
            ->orderBy('id')
            ->chunkById(50, function ($images) use ($activeSizes, $force, &$queued) {
                foreach ($images as $image) {
                    foreach ($activeSizes as $size) {
                        if ($force) {
                            static::deleteVariant($image, $size);
                        }

                        if (static::shouldGenerateVariant($image, $size)) {
                            GenerateImageSizeVariant::dispatch($image->id, $size->id);
                            $queued++;
                        }
                    }
                }
            });

        return $queued;
    }

    /**
     * Remove DB row and disk file for one image/size variant.
     */
    public static function deleteVariant(ImageLibrary $image, ImageSize $size): void
    {
        $variants = ImageSizeVariant::query()
            ->where('image_id', $image->id)
            ->where('image_size_id', $size->id)
            ->get();

        foreach ($variants as $variant) {
            if (!empty($variant->path) && Storage::disk('public')->exists($variant->path)) {
                Storage::disk('public')->delete($variant->path);
            }
            $variant->delete();
        }
    }

    /**
     * Check if variant should be generated
     * Returns true if variant doesn't exist yet
     */
    public static function shouldGenerateVariant(ImageLibrary $image, ImageSize $size): bool
    {
        return !ImageSizeVariant::where('image_id', $image->id)
            ->where('image_size_id', $size->id)
            ->exists();
    }

    /**
     * Get all active image sizes
     */
    public static function getActiveSizes()
    {
        return ImageSize::getActiveSizes();
    }
}
