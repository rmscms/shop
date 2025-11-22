<?php

/**
 * Shop module configuration
 *
 * Status catalog (code => [id, text]):
 * - pending   => [1,  'در انتظار']
 * - preparing => [2,  'در حال آماده‌سازی']
 * - shipped   => [3,  'ارسال شده']
 * - delivered => [4,  'تحویل شده']
 * - returned  => [5,  'برگشت خورده']
 * - rejected  => [6,  'رد شده']
 * - paid      => [7,  'پرداخت‌شده']
 * - cancelled => [8,  'لغو شده']
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Routes Configuration
    |--------------------------------------------------------------------------
    |
    | Configure routes prefix, names, and middleware for both admin and shop
    | You can override these to customize your shop URLs
    |
    */
    'routes' => [
        'admin' => [
            'enabled' => true,
            'prefix' => env('SHOP_ADMIN_PREFIX', 'admin/shop'),
            'name' => env('SHOP_ADMIN_ROUTE_NAME', 'admin.shop.'),
            'middleware' => ['web', 'auth:admin'],
        ],
        'payment' => [
            'enabled' => env('SHOP_PAYMENT_ROUTES_ENABLED', true),
            'prefix' => env('SHOP_PAYMENT_PREFIX', ''),
            'name' => env('SHOP_PAYMENT_ROUTE_NAME', 'shop.'),
            'redirects' => [
                'success' => env('SHOP_PAYMENT_SUCCESS_REDIRECT', '/payment/result?status=success&order={order}'),
                'failed' => env('SHOP_PAYMENT_FAILED_REDIRECT', '/payment/result?status=failed&reference={order}'),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Order Configuration
    |--------------------------------------------------------------------------
    */
    // Default order status when a user places an order
    'default_status' => 'pending',

    // Panel dashboard: legacy string key (kept for backward compatibility in current code)
    'active_order_statuses' => env('SHOP_ACTIVE_ORDER_STATUSES', 'pending,preparing,paid'),

    // New preferred key (array) for active statuses — currently unused
    'active_statuses' => [
        'pending','preparing','paid'
    ],

    // Which statuses should trigger automatic refund/adjustment if order was charged
    // Array form (preferred)
    'refund_statuses' => [
        'returned','rejected'
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart Configuration
    |--------------------------------------------------------------------------
    */
    // Soft reservation TTL in seconds (Redis)
    'cart_reservation_ttl' => env('SHOP_CART_RES_TTL', 1800),

    /*
    |--------------------------------------------------------------------------
    | Categories Configuration
    |--------------------------------------------------------------------------
    */
    'categories' => [
        'default_name' => env('SHOP_DEFAULT_CATEGORY_NAME', 'دسته‌بندی نشده'),
        'default_slug' => env('SHOP_DEFAULT_CATEGORY_SLUG', 'uncategorized'),
        'fallback_label' => 'بدون دسته',
        'cache_ttl' => env('SHOP_CATEGORY_CACHE_TTL', 300),
        'demo_tree' => [
            [
                'name' => 'کالای دیجیتال',
                'slug' => 'digital-products',
                'sort' => 10,
                'children' => [
                    ['name' => 'موبایل و تبلت', 'slug' => 'mobile-tablet', 'sort' => 10],
                    ['name' => 'لپ‌تاپ و کامپیوتر', 'slug' => 'laptop-computer', 'sort' => 20],
                    ['name' => 'لوازم جانبی دیجیتال', 'slug' => 'digital-accessories', 'sort' => 30],
                ],
            ],
            [
                'name' => 'خانه و آشپزخانه',
                'slug' => 'home-kitchen',
                'sort' => 20,
                'children' => [
                    ['name' => 'وسایل برقی خانگی', 'slug' => 'home-appliances', 'sort' => 10],
                    ['name' => 'دکوراسیون و نورپردازی', 'slug' => 'home-decoration', 'sort' => 20],
                    ['name' => 'ابزار و تجهیزات منزل', 'slug' => 'home-tools', 'sort' => 30],
                ],
            ],
            [
                'name' => 'مد و پوشاک',
                'slug' => 'fashion',
                'sort' => 30,
                'children' => [
                    ['name' => 'پوشاک مردانه', 'slug' => 'fashion-men', 'sort' => 10],
                    ['name' => 'پوشاک زنانه', 'slug' => 'fashion-women', 'sort' => 20],
                    ['name' => 'اکسسوری و زیورآلات', 'slug' => 'fashion-accessories', 'sort' => 30],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Geo Configuration (Iran Provinces)
    |--------------------------------------------------------------------------
    */
    'geo' => [
        'default_country' => 'IR',
        'iran_provinces' => [
            ['id' => 1, 'code' => 'AZE', 'slug' => 'east-azarbaijan', 'name' => 'آذربایجان شرقی'],
            ['id' => 2, 'code' => 'WAZ', 'slug' => 'west-azarbaijan', 'name' => 'آذربایجان غربی'],
            ['id' => 3, 'code' => 'ARD', 'slug' => 'ardabil', 'name' => 'اردبیل'],
            ['id' => 4, 'code' => 'ISF', 'slug' => 'isfahan', 'name' => 'اصفهان'],
            ['id' => 5, 'code' => 'ALB', 'slug' => 'alborz', 'name' => 'البرز'],
            ['id' => 6, 'code' => 'ILM', 'slug' => 'ilam', 'name' => 'ایلام'],
            ['id' => 7, 'code' => 'BUS', 'slug' => 'bushehr', 'name' => 'بوشهر'],
            ['id' => 8, 'code' => 'THR', 'slug' => 'tehran', 'name' => 'تهران'],
            ['id' => 9, 'code' => 'CHB', 'slug' => 'chaharmahal-bakhtiari', 'name' => 'چهارمحال و بختیاری'],
            ['id' => 10, 'code' => 'SKH', 'slug' => 'south-khorasan', 'name' => 'خراسان جنوبی'],
            ['id' => 11, 'code' => 'RKH', 'slug' => 'razavi-khorasan', 'name' => 'خراسان رضوی'],
            ['id' => 12, 'code' => 'NKH', 'slug' => 'north-khorasan', 'name' => 'خراسان شمالی'],
            ['id' => 13, 'code' => 'KHU', 'slug' => 'khuzestan', 'name' => 'خوزستان'],
            ['id' => 14, 'code' => 'ZAN', 'slug' => 'zanjan', 'name' => 'زنجان'],
            ['id' => 15, 'code' => 'SEM', 'slug' => 'semnan', 'name' => 'سمنان'],
            ['id' => 16, 'code' => 'SBL', 'slug' => 'sistan-baluchestan', 'name' => 'سیستان و بلوچستان'],
            ['id' => 17, 'code' => 'FAR', 'slug' => 'fars', 'name' => 'فارس'],
            ['id' => 18, 'code' => 'QAZ', 'slug' => 'qazvin', 'name' => 'قزوین'],
            ['id' => 19, 'code' => 'QOM', 'slug' => 'qom', 'name' => 'قم'],
            ['id' => 20, 'code' => 'KRD', 'slug' => 'kurdistan', 'name' => 'کردستان'],
            ['id' => 21, 'code' => 'KER', 'slug' => 'kerman', 'name' => 'کرمان'],
            ['id' => 22, 'code' => 'KSH', 'slug' => 'kermanshah', 'name' => 'کرمانشاه'],
            ['id' => 23, 'code' => 'KBA', 'slug' => 'kohgiluyeh-boyerahmad', 'name' => 'کهگیلویه و بویراحمد'],
            ['id' => 24, 'code' => 'GOL', 'slug' => 'golestan', 'name' => 'گلستان'],
            ['id' => 25, 'code' => 'GIL', 'slug' => 'gilan', 'name' => 'گیلان'],
            ['id' => 26, 'code' => 'LOR', 'slug' => 'lorestan', 'name' => 'لرستان'],
            ['id' => 27, 'code' => 'MAZ', 'slug' => 'mazandaran', 'name' => 'مازندران'],
            ['id' => 28, 'code' => 'MRK', 'slug' => 'markazi', 'name' => 'مرکزی'],
            ['id' => 29, 'code' => 'HRM', 'slug' => 'hormozgan', 'name' => 'هرمزگان'],
            ['id' => 30, 'code' => 'HAM', 'slug' => 'hamedan', 'name' => 'همدان'],
            ['id' => 31, 'code' => 'YAZ', 'slug' => 'yazd', 'name' => 'یزد'],
        ],
    ],

    'payment' => [
        'success_status' => env('SHOP_PAYMENT_SUCCESS_STATUS', 'paid'),
        'failed_status' => env('SHOP_PAYMENT_FAILED_STATUS', 'pending'),
        'redirects' => [
            'success' => env('SHOP_PAYMENT_SUCCESS_REDIRECT', '/payment/result?status=success&order={order}'),
            'failed' => env('SHOP_PAYMENT_FAILED_REDIRECT', '/payment/result?status=failed&reference={order}'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Currency Configuration
    |--------------------------------------------------------------------------
    |
    | Base currency is what products are stored in (typically CNY).
    | Display currency is what the storefront shows/prices orders with (IRT).
    |
    */
    'currency' => [
        'base' => env('SHOP_BASE_CURRENCY', 'CNY'),
        'display' => env('SHOP_DISPLAY_CURRENCY', 'IRT'),
        'label' => env('SHOP_CURRENCY_LABEL', 'تومان'),
        'decimals' => (int) env('SHOP_CURRENCY_DECIMALS', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Configuration
    |--------------------------------------------------------------------------
    */
    'media' => [
        'images' => [
            'driver' => env('SHOP_IMAGE_DRIVER', 'avif'), // avif, imagick, gd
            'quality' => env('SHOP_IMAGE_QUALITY', 85),
            'max_size' => env('SHOP_IMAGE_MAX_SIZE', 5120), // KB
            'formats' => ['jpg', 'jpeg', 'png', 'webp', 'avif'],
        ],
        'videos' => [
            'enabled' => env('SHOP_VIDEO_ENABLED', true),
            'driver' => env('SHOP_VIDEO_DRIVER', 'hls'), // hls, direct
            'max_size' => env('SHOP_VIDEO_MAX_SIZE', 102400), // KB (100MB)
            'formats' => ['mp4', 'webm', 'mov'],
            'ffmpeg_path' => env('FFMPEG_PATH', 'ffmpeg'), // Path to ffmpeg binary
            'ffprobe_path' => env('FFPROBE_PATH', 'ffprobe'), // Path to ffprobe binary
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Define separate queues for different media processing tasks
    | This allows you to run dedicated workers for each queue
    |
    */
    'queues' => [
        'avif' => env('SHOP_QUEUE_AVIF', 'shop-avif'),        // Queue for AVIF conversion
        'media' => env('SHOP_QUEUE_MEDIA', 'shop-media'),     // Queue for video processing
        'default' => env('SHOP_QUEUE_DEFAULT', 'default'),    // Default queue for other tasks
    ],

    /*
    |--------------------------------------------------------------------------
    | Frontend Panel API
    |--------------------------------------------------------------------------
    |
    | Base URL for consuming the panel API from a standalone frontend.
    | This value is consumed by the published config in shop-test to ensure
    | API requests do not rely on hard-coded APP_URL defaults.
    |
    */
    'frontend_api' => [
        'base_url' => env('SHOP_PANEL_API_URL', env('APP_URL', 'http://127.0.0.1:8000').'/api/v1/panel'),
        'shop_url' => env('SHOP_FRONT_URL', 'http://127.0.0.1:8001'),
    ],
];
