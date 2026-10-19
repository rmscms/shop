<?php
// git-trigger
namespace RMS\Shop\Services;

use RMS\Shop\Models\ImageLibrary;
use RMS\Shop\Models\ImageLibraryFolder;
use RMS\Shop\Models\ImageAssignment;
use RMS\Shop\Models\Product;
use RMS\Shop\Models\ProductCombination;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RMS\Shop\Jobs\ConvertImageToAvif;
use RMS\Shop\Services\ImageSizeService;
use RMS\Shop\Support\MediaLibrarySchema;

class ImageLibraryService
{
    /**
     * Upload image to library
     */
    public static function uploadImage($file, ImageLibraryFolder|string|null $folderOrFilename = null, ?string $filename = null): ImageLibrary
    {
        $folder = null;
        if (is_string($folderOrFilename)) {
            $filename = $folderOrFilename;
        } elseif ($folderOrFilename instanceof ImageLibraryFolder) {
            $folder = $folderOrFilename;
        }

        $filename = $filename ?: Str::uuid() . '.' . strtolower($file->getClientOriginalExtension());

        $origDir = static::origDirectoryForFolder($folder);
        $avifDir = static::avifDirectoryForFolder($folder);
        Storage::disk('public')->makeDirectory($origDir);
        Storage::disk('public')->makeDirectory($avifDir);

        $path = $file->storeAs($origDir, $filename, 'public');

        ConvertImageToAvif::dispatch($path);

        $payload = [
            'filename' => $filename,
            'path' => $path,
            'size_bytes' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ];

        if (MediaLibrarySchema::hasImageFolderColumn()) {
            $payload['folder_id'] = $folder?->id;
        }

        return ImageLibrary::create($payload);
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
        $assignment = ImageAssignment::create([
            'image_id' => $image->id,
            'assignable_type' => $assignableType,
            'assignable_id' => $assignableId,
            'is_main' => $isMain,
            'sort' => $sort,
        ]);

        // Generate size variants for the image (deduplication handled in service)
        ImageSizeService::generateVariantsForImage($image);

        return $assignment;
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
     * Delete an image, its files, and its product assignments.
     */
    public static function deleteImage(ImageLibrary $image): bool
    {
        $disk = Storage::disk('public');
        foreach ($image->variants as $variant) {
            if ($variant->path) {
                $disk->delete($variant->path);
            }
        }

        $path = $image->path;
        $disk->delete($path);
        $disk->delete(ImageLibrary::resolveAvifPath($path));

        return (bool) $image->delete();
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
    public static function searchImages(string $query = null, int $perPage = 20, ?int $folderId = null, bool $unfiledOnly = false): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $search = trim($query ?? '');

        if (empty($search)) {
            // Default: Show unassigned images first (newest to oldest)
            $builder = ImageLibrary::query()
                ->withCount('assignments')
                ->orderByRaw('(SELECT COUNT(*) FROM image_assignments WHERE image_assignments.image_id = image_library.id) ASC')
                ->orderBy('created_at', 'desc');
            static::applyImageFolderFilter($builder, $folderId, $unfiledOnly);

            return $builder->paginate($perPage);
        }

        // Search by product name - find products first
        $productIds = \RMS\Shop\Models\Product::query()
            ->where('name', 'like', "%{$search}%")
            ->pluck('id');

        if ($productIds->isEmpty()) {
            // No products found, search by filename instead
            $builder = ImageLibrary::query()
                ->where('filename', 'like', "%{$search}%")
                ->withCount('assignments')
                ->orderBy('created_at', 'desc');
            static::applyImageFolderFilter($builder, $folderId, $unfiledOnly);

            return $builder->paginate($perPage);
        }

        // Get images assigned to these products
        $builder = ImageLibrary::query()
            ->whereHas('assignments', function ($query) use ($productIds) {
                $query->where('assignable_type', \RMS\Shop\Models\Product::class)
                      ->whereIn('assignable_id', $productIds);
            })
            ->withCount('assignments')
            ->orderBy('created_at', 'desc');
        static::applyImageFolderFilter($builder, $folderId, $unfiledOnly);

        return $builder->paginate($perPage);
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

    public static function moveImage(ImageLibrary $image, ?ImageLibraryFolder $targetFolder): ImageLibrary
    {
        return DB::transaction(function () use ($image, $targetFolder) {
            if (MediaLibrarySchema::hasImageFolderColumn()) {
                $image->update(['folder_id' => $targetFolder?->id]);
            }

            return static::syncImageStoragePath($image->fresh(
                MediaLibrarySchema::hasImageFolderColumn() ? ['folder'] : []
            ));
        });
    }

    public static function syncImageStoragePath(ImageLibrary $image): ImageLibrary
    {
        $folder = MediaLibrarySchema::hasImageFolderColumn() ? $image->folder : null;
        $origDir = static::origDirectoryForFolder($folder);
        $newPath = $origDir.'/'.$image->filename;

        if ($image->path === $newPath) {
            return $image;
        }

        $disk = Storage::disk('public');
        $disk->makeDirectory($origDir);
        $disk->makeDirectory(static::avifDirectoryForFolder($folder));

        if ($disk->exists($image->path)) {
            $disk->move($image->path, $newPath);
        }

        $oldAvif = ImageLibrary::resolveAvifPath($image->path);
        $newAvif = ImageLibrary::resolveAvifPath($newPath);
        if ($disk->exists($oldAvif)) {
            $disk->makeDirectory(dirname($newAvif));
            $disk->move($oldAvif, $newAvif);
        }

        $image->update(['path' => $newPath]);

        return $image->fresh(['folder']);
    }

    public static function origDirectoryForFolder(?ImageLibraryFolder $folder): string
    {
        $base = 'uploads/products/library/orig';
        $subPath = trim((string) ($folder?->path ?? ''), '/');

        return $subPath === '' ? $base : $base.'/'.$subPath;
    }

    public static function avifDirectoryForFolder(?ImageLibraryFolder $folder): string
    {
        $base = 'uploads/products/library/avif';
        $subPath = trim((string) ($folder?->path ?? ''), '/');

        return $subPath === '' ? $base : $base.'/'.$subPath;
    }

    protected static function applyImageFolderFilter($builder, ?int $folderId, bool $unfiledOnly): void
    {
        if (! MediaLibrarySchema::hasImageFolderColumn()) {
            return;
        }

        if ($unfiledOnly) {
            $builder->whereNull('folder_id');
        } elseif ($folderId) {
            $builder->where('folder_id', $folderId);
        }
    }
}
