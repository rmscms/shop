<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SEO
    |--------------------------------------------------------------------------
    |
    | The storefront is a separate app, so canonical URLs, Open Graph and
    | JSON-LD point at storefront pages built from these paths.
    |
    */

    // Public site that shows products (no trailing slash).
    'storefront_url' => env('SHOP_STOREFRONT_URL', env('SHOP_FRONT_URL', env('APP_URL'))),

    'paths' => [
        'product' => env('SHOP_SEO_PRODUCT_PATH', '/product/{slug}'),
        'category' => env('SHOP_SEO_CATEGORY_PATH', '/category/{slug}'),
        'brand' => env('SHOP_SEO_BRAND_PATH', '/brand/{slug}'),
    ],

    'site_name' => env('SHOP_SEO_SITE_NAME', env('APP_NAME', 'Shop')),
    // Appended to titles that fit, e.g. " | Digital Part".
    'title_suffix' => env('SHOP_SEO_TITLE_SUFFIX', ''),
    'title_max' => 60,
    'description_max' => 155,

    // Fallback Open Graph image (absolute URL or public path).
    'default_image' => env('SHOP_SEO_DEFAULT_IMAGE'),
    // Image size slug used for og:image and JSON-LD when it exists (see image sizes).
    'image_size' => env('SHOP_SEO_IMAGE_SIZE', 'large'),

    /*
    | Google only accepts ISO 4217 codes, so toman prices are published in rial.
    */
    'schema_currency' => env('SHOP_SEO_SCHEMA_CURRENCY', 'IRR'),
    'schema_price_multiplier' => (float) env('SHOP_SEO_SCHEMA_PRICE_MULTIPLIER', 10),
    // Offers are valid for this many days from today (priceValidUntil).
    'price_valid_days' => (int) env('SHOP_SEO_PRICE_VALID_DAYS', 30),

    // Store policy: products are always kept in stock, so offers say InStock.
    'always_in_stock' => (bool) env('SHOP_SEO_ALWAYS_IN_STOCK', true),
    'item_condition' => 'https://schema.org/NewCondition',

    // Optional merchant listing details; leave null to omit.
    'shipping' => [
        'country' => 'IR',
        'rate' => env('SHOP_SEO_SHIPPING_RATE'),          // in display currency (toman); null = omit
        'handling_days' => [0, 1],
        'transit_days' => [1, 5],
    ],
    'return_policy' => [
        'country' => 'IR',
        'days' => env('SHOP_SEO_RETURN_DAYS'),             // null = omit
    ],

    'sitemap' => [
        'enabled' => (bool) env('SHOP_SEO_SITEMAP', true),
        'cache_minutes' => (int) env('SHOP_SEO_SITEMAP_CACHE', 60),
    ],
];
