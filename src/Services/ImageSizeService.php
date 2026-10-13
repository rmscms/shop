<?php

namespace RMS\Shop\Services;

use RMS\Shop\Models\ImageLibrary;
use RMS\Shop\Models\ImageSize;
use RMS\Shop\Models\ImageSizeVariant;
use RMS\Shop\Jobs\GenerateImageSizeVariant;

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
