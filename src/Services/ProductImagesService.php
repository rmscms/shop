<?php
// git-trigger
namespace RMS\Shop\Services;

use RMS\Shop\Models\Product;
use RMS\Shop\Models\ProductCombination;
use RMS\Shop\Models\ProductCombinationImage;
use RMS\Shop\Models\ProductCombinationValue;

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
}
