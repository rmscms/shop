<?php
// git-trigger
namespace RMS\Shop\Http\Controllers\Admin\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RMS\Shop\Jobs\ConvertImageToAvif;
use RMS\Shop\Models\ProductImage;
use RMS\Shop\Models\ProductCombinationImage;
use RMS\Shop\Models\ProductCombination;
use RMS\Shop\Models\Product;
use RMS\Shop\Models\ImageLibrary;
use RMS\Shop\Models\ImageAssignment;
use RMS\Shop\Services\ImageLibraryService;

trait ProductImagesTrait
{
    // JSON list of images (updated to use Image Library)
    public function listImages(Request $request, int $productId)
    {
        $combinationId = $request->query('combination_id');

        try {
            if ($combinationId) {
                // Validate combination belongs to product
                $combination = ProductCombination::where('id', (int)$combinationId)
                    ->where('product_id', (int)$productId)
                    ->first();

                if (!$combination) {
                    return response()->json(['ok' => false, 'error' => 'combination_not_found'], 404);
                }

                $images = ImageLibraryService::getCombinationImages($combination);
            } else {
                $product = Product::findOrFail($productId);
                $images = ImageLibraryService::getProductImages($product);
            }

            $rows = $images->map(function($image) {
                return [
                    'id' => $image->pivot->id, // Assignment ID
                    'image_id' => $image->id, // Image Library ID
                    'path' => $image->path,
                    'url' => $image->url,
                    'avif_url' => $image->avif_url,
                    'filename' => $image->filename,
                    'is_main' => $image->pivot->is_main,
                    'sort' => $image->pivot->sort,
                ];
            });

            return response()->json(['ok' => true, 'data' => $rows]);

        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function uploadImage(Request $request, int $productId, ?int $combination = null)
    {
        // Validate combination belongs to product if provided
        if ($combination) {
            $existsComb = ProductCombination::query()->where(['id'=>(int)$combination,'product_id'=>(int)$productId])->exists();
            if (!$existsComb) { return response()->json(['success'=>false,'message'=>'combination_not_found'], 404); }
        }

        $rules = [];
        $filesArr = $request->allFiles();
        $addRules = function($value, $prefix) use (&$addRules, &$rules) {
            if (is_array($value)) {
                foreach ($value as $k => $v) { $addRules($v, $prefix.($prefix!==''?'.':'').$k); }
            } else if ($value instanceof UploadedFile) {
                $rules[$prefix] = ['file','image','mimes:jpg,jpeg,png,webp,avif','max:5120'];
            }
        };
        foreach ($filesArr as $key => $value) { $addRules($value, $key); }
        if (!empty($rules)) { Validator::make($request->all(), $rules)->validate(); }

        // Collect first uploaded file
        $filesArr = $request->allFiles();
        $collected = [];
        $collect = function($v) use (&$collect, &$collected) {
            if (is_array($v)) { foreach ($v as $vv) { $collect($vv); } }
            else if ($v instanceof UploadedFile) { $collected[] = $v; }
        };
        foreach ($filesArr as $v) { $collect($v); }
        $file = $collected[0] ?? null;
        if (!$file) { return response()->json(['success'=>false,'message'=>'no_file'], 400); }

        try {
            // Upload to Image Library (this handles AVIF conversion)
            $imageLibrary = ImageLibraryService::uploadImage($file);

            // Get the target model (Product or ProductCombination)
            $targetModel = $combination
                ? ProductCombination::findOrFail($combination)
                : Product::findOrFail($productId);

            // Assign the image to the target
            $assignment = ImageLibraryService::assignImage($imageLibrary, $targetModel);

            // Return the AVIF URL if available, otherwise original
            $imageUrl = $imageLibrary->avif_url ?: $imageLibrary->url;

            return response()->json([
                'ok' => true,
                'id' => $imageLibrary->id,
                'assignment_id' => $assignment->id,
                'path' => $imageUrl
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در آپلود تصویر: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detach image from combination
     */
    public function detachImages(Request $request, int $productId)
    {
        $combinationId = $request->input('combination_id');
        $imageId = $request->input('image_id'); // Image Library ID
        $assignmentId = $request->input('combination_image_id'); // Assignment ID (pivot)

        try {
            if ($assignmentId) {
                // Delete by assignment ID (faster and more accurate)
                ImageAssignment::where('id', (int)$assignmentId)->delete();
            } elseif ($combinationId && $imageId) {
                // Fallback: Delete by combination + image
                ImageAssignment::query()
                    ->where('assignable_type', ProductCombination::class)
                    ->where('assignable_id', (int)$combinationId)
                    ->where('image_id', (int)$imageId)
                    ->delete();
            } else {
                return response()->json(['ok' => false, 'error' => 'missing_parameters'], 400);
            }

            // Clear cache
            $repo = Product::cacheRepoForId((int)$productId);
            $repo->forget('shop:product:image-counts:' . (int)$productId);

            return response()->json(['ok' => true, 'message' => 'Image detached successfully']);

        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function deleteImage(Request $request, int $productId, $imageId)
    {
        $imageId = (int) $imageId; // Cast to int
        
        try {
            // Find the image in library
            $imageLibrary = ImageLibrary::findOrFail($imageId);

            // Check if this is a product-specific delete (assignment ID) or library delete
            // For now, since we can't delete from library in product context, just detach
            $product = Product::findOrFail($productId);

            // Detach image from product (this will remove the assignment)
            $detached = ImageLibraryService::detachImage($imageLibrary, $product);

            if ($detached) {
                return response()->json(['ok' => true, 'message' => 'تصویر از محصول جدا شد']);
            } else {
                return response()->json(['ok' => false, 'error' => 'تصویر یافت نشد'], 404);
            }

        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function assignImage(Request $request, int $productId)
    {
        $data = $request->validate([
            'combination_id' => ['required','integer','exists:product_combinations,id'],
            'image_id' => ['nullable','integer'],
            'file_path' => ['nullable','string'],
        ]);
        // Ensure combination belongs to product
        $comb = ProductCombination::query()->where('id', (int)$data['combination_id'])->where('product_id', (int)$productId)->first();
        if (!$comb) { return response()->json(['ok'=>false,'message'=>'combination_not_found'], 404); }
        $combId = (int)$comb->id;
        // Resolve path either by image_id or by file_path
        $path = null;
        if (!empty($data['image_id'])) {
            $row = ProductImage::query()->where('id', (int)$data['image_id'])->where('product_id', (int)$productId)->first();
            if ($row) { $path = $row->path; }
        }
        if (!$path && !empty($data['file_path'])) {
            $row = ProductImage::query()->where('product_id', (int)$productId)->where('path', (string)$data['file_path'])->first();
            if ($row) { $path = $row->path; }
        }
        if (!$path) {
            return response()->json(['ok'=>false,'message'=>'image_not_found'], 404);
        }

        // Use service for attach + label
        $res = \RMS\Shop\Services\ProductImagesService::assignToCombination((int)$productId, $combId, $path);
        $count = ProductCombinationImage::query()->where('combination_id', $combId)->count();
        return response()->json([
            'ok'=>true,
            'message'=>trans('admin.file_uploaded'),
            'combination_id'=>$combId,
            'file_path'=>$path,
            'label'=>$res['label'] ?? null,
            'images_count' => (int)$count,
        ]);
    }

    public function detachImage(Request $request, int $productId)
    {
        $data = $request->validate([
            'combination_id' => ['required','integer','exists:product_combinations,id'],
            'combination_image_id' => ['nullable','integer','exists:product_combination_images,id'],
            'image_id' => ['nullable','integer'],
            'file_path' => ['nullable','string'],
        ]);
        // Ensure combination belongs to product
        $comb = ProductCombination::query()->where('id', (int)$data['combination_id'])->where('product_id', (int)$productId)->first();
        if (!$comb) { return response()->json(['ok'=>false,'message'=>'combination_not_found'], 404); }
        $combId = (int)$comb->id;

        $ok = \RMS\Shop\Services\ProductImagesService::detachFromCombination((int)$productId, $combId, (int)($data['combination_image_id'] ?? 0) ?: null, (int)($data['image_id'] ?? 0) ?: null, $data['file_path'] ?? null);
        return $ok ? response()->json(['ok'=>true]) : response()->json(['ok'=>false,'message'=>'not_found'], 404);
    }

    public function setMainImage(Request $request, int $productId)
    {
        $data = $request->validate([
            'scope' => ['required','in:product,combination'],
            'assignment_id' => ['required','integer'], // Now using assignment ID
            'combination_id' => ['nullable','integer','exists:product_combinations,id']
        ]);

        try {
            if ($data['scope'] === 'product') {
                $product = Product::findOrFail($productId);
                $result = ImageLibraryService::setMainImage($product, (int)$data['assignment_id']);
            } else {
                // combination scope
                $combId = (int)($data['combination_id'] ?? 0);
                if ($combId <= 0) {
                    return response()->json(['ok' => false, 'message' => 'combination_required'], 422);
                }

                $combination = ProductCombination::where('id', $combId)
                    ->where('product_id', $productId)
                    ->firstOrFail();

                $result = ImageLibraryService::setMainImage($combination, (int)$data['assignment_id']);
            }

            return response()->json(['ok' => $result]);

        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function sortImages(Request $request, int $productId)
    {
        $data = $request->validate([
            'scope' => ['required','in:product,combination'],
            'items' => ['required','array','min:1'],
            'items.*.id' => ['required','integer'], // Now assignment IDs
            'items.*.sort' => ['required','integer','min:0'],
            'combination_id' => ['nullable','integer','exists:product_combinations,id']
        ]);

        try {
            if ($data['scope'] === 'product') {
                $product = Product::findOrFail($productId);
                $result = ImageLibraryService::updateSort($product, $data['items']);
            } else {
                $combId = (int)($data['combination_id'] ?? 0);
                if ($combId <= 0) {
                    return response()->json(['ok' => false, 'message' => 'combination_required'], 422);
                }

                $combination = ProductCombination::where('id', $combId)
                    ->where('product_id', $productId)
                    ->firstOrFail();

                $result = ImageLibraryService::updateSort($combination, $data['items']);
            }

            return response()->json(['ok' => $result]);

        } catch (\Exception $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // image-uploader plugin endpoints (RouteHelper::ajaxFileRoutes)
    public function ajaxUpload(Request $request, $id, string $fieldName)
    {
        $rules = [];
        $filesArr = $request->allFiles();
        $addRules = function($value, $prefix) use (&$addRules, &$rules) {
            if (is_array($value)) {
                foreach ($value as $k => $v) { $addRules($v, $prefix.($prefix!==''?'.':'').$k); }
            } else if ($value instanceof UploadedFile) {
                $rules[$prefix] = ['file','image','mimes:jpg,jpeg,png,webp,avif','max:5120'];
            }
        };
        foreach ($filesArr as $key => $value) { $addRules($value, $key); }
        if (!empty($rules)) { Validator::make($request->all(), $rules)->validate(); }

        $productId = (int)$id;
        $combId = null;
        if (preg_match('/^gallery__(?:comb_(\d+))$/', $fieldName, $m)) { $combId = (int)($m[1] ?? 0) ?: null; }

        // Validate combination belongs to product when present
        if ($combId) {
            $okComb = ProductCombination::query()->where(['id'=>$combId,'product_id'=>$productId])->exists();
            if (!$okComb) { return response()->json(['success'=>false,'message'=>'combination_not_found'], 404); }
        }

        // Get target model
        $targetModel = $combId
            ? ProductCombination::findOrFail($combId)
            : Product::findOrFail($productId);

        // Process uploaded files
        $files = [];
        $flatten = function($v) use (&$flatten, &$files) {
            if (is_array($v)) { foreach ($v as $vv) { $flatten($vv); } }
            else if ($v instanceof UploadedFile) { $files[] = $v; }
        };
        foreach ($request->allFiles() as $value) { $flatten($value); }

        $uploaded = [];
        $assignments = [];

        foreach ($files as $file) {
            try {
                // Upload to Image Library (handles AVIF conversion)
                $imageLibrary = ImageLibraryService::uploadImage($file);

                // Assign to target model
                $assignment = ImageLibraryService::assignImage($imageLibrary, $targetModel);

                // Use AVIF URL if available, otherwise original
                $imageUrl = $imageLibrary->avif_url ?: $imageLibrary->url;

                $uploaded[] = $imageUrl;
                $assignments[] = [
                    'image_id' => $imageLibrary->id,
                    'assignment_id' => $assignment->id,
                    'url' => $imageUrl
                ];

            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'خطا در آپلود تصویر: ' . $e->getMessage()
                ], 500);
            }
        }

        return response()->json([
            'success' => true,
            'message' => trans('admin.file_uploaded') ?? 'آپلود انجام شد',
            'uploaded_files' => $uploaded,
            'file_info' => array_map(fn($url) => ['url' => $url], $uploaded),
            'assignments' => $assignments,
            'field' => $fieldName
        ]);
    }

    public function ajaxDeleteFile(Request $request, $id, $fieldName)
    {
        $productId = (int)$id;
        $path = (string)$request->query('file_path','');
        if (!$path) return response()->json(['success'=>false,'message'=>'file_path empty'], 400);
        // convert public URL to storage path if needed
        if (str_starts_with($path, url('/storage'))) {
            $rel = str_replace(url('/storage').'/', '', $path);
        } else if (str_starts_with($path, '/storage/')) {
            $rel = substr($path, 9);
        } else {
            $rel = $path; // assume relative storage path
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
        foreach ($files as $f) { if (str_ends_with($f, $suffix)) { Storage::disk('public')->delete($f); } }
        if ($scope==='product') {
            ProductImage::query()->where(['product_id'=>$productId,'path'=>$rel])->delete();
        } else {
            $combIds = ProductCombination::query()->where('product_id', (int)$productId)->pluck('id');
            ProductCombinationImage::query()->whereIn('combination_id', $combIds)->where('path',$rel)->delete();
        }
        return response()->json(['success'=>true,'message'=>trans('admin.file_deleted') ?? 'حذف انجام شد']);
    }

    // Regenerate AVIF variants for all images of a product (async)
    public function regenerateAvif(Request $request, int $productId)
    {
        $product = Product::query()->find((int)$productId);
        abort_if(!$product, 404);

        try {
            $results = [
                'product_images' => 0,
                'combination_images' => 0,
                'converted' => 0,
                'failed' => 0,
                'errors' => []
            ];

            $productImages = $product->images()->get(['path']);
            foreach ($productImages as $img) {
                $results['product_images']++;
                ConvertImageToAvif::dispatch($img->path, 70)->delay(now()->addSeconds(2));
                $results['converted']++;
            }

            $combinationImages = $product->combinationImages()->get(['path']);
            foreach ($combinationImages as $img) {
                $results['combination_images']++;
                ConvertImageToAvif::dispatch($img->path, 70)->delay(now()->addSeconds(2));
                $results['converted']++;
            }

            return response()->json(['success'=>true,'message'=>"بازسازی آغاز شد! {$results['converted']} تصویر در صف قرار گرفت.",'data'=>$results]);
        } catch (\Throwable $e) {
            Log::error('Error regenerating product AVIF files', ['product_id'=>$productId,'error'=>$e->getMessage()]);
            return response()->json(['success'=>false,'message'=>'خطا در بازسازی: '.$e->getMessage()], 500);
        }
    }
}
