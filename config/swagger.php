<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Swagger Documentation Configuration
    |--------------------------------------------------------------------------
    |
    | این تنظیمات برای فعال‌سازی Swagger Documentation در پکیج Shop است.
    | برای استفاده از Swagger، باید پکیج darkaonline/l5-swagger را نصب کنید.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Enable Swagger
    |--------------------------------------------------------------------------
    |
    | فعال یا غیرفعال کردن Swagger Documentation
    |
    */
    'enabled' => env('SHOP_SWAGGER_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Swagger Route
    |--------------------------------------------------------------------------
    |
    | مسیر دسترسی به Swagger UI
    |
    */
    'route' => env('SHOP_SWAGGER_ROUTE', 'api/documentation'),

    /*
    |--------------------------------------------------------------------------
    | Annotations Path
    |--------------------------------------------------------------------------
    |
    | مسیر فایل‌های حاوی Swagger Annotations
    | این مسیر باید به l5-swagger config اضافه شود
    |
    */
    'annotations_path' => base_path('vendor/rmscms/shop/src'),

    /*
    |--------------------------------------------------------------------------
    | Info Configuration
    |--------------------------------------------------------------------------
    |
    | اطلاعات کلی API Documentation
    |
    */
    'info' => [
        'title' => env('SHOP_SWAGGER_TITLE', 'RMS Shop Panel API'),
        'version' => env('SHOP_SWAGGER_VERSION', '1.0.0'),
        'description' => env('SHOP_SWAGGER_DESCRIPTION', 'REST API used by the RMS customer panel application.'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Server Configuration
    |--------------------------------------------------------------------------
    |
    | تنظیمات سرور API
    |
    */
    'server' => [
        'url' => env('SHOP_SWAGGER_SERVER_URL', '/api/v1/panel'),
        'description' => env('SHOP_SWAGGER_SERVER_DESCRIPTION', 'Panel API base URL'),
    ],
];
