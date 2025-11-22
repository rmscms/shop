<?php
// git-trigger
namespace RMS\Shop\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use RMS\Core\Contracts\Actions\ChangeBoolField;
use RMS\Core\Contracts\Filter\ShouldFilter;
use RMS\Core\Contracts\Form\HasForm;
use RMS\Core\Contracts\List\HasList;
use RMS\Core\Controllers\Admin\AdminController;
use RMS\Core\Data\Field;
use RMS\Shop\Models\ImageLibrary;
use RMS\Shop\Models\Product;
use RMS\Shop\Services\ImageLibraryService;

class ImageLibraryController extends AdminController implements HasList, HasForm, ShouldFilter
{
    public function table(): string
    {
        return 'image_library';
    }

    public function modelName(): string
    {
        return ImageLibrary::class;
    }

    public function baseRoute(): string
    {
        return 'image-library';
    }

    public function routeParameter(): string
    {
        return 'image';
    }

    public function getFieldsForm(): array
    {
        return [
            Field::string('filename', trans('shop.image_library.filename'))->readonly(),
            Field::string('path', trans('shop.image_library.path'))->readonly(),
            Field::string('mime_type', trans('shop.image_library.mime_type'))->readonly(),
            Field::number('size_bytes', trans('shop.image_library.size'))->readonly(),
        ];
    }

    public function getListFields(): array
    {
        return [
            Field::string('id', 'ID')->sortable(),
            Field::string('filename', trans('shop.image_library.filename'))->sortable(),
            Field::image('path', trans('shop.image_library.image'))->thumbnail(50, 50),
            Field::string('formatted_size', trans('shop.image_library.size')),
            Field::string('mime_type', trans('shop.image_library.mime_type')),
            Field::number('assignments_count', trans('shop.image_library.usage_count')),
            Field::date('created_at', trans('common.created_at'))->sortable(),
        ];
    }

    protected function beforeRenderView(): void
    {
        parent::beforeRenderView();
        $this->view
            ->withCss('shop/image-library.css')
            ->withJs('shop/image-library.js')
            ->withJsVariables([
                'imageLibraryRoutes' => [
                    'upload' => route('admin.shop.image-library.upload'),
                    'destroy' => 'javascript:void(0)', // Will be set per image
                ]
            ]);
    }

    /**
     * Display image library index
     */
    public function index(Request $request)
    {
        $this->title = __('shop.image_library.title');

        $search = $request->get('search');
        $images = ImageLibraryService::searchImages($search);

        $this->view->usePackageNamespace('shop')
            ->setTheme('admin')
            ->setTpl('image-library.index')
            ->withCss('shop/image-library.css')
            ->withJs('shop/image-library.js')
            ->withVariables(compact('images', 'search'))
            ->withJsVariables([
                'ImageLibraryConfig' => [
                    'csrf' => csrf_token(),
                    'routes' => [
                        'upload' => route('admin.shop.image-library.upload'),
                        'destroy' => route('admin.shop.image-library.ajax-destroy', '__ID__'),
                        'generateAvif' => route('admin.shop.image-library.generate-avif', '__ID__'),
                    ],
                ],
            ]);

        return $this->view();
    }

    public function filters(): array
    {
        return [
            Field::string('filename', trans('shop.image_library.filename')),
            Field::select('mime_type', trans('shop.image_library.mime_type'))
                ->setOptions([
                    'image/jpeg' => 'JPEG',
                    'image/png' => 'PNG',
                    'image/webp' => 'WebP',
                    'image/avif' => 'AVIF',
                ]),
        ];
    }

    /**
     * Upload image to library
     */
    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,avif', 'max:5120'],
        ]);

        try {
            $file = $request->file('image');
            $image = ImageLibraryService::uploadImage($file);

            return response()->json([
                'success' => true,
                'message' => 'تصویر با موفقیت آپلود شد',
                'data' => [
                    'id' => $image->id,
                    'filename' => $image->filename,
                    'url' => $image->url,
                    'avif_url' => $image->avif_url,
                    'size_formatted' => $image->formatted_size,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در آپلود تصویر: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Override destroy to handle image deletion with custom logic
     */
    public function destroy(\Illuminate\Http\Request $request, int|string $id): \Illuminate\Http\RedirectResponse
    {
        try {
            $image = ImageLibrary::findOrFail($id);

            if (!$image->canBeDeleted()) {
                return back()->withErrors('این تصویر قابل حذف نیست زیرا در محصولاتی استفاده شده است');
            }

            $deleted = ImageLibraryService::deleteImage($image);

            if ($deleted) {
                return back()->with('success', 'تصویر با موفقیت حذف شد');
            } else {
                return back()->withErrors('خطا در حذف تصویر');
            }
        } catch (\Exception $e) {
            return back()->withErrors('خطا در حذف تصویر: ' . $e->getMessage());
        }
    }

    /**
     * Delete an image via AJAX
     */
    public function ajaxDestroy($id): JsonResponse
    {
        try {
            $image = ImageLibrary::findOrFail($id);

            if (!$image->canBeDeleted()) {
                return response()->json([
                    'success' => false,
                    'message' => 'این تصویر قابل حذف نیست زیرا در محصولاتی استفاده شده است'
                ], 422);
            }

            $deleted = ImageLibraryService::deleteImage($image);

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'تصویر با موفقیت حذف شد'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'خطا در حذف تصویر'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در حذف تصویر: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate AVIF for an image
     */
    public function generateAvif($id): JsonResponse
    {
        try {
            $image = ImageLibrary::findOrFail($id);

            // Dispatch AVIF conversion job
            \RMS\Shop\Jobs\ConvertImageToAvif::dispatch($image->path, 85);

            return response()->json([
                'success' => true,
                'message' => 'ساخت AVIF در صف قرار گرفت و به زودی انجام می‌شود'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در ساخت AVIF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get images by product for selection
     */
    /**
     * Get images for product modal (with pagination and smart search)
     */
    public function getProductImages(Product $product, Request $request): JsonResponse
    {
        // Get search query and page
        $search = $request->get('search', '');
        $page = $request->get('page', 1);
        $perPage = 20;

        // Get paginated images from library
        $paginatedImages = ImageLibraryService::searchImages($search, $perPage);

        // Get already assigned image IDs for this product
        $assignedIds = $product->assignedImages()->pluck('image_library.id')->toArray();

        return response()->json([
            'success' => true,
            'data' => $paginatedImages->map(function ($image) use ($assignedIds) {
                return [
                    'id' => $image->id,
                    'filename' => $image->filename,
                    'url' => $image->url,
                    'avif_url' => $image->avif_url,
                    'is_assigned' => in_array($image->id, $assignedIds),
                    'assignments_count' => $image->assignments_count,
                ];
            }),
            'pagination' => [
                'current_page' => $paginatedImages->currentPage(),
                'last_page' => $paginatedImages->lastPage(),
                'per_page' => $paginatedImages->perPage(),
                'total' => $paginatedImages->total(),
                'has_more' => $paginatedImages->hasMorePages(),
            ]
        ]);
    }

    /**
     * Assign images to product (multiple images at once)
     */
    public function assignToProduct(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'image_ids' => ['required', 'array'],
            'image_ids.*' => ['required', 'integer', 'exists:image_library,id'],
        ]);

        try {
            $assignedCount = 0;
            
            foreach ($request->image_ids as $imageId) {
                $image = ImageLibrary::findOrFail($imageId);
                ImageLibraryService::assignImage($image, $product);
                $assignedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => "تعداد {$assignedCount} تصویر به محصول اختصاص یافت",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اختصاص تصویر: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get assigned combinations for an image
     */
    public function getAssignedCombinations(Product $product, ImageLibrary $image): JsonResponse
    {
        try {
            $assignedCombinationIds = \RMS\Shop\Models\ImageAssignment::query()
                ->where('image_id', $image->id)
                ->where('assignable_type', \RMS\Shop\Models\ProductCombination::class)
                ->whereIn('assignable_id', function($query) use ($product) {
                    $query->select('id')
                        ->from('product_combinations')
                        ->where('product_id', $product->id);
                })
                ->pluck('assignable_id')
                ->toArray();

            return response()->json([
                'success' => true,
                'data' => $assignedCombinationIds
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در دریافت ترکیبات: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign image to multiple combinations
     */
    public function assignToCombinations(Request $request, Product $product, ImageLibrary $image): JsonResponse
    {
        $request->validate([
            'combination_ids' => ['required', 'array'],
            'combination_ids.*' => ['required', 'integer', 'exists:product_combinations,id'],
        ]);

        try {
            // Verify combinations belong to this product
            $validCombinations = \RMS\Shop\Models\ProductCombination::query()
                ->where('product_id', $product->id)
                ->whereIn('id', $request->combination_ids)
                ->pluck('id');

            if ($validCombinations->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'هیچ ترکیب معتبری یافت نشد'
                ], 400);
            }

            // First, detach image from all combinations of this product
            \RMS\Shop\Models\ImageAssignment::query()
                ->where('image_id', $image->id)
                ->where('assignable_type', \RMS\Shop\Models\ProductCombination::class)
                ->whereIn('assignable_id', function($query) use ($product) {
                    $query->select('id')
                        ->from('product_combinations')
                        ->where('product_id', $product->id);
                })
                ->delete();

            // Then assign to selected combinations
            foreach ($validCombinations as $combinationId) {
                $combination = \RMS\Shop\Models\ProductCombination::find($combinationId);
                ImageLibraryService::assignImage($image, $combination);
            }

            return response()->json([
                'success' => true,
                'message' => "تصویر به {$validCombinations->count()} ترکیب اختصاص یافت",
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اختصاص به ترکیبات: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detach image from all combinations
     */
    public function detachFromCombinations(Product $product, ImageLibrary $image): JsonResponse
    {
        try {
            // Remove image from all combinations of this product
            $deletedCount = \RMS\Shop\Models\ImageAssignment::query()
                ->where('image_id', $image->id)
                ->where('assignable_type', \RMS\Shop\Models\ProductCombination::class)
                ->whereIn('assignable_id', function($query) use ($product) {
                    $query->select('id')
                        ->from('product_combinations')
                        ->where('product_id', $product->id);
                })
                ->delete();

            return response()->json([
                'success' => true,
                'message' => "تصویر از {$deletedCount} ترکیب جدا شد",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در جداسازی: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Detach image from product
     */
    public function detachFromProduct(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'image_id' => ['required', 'integer', 'exists:image_library,id'],
        ]);

        try {
            $image = ImageLibrary::findOrFail($request->image_id);
            $detached = ImageLibraryService::detachImage($image, $product);

            if ($detached) {
                return response()->json([
                    'success' => true,
                    'message' => 'تصویر از محصول جدا شد'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'تصویر یافت نشد'
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در جدا کردن تصویر: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set main image for product
     */
    public function setMainImage(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'image_id' => ['required', 'integer', 'exists:image_library,id'],
        ]);

        try {
            $image = ImageLibrary::findOrFail($request->image_id);
            ImageLibraryService::setMainImageByImage($image, $product);

            return response()->json([
                'success' => true,
                'message' => 'تصویر اصلی محصول تنظیم شد'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در تنظیم تصویر اصلی: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update sort order for product images
     */
    public function updateSort(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'image_ids' => ['required', 'array'],
            'image_ids.*' => ['integer', 'exists:image_library,id'],
        ]);

        try {
            ImageLibraryService::updateSortOrder($product, $request->image_ids);

            return response()->json([
                'success' => true,
                'message' => 'ترتیب تصاویر به‌روزرسانی شد'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در به‌روزرسانی ترتیب: ' . $e->getMessage()
            ], 500);
        }
    }
}
