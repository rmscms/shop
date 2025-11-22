<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Panel API Toggle
    |--------------------------------------------------------------------------
    */
    'enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Prefix & Middleware
    |--------------------------------------------------------------------------
    */
    'prefix' => 'api/v1/panel',
    'middleware' => ['api'],
    'auth_guard' => 'sanctum',

    /*
    |--------------------------------------------------------------------------
    | Authentication strategy
    |--------------------------------------------------------------------------
    */
    'auth' => [
        'driver' => env('SHOP_PANEL_AUTH_DRIVER', 'email'),
        'device_name' => env('SHOP_PANEL_DEVICE_NAME', 'shop-panel'),
        'user_model' => env('SHOP_PANEL_USER_MODEL', config('auth.providers.users.model')),
        'drivers' => [
            'email' => RMS\Shop\Support\PanelApi\Auth\EmailPasswordDriver::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default JSON structure
    |--------------------------------------------------------------------------
    */
    'response' => [
        'status_key' => 'status',
        'data_key' => 'data',
        'errors_key' => 'errors',
        'meta_key' => 'meta',
        'default_status' => 'ok',
        'modifiers' => [
            // \App\Api\Modifiers\ExampleModifier::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart handling for guest users
    |--------------------------------------------------------------------------
    */
    'cart' => [
        'cookie_name' => 'panel_cart',
        'ttl' => 60 * 60 * 24 * 30, // 30 days in seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Orders config (customer-side)
    |--------------------------------------------------------------------------
    */
    'orders' => [
        // Allowed status transitions that customers can trigger themselves.
        // key => array of source statuses
        'customer_status_transitions' => [
            'cancelled' => ['pending', 'preparing', 'paid'],
            'received' => ['shipped', 'delivered'],
        ],
        'note_max_length' => 3000,
    ],
];

