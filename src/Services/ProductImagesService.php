<?php
// git-trigger
namespace RMS\Shop\Services;

use RMS\Shop\Models\Product;
use RMS\Shop\Models\ProductCombination;
use RMS\Shop\Models\ProductCombinationImage;
use RMS\Shop\Models\ProductCombinationValue;
use RMS\Shop\Models\ImageLibrary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImagesService
{
    public static function assignToCombination(int $productId, int $combinationId, string $path): array
    {
        $comb = ProductCombination::query()->where(['id'=>$combinationId, 'product_id'=>$productId])->firstOrFail();
        $exists = ProductCombinationImage::query()->where(['combination_id'=>$combinationId,'path'=>$path])->exists();
        if (!$exists) {
            ProductCombinationImage::query()->create([
                'combination_id' => $combinationId,
                'path' => $path,
                'is_main' => false,
                'sort' => 0,
            ]);
        }
        // Build label SKU + attributes
        $comb->loadMissing(['values.value.attribute']);
        $parts = [];
        foreach ($comb->values as $cv) {
            $attr = $cv->value->attribute->name ?? null;
            $val = $cv->value->value ?? null;
            if ($attr !== null && $val !== null) { $parts[] = $attr.': '.$val; }
        }
        $label = ($comb->sku ? ($comb->sku.' — ') : '').implode(' / ', $parts);
        return ['label' => $label, 'file_path' => $path];
    }

    public static function detachFromCombination(int $productId, int $combinationId, ?int $combinationImageId = null, ?int $imageId = null, ?string $filePath = null): bool
    {
        $comb = ProductCombination::query()->where(['id'=>$combinationId, 'product_id'=>$productId])->firstOrFail();
        if ($combinationImageId) {
            return (bool) ProductCombinationImage::query()->where(['id'=>$combinationImageId,'combination_id'=>$combinationId])->delete();
        }
        $path = null;
        if ($imageId) {
            $imgRow = \RMS\Shop\Models\ProductImage::query()->where(['id'=>$imageId,'product_id'=>$productId])->first();
            if ($imgRow) { $path = $imgRow->path; }
        }
        if (!$path && $filePath) { $path = $filePath; }
        if (!$path) { return false; }
        return (bool) ProductCombinationImage::query()->where(['combination_id'=>$combinationId,'path'=>$path])->delete();
    }

    /**
     * Upload gallery images for product or combination
     */
    public static function uploadGallery(int $productId, UploadedFile $file, string $gallery, ?int $combinationId = null): array
    {
        // Validate combination belongs to product when present
        if ($combinationId) {
            $okComb = ProductCombination::query()->where(['id'=>$combinationId,'product_id'=>$productId])->exists();
            if (!$okComb) {
                throw new \InvalidArgumentException('combination_not_found');
            }
        }

        // Get target model
        $targetModel = $combinationId
            ? ProductCombination::findOrFail($combinationId)
            : Product::findOrFail($productId);

        // Upload to Image Library (handles AVIF conversion)
        $imageLibrary = ImageLibraryService::uploadImage($file);

        // Assign to target model
        $assignment = ImageLibraryService::assignImage($imageLibrary, $targetModel);

        // Use AVIF URL if available, otherwise original
        $imageUrl = $imageLibrary->avif_url ?: $imageLibrary->url;

        return [
            'image_id' => $imageLibrary->id,
            'assignment_id' => $assignment->id,
            'url' => $imageUrl
        ];
    }

    /**
     * Delete gallery image from product or combination
     */
    public static function deleteGallery(int $productId, string $filePath, ?int $combinationId = null): bool
    {
        // convert public URL to storage path if needed
        if (str_starts_with($filePath, url('/storage'))) {
            $rel = str_replace(url('/storage').'/', '', $filePath);
        } else if (str_starts_with($filePath, '/storage/')) {
            $rel = substr($filePath, 9);
        } else {
            $rel = $filePath; // assume relative storage path
        }

        $scope = str_contains($rel, '/combinations/') ? 'combination' : 'product';
        $dir = Str::beforeLast($rel, '/orig/');
        $name = Str::afterLast($rel, '/orig/');

        // Delete original
        Storage::disk('public')->delete($rel);

        // Delete AVIF variant if exists
        $avifRel = $dir.'/avif/'.pathinfo($name, PATHINFO_FILENAME).'.avif';
        Storage::disk('public')->delete($avifRel);

        // Cleanup any legacy code_* variants
        $files = Storage::disk('public')->files($dir);
        $suffix = '_'.$name;
        foreach ($files as $f) {
            if (str_ends_with($f, $suffix)) {
                Storage::disk('public')->delete($f);
            }
        }

        if ($scope === 'product') {
            \RMS\Shop\Models\ProductImage::query()->where(['product_id'=>$productId,'path'=>$rel])->delete();
        } else {
            $combIds = ProductCombination::query()->where('product_id', (int)$productId)->pluck('id');
            ProductCombinationImage::query()->whereIn('combination_id', $combIds)->where('path',$rel)->delete();
        }

        return true;
    }

    /**
     * Assign image to product
     */
    public static function assignToProduct(int $productId, int $imageId, array $data = []): bool
    {
        $product = Product::findOrFail($productId);
        $image = ImageLibrary::findOrFail($imageId);
        
        ImageLibraryService::assignImage($image, $product, $data['is_main'] ?? false, $data['sort'] ?? 0);
        
        return true;
    }

    /**
     * Detach image from product
     */
    public static function detachFromProduct(int $productId, int $imageId): bool
    {
        $product = Product::findOrFail($productId);
        $image = ImageLibrary::findOrFail($imageId);
        
        return ImageLibraryService::detachImage($image, $product);
    }

    /**
     * Set main image for product
     */
    public static function setMainImage(int $productId, int $imageId): bool
    {
        $product = Product::findOrFail($productId);
        $image = ImageLibrary::findOrFail($imageId);
        
        return ImageLibraryService::setMainImageByImage($image, $product);
    }

    /**
     * Update sort order for product images
     */
    public static function updateSort(int $productId, array $sortData): bool
    {
        $product = Product::findOrFail($productId);
        
        return ImageLibraryService::updateSort($product, $sortData);
    }
}
