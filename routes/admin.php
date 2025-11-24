<?php

use Illuminate\Support\Facades\Route;
use RMS\Core\Helpers\RouteHelper;
use RMS\Shop\Http\Controllers\Admin\ShopDashboardController;
use RMS\Shop\Http\Controllers\Admin\CategoriesController;
use RMS\Shop\Http\Controllers\Admin\ProductsController;
use RMS\Shop\Http\Controllers\Admin\OrdersController;
use RMS\Shop\Http\Controllers\Admin\CartsController;
use RMS\Shop\Http\Controllers\Admin\CurrenciesController;
use RMS\Shop\Http\Controllers\Admin\CurrencyRatesController;
use RMS\Shop\Http\Controllers\Admin\ProductFeatureCategoriesController;
use RMS\Shop\Http\Controllers\Admin\ProductFeatureLookupController;
use RMS\Shop\Http\Controllers\Admin\ProductAttributeLookupController;
use RMS\Shop\Http\Controllers\Admin\ProductPurchaseStatsController;
use RMS\Shop\Http\Controllers\Admin\UserPointsController;
use RMS\Shop\Http\Controllers\Admin\ShopSettingsController;
use RMS\Shop\Http\Controllers\Admin\EditorUploadController;
use RMS\Shop\Http\Controllers\Admin\ImageLibraryController;
use RMS\Shop\Http\Controllers\Admin\VideoLibraryController;
use RMS\Shop\Http\Controllers\Admin\AvifController;

Route::middleware([
        'web',
        class_exists(\RMS\Core\Middleware\AdminAuthenticate::class)
            ? \RMS\Core\Middleware\AdminAuthenticate::class
            : 'auth:admin',
    ])
    ->prefix('admin/shop')
    ->name('admin.shop.')
    ->group(function () {
        
        // Dashboard
        Route::get('/', [ShopDashboardController::class, 'index'])->name('dashboard');
        Route::get('/system-requirements', [ShopDashboardController::class, 'systemRequirements'])->name('system-requirements');
        
        // CKEditor Upload
        Route::post('editor/upload', [EditorUploadController::class, 'upload'])->name('editor.upload');
        
        // Settings
        Route::get('settings', [ShopSettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [ShopSettingsController::class, 'update'])->name('settings.update');
        
        // Categories
        Route::resource('categories', CategoriesController::class);
       

        // Category tree routes
        Route::get('categories/tree', [CategoriesController::class, 'tree'])->name('categories.tree');
        Route::get('categories/tree/data', [CategoriesController::class, 'treeData'])->name('categories.tree.data');
        // Products
        Route::resource('products', ProductsController::class);
       

        // Product custom routes
        Route::post('products/{product}/save-combinations', [ProductsController::class, 'saveCombinations'])->name('products.save-combinations');
        Route::post('products/{product}/basic/update', [ProductsController::class, 'updateBasicAjax'])->name('products.basic.update');
        Route::post('products/{product}/pricing/update', [ProductsController::class, 'updatePricingAjax'])->name('products.pricing.update');
        Route::post('products/{product}/save-features', [ProductsController::class, 'saveFeatures'])->name('products.save-features');

        // Product images (specific routes before parametric ones)
        Route::get('products/{product}/images/list', [ProductsController::class, 'listImages'])->name('products.images.list');
        Route::post('products/{product}/images/upload', [ProductsController::class, 'uploadImage'])->name('products.upload-image');
        Route::delete('products/{product}/images/detach', [ProductsController::class, 'detachImages'])->name('products.images.detach');
        Route::delete('products/{product}/images/{image}', [ProductsController::class, 'deleteImage'])->name('products.delete-image');

        // Legacy gallery routes (for compatibility)
        Route::post('products/{product}/ajax_upload_gallery', [ProductsController::class, 'ajaxUpload'])->name('products.ajax_upload_gallery');
        Route::delete('products/{product}/ajax_delete_gallery', [ProductsController::class, 'ajaxDeleteFile'])->name('products.ajax_delete_gallery');
        Route::post('products/{product}/regenerate-avif', [ProductsController::class, 'regenerateAvif'])->name('products.regenerate-avif');

        // Product video
        Route::post('products/{product}/video/chunk-init', [ProductsController::class, 'videoChunkInit'])->name('products.video.chunk-init');
        Route::post('products/{product}/video/chunk', [ProductsController::class, 'videoChunk'])->name('products.video.chunk');
        Route::post('products/{product}/video/upload', [ProductsController::class, 'uploadVideo'])->name('products.upload-video');
        Route::delete('products/{product}/video/{video}', [ProductsController::class, 'deleteVideo'])->name('products.delete-video');

        // Product features
        Route::post('products/features/create-category', [ProductFeatureLookupController::class, 'storeCategory'])->name('products.features.create-category');
        Route::get('products/features/categories/search', [ProductFeatureLookupController::class, 'categories'])->name('products.features.categories.search');
        Route::get('products/features/search', [ProductFeatureLookupController::class, 'features'])->name('products.features.search');
        Route::get('products/features/values/search', [ProductFeatureLookupController::class, 'values'])->name('products.features.values.search');
        Route::post('products/features/definitions', [ProductFeatureLookupController::class, 'storeFeature'])->name('products.features.definitions.store');
        Route::post('products/features/values', [ProductFeatureLookupController::class, 'storeValue'])->name('products.features.values.store');

        // Product attributes
        Route::get('products/attributes/definitions/search', [ProductAttributeLookupController::class, 'definitions'])->name('products.attributes.definitions.search');
        Route::get('products/attributes/values/search', [ProductAttributeLookupController::class, 'values'])->name('products.attributes.values.search');
        Route::post('products/attributes/definitions', [ProductAttributeLookupController::class, 'storeDefinition'])->name('products.attributes.definitions.store');
        Route::post('products/attributes/values', [ProductAttributeLookupController::class, 'storeValue'])->name('products.attributes.values.store');
        // Orders (limited to index, show only)
        Route::resource('orders', OrdersController::class, [
            'export' => true,
            'sort' => false,
            'toggle_active' => false,
            'batch_actions' => [],
        ]);
      

        // Order custom routes
        Route::get('orders/{order}/whatsapp', [OrdersController::class, 'whatsapp'])->name('orders.whatsapp');
        Route::get('orders/{order}/invoice', [OrdersController::class, 'invoice'])->name('orders.invoice');
        Route::get('orders/{order}/label', [OrdersController::class, 'label'])->name('orders.label');
        Route::post('orders/{order}/discount', [OrdersController::class, 'applyDiscount'])->name('orders.discount');
        Route::post('orders/{order}/apply-points', [OrdersController::class, 'applyPoints'])->name('orders.apply-points');
        Route::post('orders/{order}/tracking/update', [OrdersController::class, 'updateTracking'])->name('orders.tracking.update');
        Route::post('orders/{order}/update-status', [OrdersController::class, 'updateStatus'])->name('orders.update-status');
        Route::post('orders/{order}/charge', [OrdersController::class, 'charge'])->name('orders.charge');
        Route::post('orders/{order}/notes/add', [OrdersController::class, 'addNote'])->name('orders.notes.add');
        Route::post('orders/{order}/notes/{note}/update', [OrdersController::class, 'updateNote'])->name('orders.notes.update');
        Route::post('orders/{order}/notes/{note}/delete', [OrdersController::class, 'deleteNote'])->name('orders.notes.delete');
        // Basic resources
        Route::resource('carts', CartsController::class)->only(['index', 'show', 'destroy']);

        Route::resource('currencies', CurrenciesController::class);
        Route::resource('currency-rates', CurrencyRatesController::class);

        Route::resource('product-feature-categories', ProductFeatureCategoriesController::class);
        Route::resource('product-purchase-stats', ProductPurchaseStatsController::class)->only(['index']);

        Route::resource('user-points', UserPointsController::class)->only(['index']);

        // Image Library
        Route::resource('image-library', ImageLibraryController::class);
        Route::post('image-library/{id}/assign', [ImageLibraryController::class, 'assign'])->name('image-library.assign');
        Route::post('image-library/{id}/detach', [ImageLibraryController::class, 'detach'])->name('image-library.detach');
        Route::post('image-library/{id}/generate-avif', [ImageLibraryController::class, 'generateAvif'])->name('image-library.generate-avif');
        Route::get('image-library', [ImageLibraryController::class, 'index'])->name('image-library.index');
        Route::post('image-library', [ImageLibraryController::class, 'store'])->name('image-library.store');
        Route::get('image-library/{image}', [ImageLibraryController::class, 'show'])->name('image-library.show');
        Route::put('image-library/{image}', [ImageLibraryController::class, 'update'])->name('image-library.update');
        Route::delete('image-library/{image}', [ImageLibraryController::class, 'destroy'])->name('image-library.destroy');
        Route::get('products/{product}/image-library/images', [ImageLibraryController::class, 'getProductImages'])->name('products.image-library.images');
        Route::post('products/{product}/image-library/assign', [ImageLibraryController::class, 'assignToProduct'])->name('products.image-library.assign');
        Route::get('products/{product}/images/{image}/assigned-combinations', [ImageLibraryController::class, 'getAssignedCombinations'])->name('products.images.assigned-combinations');
        Route::post('products/{product}/images/{image}/assign-combinations', [ImageLibraryController::class, 'assignToCombinations'])->name('products.images.assign-combinations');
        Route::delete('products/{product}/images/{image}/detach-combinations', [ImageLibraryController::class, 'detachFromCombinations'])->name('products.images.detach-combinations');
        Route::delete('products/{product}/image-library/detach', [ImageLibraryController::class, 'detachFromProduct'])->name('products.image-library.detach');
        Route::post('products/{product}/image-library/set-main', [ImageLibraryController::class, 'setMainImage'])->name('products.image-library.set-main');
        Route::post('products/{product}/image-library/sort', [ImageLibraryController::class, 'updateSort'])->name('products.image-library.sort');

        // AVIF Manager
        Route::prefix('avif')->name('avif.')->group(function () {
            Route::get('/', [AvifController::class, 'index'])->name('index');
            Route::get('/stats', [AvifController::class, 'stats'])->name('stats');
            Route::post('/regenerate-all', [AvifController::class, 'regenerateAll'])->name('regenerate-all');
            Route::post('/clean-all', [AvifController::class, 'cleanAll'])->name('clean-all');
            Route::post('/regenerate-directory', [AvifController::class, 'regenerateDirectory'])->name('regenerate-directory');
            Route::post('/upload-convert', [AvifController::class, 'uploadAndConvert'])->name('upload-convert');
        });

        // Video Library
        Route::resource('video-library', VideoLibraryController::class);
        Route::post('video-library/{id}/assign', [VideoLibraryController::class, 'assign'])->name('video-library.assign');
        Route::post('video-library/{id}/detach', [VideoLibraryController::class, 'detach'])->name('video-library.detach');

        // Product videos (specific routes before parametric ones)
        Route::get('products/{product}/videos/list', [ProductsController::class, 'listVideos'])->name('products.videos.list');
        Route::get('products/{product}/videos/library', [ProductsController::class, 'getVideoLibrary'])->name('products.videos.library');
        Route::post('products/{product}/videos/assign', [ProductsController::class, 'assignVideos'])->name('products.videos.assign');
        Route::delete('products/{product}/videos/detach', [ProductsController::class, 'detachVideos'])->name('products.videos.detach');
        Route::post('products/{product}/videos/set-main', [ProductsController::class, 'setMainVideo'])->name('products.videos.set-main');
        Route::post('products/{product}/videos/sort', [ProductsController::class, 'sortVideos'])->name('products.videos.sort');
    });

