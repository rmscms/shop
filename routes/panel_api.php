<?php

use Illuminate\Support\Facades\Route;
use RMS\Shop\Http\Controllers\Api\Panel\AddressController;
use RMS\Shop\Http\Controllers\Api\Panel\AuthController;
use RMS\Shop\Http\Controllers\Api\Panel\CartController;
use RMS\Shop\Http\Controllers\Api\Panel\CategoryController;
use RMS\Shop\Http\Controllers\Api\Panel\CheckoutController;
use RMS\Shop\Http\Controllers\Api\Panel\CurrencyController;
use RMS\Shop\Http\Controllers\Api\Panel\CurrencyRateController;
use RMS\Shop\Http\Controllers\Api\Panel\MediaController;
use RMS\Shop\Http\Controllers\Api\Panel\OrderController;
use RMS\Shop\Http\Controllers\Api\Panel\ProductController;
use RMS\Shop\Http\Controllers\Api\Panel\PaymentDriverController;
use RMS\Shop\Http\Controllers\Api\Panel\ProductGalleryController;
use RMS\Shop\Http\Controllers\Api\Panel\ProductMutationController;
use RMS\Shop\Http\Controllers\Api\Panel\BrandController;

$prefix = trim(config('shop.panel_api.prefix', 'api/v1/panel'), '/');
$middleware = config('shop.panel_api.middleware', ['api']);
$guard = config('shop.panel_api.auth_guard');
$authMiddleware = $guard ? ['auth:' . $guard] : [];

Route::prefix($prefix)
    ->middleware($middleware)
    ->name('shop.panel_api.')
    ->group(function () use ($authMiddleware) {
        Route::post('auth/login', [AuthController::class, 'login'])->name('auth.login');
        Route::post('auth/register', [AuthController::class, 'register'])->name('auth.register');

        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
        Route::get('brands', [BrandController::class, 'index'])->name('brands.index');

        Route::get('categories/tree', [CategoryController::class, 'tree'])->name('categories.tree');
        Route::get('addresses/provinces', [AddressController::class, 'provinces'])->name('addresses.provinces');
        Route::get('payment/drivers', [PaymentDriverController::class, 'index'])->name('payment.drivers.index');
        Route::get('currencies', [CurrencyController::class, 'index'])->name('currencies.index');
        Route::get('currency-rates', [CurrencyRateController::class, 'index'])->name('currency-rates.index');
        Route::get('cart', [CartController::class, 'show'])->name('cart.show');
        Route::post('cart/items', [CartController::class, 'addItem'])->name('cart.items.add');
        Route::patch('cart/items/{lineId}', [CartController::class, 'updateItem'])->name('cart.items.update');
        Route::delete('cart/items/{lineId}', [CartController::class, 'removeItem'])->name('cart.items.remove');

        Route::middleware($authMiddleware)->group(function () {
            Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
            Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

            Route::get('addresses', [AddressController::class, 'index'])->name('addresses.index');
            Route::post('addresses', [AddressController::class, 'store'])->name('addresses.store');
            Route::put('addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
            Route::delete('addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');
            Route::post('addresses/{address}/default', [AddressController::class, 'setDefault'])->name('addresses.default');

            Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
            Route::get('orders/{order}/notes', [OrderController::class, 'notes'])->name('orders.notes');
            Route::post('orders/{order}/notes', [OrderController::class, 'storeNote'])->name('orders.notes.store');
            Route::post('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status');

            Route::post('checkout', [CheckoutController::class, 'store'])->name('checkout.store');
            Route::post('currency-rates', [CurrencyRateController::class, 'store'])->name('currency-rates.store');
            Route::post('products/{product}/stock', [ProductMutationController::class, 'updateStock'])->name('products.stock');
            Route::post('products/{product}/gallery', [ProductGalleryController::class, 'upload'])->name('products.gallery.upload');
            Route::delete('products/{product}/gallery/{image}', [ProductGalleryController::class, 'destroy'])->name('products.gallery.delete');
            Route::post('products/{product}/gallery/set-main', [ProductGalleryController::class, 'setMain'])->name('products.gallery.set-main');
            Route::post('products/{product}/gallery/sort', [ProductGalleryController::class, 'sort'])->name('products.gallery.sort');
            Route::post('products/{product}/gallery/assign', [ProductGalleryController::class, 'assign'])->name('products.gallery.assign');
            Route::post('products/{product}/gallery/detach', [ProductGalleryController::class, 'detach'])->name('products.gallery.detach');
            Route::post('media/chunks/init', [MediaController::class, 'init'])->name('media.chunks.init');
            Route::post('media/chunks/upload', [MediaController::class, 'upload'])->name('media.chunks.upload');
        });
    });

