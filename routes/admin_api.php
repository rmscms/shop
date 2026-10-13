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

$prefix = trim(config('shop.admin_api.prefix', 'api/v1/admin/shop'), '/');
$middleware = config('shop.admin_api.middleware', ['api']);
$guard = config('shop.admin_api.auth_guard');
// Use sanctum middleware - it will automatically use the correct guard based on token
$authMiddleware = $guard ? ['auth:sanctum'] : [];

Route::prefix($prefix)
    ->middleware($middleware)
    ->name('shop.admin_api.')
    ->group(function () use ($authMiddleware) {
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
            Route::post('image-library/{id}/assign-to-product', [ImageLibraryController::class, 'assignToProduct'])->name('image-library.assign-to-product');
            Route::post('image-library/{id}/detach-from-product', [ImageLibraryController::class, 'detachFromProduct'])->name('image-library.detach-from-product');
            Route::apiResource('image-library', ImageLibraryController::class);

            // Image Sizes
            Route::apiResource('image-sizes', ImageSizeController::class);

            // Video Library
            Route::post('video-library/{id}/assign-to-product', [VideoLibraryController::class, 'assignToProduct'])->name('video-library.assign-to-product');
            Route::post('video-library/{id}/detach-from-product', [VideoLibraryController::class, 'detachFromProduct'])->name('video-library.detach-from-product');
            Route::apiResource('video-library', VideoLibraryController::class);

            // Orders
            Route::post('orders/{id}/update-status', [OrderController::class, 'updateStatus'])->name('orders.update-status');
            Route::post('orders/{id}/tracking', [OrderController::class, 'updateTracking'])->name('orders.tracking');
            Route::post('orders/{id}/discount', [OrderController::class, 'applyDiscount'])->name('orders.discount');
            Route::post('orders/{id}/notes', [OrderController::class, 'addNote'])->name('orders.notes.add');
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
            Route::post('avif/regenerate-all', [AvifController::class, 'regenerateAll'])->name('avif.regenerate-all');
            Route::post('avif/clean-all', [AvifController::class, 'cleanAll'])->name('avif.clean-all');
            Route::post('editor/upload', [EditorUploadController::class, 'upload'])->name('editor.upload');
        });
    });
