<?php

use Illuminate\Support\Facades\Route;
use RMS\Shop\Http\Controllers\Payment\PaymentCallbackController;

$prefix = trim(config('shop.routes.payment.prefix', ''), '/');
$prefix = $prefix ? $prefix.'/' : '';
$name = config('shop.routes.payment.name', 'shop.');
$middleware = config('shop.routes.payment.middleware', ['web']);

Route::prefix($prefix)
    ->name($name)
    ->middleware($middleware)
    ->group(function () {
        Route::match(['GET', 'POST'], 'payment/callback', PaymentCallbackController::class)
            ->name('payment.callback');
    });

