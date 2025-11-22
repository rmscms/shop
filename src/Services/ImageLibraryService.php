<?php
// git-trigger
namespace RMS\Shop\Services;

use RMS\Shop\Models\ImageLibrary;
use RMS\Shop\Models\ImageAssignment;
use RMS\Shop\Models\Product;
use RMS\Shop\Models\ProductCombination;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RMS\Shop\Jobs\ConvertImageToAvif;

class ImageLibraryService
{
    /**
     * Upload image to library
     */
    public static function uploadImage($file, string $filename = null): ImageLibrary
    {
        $filename = $filename ?: Str::uuid() . '.' . strtolower($file->getClientOriginalExtension());

        // Create library directory structure
        $baseDir = 'uploads/products/library';
        $origDir = $baseDir . '/orig';

        // Store original file
        $path = $file->storeAs($origDir, $filename, 'public');

        // Generate AVIF variant
        ConvertImageToAvif::dispatch($path);

        // Create library entry
        $image = ImageLibrary::create([
            'filename' => $filename,
            'path' => $path,
            'size_bytes' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        return $image;
    }

    /**
     * Assign image to a product or combination
     */
    public static function assignImage(ImageLibrary $image, $assignable, bool $isMain = false, int $sort = 0): ImageAssignment
    {
        if (!$assignable || !is_object($assignable)) {
            throw new \InvalidArgumentException('Assignable must be a valid object');
        }

        $assignableType = get_class($assignable);
        $assignableId = $assignable->getKey();

        // Check if already assigned
        $existing = ImageAssignment::where('image_id', $image->id)
            ->where('assignable_type', $assignableType)
            ->where('assignable_id', $assignableId)
            ->first();

        if ($existing) {
            // Update existing assignment
            $existing->update([
                'is_main' => $isMain,
                'sort' => $sort,
            ]);
            return $existing;
        }

        // Create new assignment
        return ImageAssignment::create([
            'image_id' => $image->id,
            'assignable_type' => $assignableType,
            'assignable_id' => $assignableId,
            'is_main' => $isMain,
            'sort' => $sort,
        ]);
    }

    /**
     * Detach image from assignable
     */
    public static function detachImage(ImageLibrary $image, $assignable): bool
    {
        if (!$assignable || !is_object($assignable)) {
            return false;
        }

        $assignableType = get_class($assignable);
        $assignableId = $assignable->getKey();

        return ImageAssignment::where('image_id', $image->id)
            ->where('assignable_type', $assignableType)
            ->where('assignable_id', $assignableId)
            ->delete() > 0;
    }

    /**
     * Delete image from library (only if no assignments)
     */
    public static function deleteImage(ImageLibrary $image): bool
    {
        if (!$image->canBeDeleted()) {
            return false;
        }

        // Delete physical files
        $path = $image->path;
        Storage::disk('public')->delete($path);

        // Delete AVIF variant if exists
        $dir = dirname($path);
        $filename = pathinfo($image->filename, PATHINFO_FILENAME);
        $avifPath = $dir . '/avif/' . $filename . '.avif';
        Storage::disk('public')->delete($avifPath);

        // Delete from database
        return $image->delete();
    }

    /**
     * Get images assigned to a product
     */
    public static function getProductImages(Product $product): \Illuminate\Database\Eloquent\Collection
    {
        return $product->assignedImages()->orderByPivot('sort')->get();
    }

    /**
     * Get images assigned to a product combination
     */
    public static function getCombinationImages(ProductCombination $combination): \Illuminate\Database\Eloquent\Collection
    {
        return $combination->assignedImages()->orderByPivot('sort')->get();
    }

    /**
     * Search images in library with smart filtering
     */
    public static function searchImages(string $query = null, int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $search = trim($query ?? '');

        if (empty($search)) {
            // Default: Show unassigned images first (newest to oldest)
            return ImageLibrary::query()
                ->withCount('assignments')
                ->orderByRaw('(SELECT COUNT(*) FROM image_assignments WHERE image_assignments.image_id = image_library.id) ASC')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        }

        // Search by product name - find products first
        $productIds = \RMS\Shop\Models\Product::query()
            ->where('name', 'like', "%{$search}%")
            ->pluck('id');

        if ($productIds->isEmpty()) {
            // No products found, search by filename instead
            return ImageLibrary::query()
                ->where('filename', 'like', "%{$search}%")
                ->withCount('assignments')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        }

        // Get images assigned to these products
        return ImageLibrary::query()
            ->whereHas('assignments', function ($query) use ($productIds) {
                $query->where('assignable_type', \RMS\Shop\Models\Product::class)
                      ->whereIn('assignable_id', $productIds);
            })
            ->withCount('assignments')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get images by product for selection
     */
    public static function getImagesByProduct(int $productId): \Illuminate\Database\Eloquent\Collection
    {
        $product = Product::findOrFail($productId);
        return self::getProductImages($product);
    }

    /**
     * Bulk assign images to product
     */
    public static function bulkAssignToProduct(array $imageIds, Product $product): array
    {
        $results = ['assigned' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($imageIds as $imageId) {
            try {
                $image = ImageLibrary::find($imageId);
                if (!$image) {
                    $results['errors'][] = "Image {$imageId} not found";
                    continue;
                }

                $existing = ImageAssignment::where('image_id', $image->id)
                    ->where('assignable_type', Product::class)
                    ->where('assignable_id', $product->id)
                    ->exists();

                if ($existing) {
                    $results['skipped']++;
                } else {
                    self::assignImage($image, $product);
                    $results['assigned']++;
                }
            } catch (\Exception $e) {
                $results['errors'][] = "Error assigning image {$imageId}: " . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Set main image for assignable by assignment ID
     */
    public static function setMainImage($assignable, int $assignmentId): bool
    {
        if (!$assignable || !is_object($assignable)) {
            return false;
        }

        $assignment = ImageAssignment::findOrFail($assignmentId);
        $assignableType = get_class($assignable);
        $assignableId = $assignable->getKey();

        // Verify assignment belongs to this assignable
        if ($assignment->assignable_type !== $assignableType ||
            $assignment->assignable_id != $assignableId) {
            return false;
        }

        DB::transaction(function () use ($assignableType, $assignableId, $assignment) {
            // Unset main for all other images of this assignable
            ImageAssignment::where('assignable_type', $assignableType)
                ->where('assignable_id', $assignableId)
                ->update(['is_main' => false]);

            // Set main for this assignment
            $assignment->update(['is_main' => true]);
        });

        return true;
    }

    /**
     * Set main image for assignable (legacy method)
     */
    public static function setMainImageByImage(ImageLibrary $image, $assignable): bool
    {
        if (!$assignable || !is_object($assignable)) {
            return false;
        }

        $assignableType = get_class($assignable);
        $assignableId = $assignable->getKey();

        DB::transaction(function () use ($image, $assignableType, $assignableId) {
            // Unset main for all other images of this assignable
            ImageAssignment::where('assignable_type', $assignableType)
                ->where('assignable_id', $assignableId)
                ->update(['is_main' => false]);

            // Set main for this image
            ImageAssignment::where('image_id', $image->id)
                ->where('assignable_type', $assignableType)
                ->where('assignable_id', $assignableId)
                ->update(['is_main' => true]);
        });

        return true;
    }

    /**
     * Update sort order for assignments
     */
    public static function updateSort($assignable, array $assignmentItems): bool
    {
        if (!$assignable || !is_object($assignable)) {
            return false;
        }

        try {
            $assignableType = get_class($assignable);
            $assignableId = $assignable->getKey();

            DB::transaction(function () use ($assignableType, $assignableId, $assignmentItems) {
                foreach ($assignmentItems as $item) {
                    ImageAssignment::where('id', (int)$item['id'])
                        ->where('assignable_type', $assignableType)
                        ->where('assignable_id', $assignableId)
                        ->update(['sort' => (int)$item['sort']]);
                }
            });
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Update sort order for images (legacy method)
     */
    public static function updateSortOrder($assignable, array $imageIds): void
    {
        if (!$assignable || !is_object($assignable)) {
            return;
        }

        $assignableType = get_class($assignable);
        $assignableId = $assignable->getKey();

        foreach ($imageIds as $sort => $imageId) {
            ImageAssignment::where('image_id', $imageId)
                ->where('assignable_type', $assignableType)
                ->where('assignable_id', $assignableId)
                ->update(['sort' => $sort]);
        }
    }
}
