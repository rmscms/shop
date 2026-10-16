<?php

use Illuminate\Support\Facades\Route;
use RMS\Shop\Http\Controllers\Api\Admin\BrandController;
use RMS\Shop\Http\Controllers\Api\Admin\ProductController;
use RMS\Shop\Http\Controllers\Api\Admin\CategoryController;
use RMS\Shop\Http\Controllers\Api\Admin\ImageLibraryController;
use RMS\Shop\Http\Controllers\Api\Admin\ImageSizeController;
use RMS\Shop\Http\Controllers\Api\Admin\ProductCombinationController;
use RMS\Shop\Http\Controllers\Api\Admin\ProductFeatureController;
use RMS\Shop\Http\Controllers\Api\Admin\OrderController;
use RMS\Shop\Http\Controllers\Api\Admin\CartController;
use RMS\Shop\Http\Controllers\Api\Admin\CurrencyController;
use RMS\Shop\Http\Controllers\Api\Admin\CurrencyRateController;
use RMS\Shop\Http\Controllers\Api\Admin\VideoLibraryController;
use RMS\Shop\Http\Controllers\Api\Admin\ProductFeatureCategoryController;
use RMS\Shop\Http\Controllers\Api\Admin\ProductFeatureLookupController;
use RMS\Shop\Http\Controllers\Api\Admin\ProductAttributeLookupController;
use RMS\Shop\Http\Controllers\Api\Admin\ProductPurchaseStatsController;
use RMS\Shop\Http\Controllers\Api\Admin\UserPointsController;
use RMS\Shop\Http\Controllers\Api\Admin\ShopSettingsController;
use RMS\Shop\Http\Controllers\Api\Admin\AvifController;
use RMS\Shop\Http\Controllers\Api\Admin\EditorUploadController;
use RMS\Shop\Http\Controllers\Api\Admin\ShopDashboardController;
use RMS\Shop\Http\Controllers\Api\Admin\AuthController;
use RMS\Shop\Http\Controllers\Api\Admin\ProductMediaController;
use RMS\Shop\Http\Middleware\AuthenticateShopApiAccess;

$prefix = trim(config('shop.admin_api.prefix', 'api/v1/admin/shop'), '/');
$middleware = config('shop.admin_api.middleware', ['api']);
$authMiddleware = [AuthenticateShopApiAccess::class];

Route::prefix($prefix)
    ->middleware($middleware)
    ->name('shop.admin_api.')
    ->group(function () use ($authMiddleware, $prefix) {
        Route::get('/', function () use ($prefix) {
            return response()->json([
                'status' => 'ok',
                'name' => 'Shop Admin API',
                'prefix' => '/'.trim($prefix, '/'),
                'auth' => 'Bearer token from admin/shop/api-accesses',
                'docs' => url(config('l5-swagger.documentations.default.routes.api', 'api/documentation')),
                'sample' => [
                    'GET' => url(trim($prefix, '/').'/auth/me'),
                    'POST' => url(trim($prefix, '/').'/auth/login'),
                ],
            ]);
        })->name('root');

        // Authentication routes (no auth required)
        Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
        
        // Protected routes (require authentication)
        Route::middleware($authMiddleware)->group(function () {
            // Auth routes (require authentication)
            Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
            Route::post('auth/logout-all', [AuthController::class, 'logoutAll'])->name('auth.logout-all');
            Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
            // Dashboard
            Route::get('dashboard', [ShopDashboardController::class, 'index'])->name('dashboard.index');
            Route::get('dashboard/system-requirements', [ShopDashboardController::class, 'systemRequirements'])->name('dashboard.system-requirements');

            // Brands
            Route::get('brands/options', [BrandController::class, 'options'])->name('brands.options');
            Route::apiResource('brands', BrandController::class);

            // Categories
            Route::get('categories/tree', [CategoryController::class, 'tree'])->name('categories.tree');
            Route::get('categories/options', [CategoryController::class, 'options'])->name('categories.options');
            Route::apiResource('categories', CategoryController::class);

            // Products
            Route::patch('products/{id}/basic', [ProductController::class, 'updateBasic'])->name('products.basic.update');
            Route::patch('products/{id}/pricing', [ProductController::class, 'updatePricing'])->name('products.pricing.update');
            Route::get('products/{id}/images', [ProductMediaController::class, 'listImages'])->name('products.images.index');
            Route::post('products/{id}/images', [ProductMediaController::class, 'uploadImage'])->name('products.images.store');
            Route::delete('products/{id}/images', [ProductMediaController::class, 'detachImages'])->name('products.images.detach');
            Route::post('products/{id}/images/set-main', [ProductMediaController::class, 'setMainImage'])->name('products.images.set-main');
            Route::post('products/{id}/images/sort', [ProductMediaController::class, 'sortImages'])->name('products.images.sort');
            Route::delete('products/{id}/images/{image}', [ProductMediaController::class, 'deleteImage'])->name('products.images.destroy');
            Route::get('products/{id}/images/{image}/combinations', [ProductMediaController::class, 'assignedCombinations'])->name('products.images.combinations');
            Route::post('products/{id}/images/{image}/combinations', [ProductMediaController::class, 'assignCombinations'])->name('products.images.combinations.assign');
            Route::delete('products/{id}/images/{image}/combinations', [ProductMediaController::class, 'detachCombinations'])->name('products.images.combinations.detach');
            Route::get('products/{id}/videos', [ProductMediaController::class, 'listVideos'])->name('products.videos.index');
            Route::get('products/{id}/videos/library', [ProductMediaController::class, 'videoLibrary'])->name('products.videos.library');
            Route::post('products/{id}/videos', [ProductMediaController::class, 'uploadVideo'])->name('products.videos.store');
            Route::post('products/{id}/videos/chunk-init', [ProductMediaController::class, 'videoChunkInit'])->name('products.videos.chunk-init');
            Route::post('products/{id}/videos/chunk', [ProductMediaController::class, 'videoChunk'])->name('products.videos.chunk');
            Route::delete('products/{id}/videos', [ProductMediaController::class, 'detachVideos'])->name('products.videos.detach');
            Route::post('products/{id}/videos/assign', [ProductMediaController::class, 'assignVideos'])->name('products.videos.assign');
            Route::post('products/{id}/videos/set-main', [ProductMediaController::class, 'setMainVideo'])->name('products.videos.set-main');
            Route::post('products/{id}/videos/sort', [ProductMediaController::class, 'sortVideos'])->name('products.videos.sort');
            Route::get('products/{id}/video', [ProductMediaController::class, 'listProductVideos'])->name('products.video.index');
            Route::delete('products/{id}/video/{video}', [ProductMediaController::class, 'deleteProductVideo'])->name('products.video.destroy');
            Route::apiResource('products', ProductController::class);

            // Product Combinations
            Route::post('products/{product_id}/combinations/save', [ProductCombinationController::class, 'save'])->name('products.combinations.save');
            Route::apiResource('products.combinations', ProductCombinationController::class)->parameters([
                'combinations' => 'id'
            ]);

            // Product Features
            Route::post('products/{product_id}/features', [ProductFeatureController::class, 'store'])->name('products.features.store');
            Route::get('products/{product_id}/features', [ProductFeatureController::class, 'index'])->name('products.features.index');
            Route::get('products/{product_id}/features/{id}', [ProductFeatureController::class, 'show'])->name('products.features.show');
            Route::post('products/features/categories', [ProductFeatureController::class, 'createCategory'])->name('products.features.categories.create');

            // Product Feature Categories
            Route::apiResource('product-feature-categories', ProductFeatureCategoryController::class);

            // Product Feature Lookup
            Route::get('product-features/categories/search', [ProductFeatureLookupController::class, 'categories'])->name('product-features.categories.search');
            Route::get('product-features/search', [ProductFeatureLookupController::class, 'features'])->name('product-features.search');
            Route::get('product-features/values/search', [ProductFeatureLookupController::class, 'values'])->name('product-features.values.search');
            Route::post('product-features/definitions', [ProductFeatureLookupController::class, 'storeFeature'])->name('product-features.definitions.store');
            Route::post('product-features/values', [ProductFeatureLookupController::class, 'storeValue'])->name('product-features.values.store');

            // Product Attribute Lookup
            Route::get('product-attributes/definitions/search', [ProductAttributeLookupController::class, 'definitions'])->name('product-attributes.definitions.search');
            Route::get('product-attributes/values/search', [ProductAttributeLookupController::class, 'values'])->name('product-attributes.values.search');
            Route::post('product-attributes/definitions', [ProductAttributeLookupController::class, 'storeDefinition'])->name('product-attributes.definitions.store');
            Route::post('product-attributes/values', [ProductAttributeLookupController::class, 'storeValue'])->name('product-attributes.values.store');

            // Image Library
            Route::get('image-library/folders/tree', [ImageLibraryController::class, 'foldersTree'])->name('image-library.folders.tree');
            Route::post('image-library/folders', [ImageLibraryController::class, 'storeFolder'])->name('image-library.folders.store');
            Route::put('image-library/folders/{folder}', [ImageLibraryController::class, 'updateFolder'])->name('image-library.folders.update');
            Route::delete('image-library/folders/{folder}', [ImageLibraryController::class, 'destroyFolder'])->name('image-library.folders.destroy');
            Route::post('image-library/{id}/move', [ImageLibraryController::class, 'moveImage'])->name('image-library.move');
            Route::post('image-library/{id}/generate-avif', [ImageLibraryController::class, 'generateAvif'])->name('image-library.generate-avif');
            Route::post('image-library/{id}/assign-to-product', [ImageLibraryController::class, 'assignToProduct'])->name('image-library.assign-to-product');
            Route::post('image-library/{id}/detach-from-product', [ImageLibraryController::class, 'detachFromProduct'])->name('image-library.detach-from-product');
            Route::apiResource('image-library', ImageLibraryController::class);

            // Image Sizes
            Route::apiResource('image-sizes', ImageSizeController::class);

            // Video Library
            Route::get('video-library/folders/tree', [VideoLibraryController::class, 'foldersTree'])->name('video-library.folders.tree');
            Route::post('video-library/folders', [VideoLibraryController::class, 'storeFolder'])->name('video-library.folders.store');
            Route::put('video-library/folders/{folder}', [VideoLibraryController::class, 'updateFolder'])->name('video-library.folders.update');
            Route::delete('video-library/folders/{folder}', [VideoLibraryController::class, 'destroyFolder'])->name('video-library.folders.destroy');
            Route::post('video-library/{id}/move', [VideoLibraryController::class, 'moveVideo'])->name('video-library.move');
            Route::post('video-library/{id}/assign-to-product', [VideoLibraryController::class, 'assignToProduct'])->name('video-library.assign-to-product');
            Route::post('video-library/{id}/detach-from-product', [VideoLibraryController::class, 'detachFromProduct'])->name('video-library.detach-from-product');
            Route::apiResource('video-library', VideoLibraryController::class);

            // Orders
            Route::post('orders/{id}/update-status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
            Route::post('orders/{id}/tracking', [OrderController::class, 'updateTracking'])->name('orders.tracking');
            Route::post('orders/{id}/discount', [OrderController::class, 'applyDiscount'])->name('orders.discount');
            Route::get('orders/{id}/invoice', [OrderController::class, 'invoice'])->name('orders.invoice');
            Route::get('orders/{id}/label', [OrderController::class, 'label'])->name('orders.label');
            Route::get('orders/{id}/whatsapp', [OrderController::class, 'whatsapp'])->name('orders.whatsapp');
            Route::post('orders/{id}/points', [OrderController::class, 'applyPoints'])->name('orders.points');
            Route::post('orders/{id}/charge', [OrderController::class, 'charge'])->name('orders.charge');
            Route::post('orders/{id}/notes', [OrderController::class, 'addNote'])->name('orders.notes.add');
            Route::put('orders/{id}/notes/{note}', [OrderController::class, 'updateNote'])->name('orders.notes.update');
            Route::delete('orders/{id}/notes/{note}', [OrderController::class, 'deleteNote'])->name('orders.notes.delete');
            Route::post('orders/{id}/retry-sync', [OrderController::class, 'retrySync'])->name('orders.retry-sync');
            Route::post('orders/{id}/retry-payment', [OrderController::class, 'retryPayment'])->name('orders.retry-payment');
            Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{id}', [OrderController::class, 'show'])->name('orders.show');

            // Carts
            Route::apiResource('carts', CartController::class)->only(['index', 'show', 'destroy']);

            // Currencies
            Route::apiResource('currencies', CurrencyController::class);

            // Currency Rates
            Route::apiResource('currency-rates', CurrencyRateController::class);

            // Statistics
            Route::get('product-purchase-stats', [ProductPurchaseStatsController::class, 'index'])->name('product-purchase-stats.index');
            Route::get('user-points', [UserPointsController::class, 'index'])->name('user-points.index');

            // Settings
            Route::get('settings', [ShopSettingsController::class, 'index'])->name('settings.index');
            Route::put('settings', [ShopSettingsController::class, 'update'])->name('settings.update');

            // Utilities
            Route::get('avif/stats', [AvifController::class, 'stats'])->name('avif.stats');
            Route::get('avif/directories', [AvifController::class, 'directories'])->name('avif.directories.index');
            Route::post('avif/directories', [AvifController::class, 'storeDirectory'])->name('avif.directories.store');
            Route::post('avif/directories/{directory}/toggle', [AvifController::class, 'toggleDirectory'])->name('avif.directories.toggle');
            Route::delete('avif/directories/{directory}', [AvifController::class, 'destroyDirectory'])->name('avif.directories.destroy');
            Route::post('avif/regenerate-all', [AvifController::class, 'regenerateAll'])->name('avif.regenerate-all');
            Route::post('avif/regenerate-directory', [AvifController::class, 'regenerateDirectory'])->name('avif.regenerate-directory');
            Route::post('avif/upload-convert', [AvifController::class, 'uploadAndConvert'])->name('avif.upload-convert');
            Route::post('avif/clean-all', [AvifController::class, 'cleanAll'])->name('avif.clean-all');
            Route::post('editor/upload', [EditorUploadController::class, 'upload'])->name('editor.upload');
        });
    });
