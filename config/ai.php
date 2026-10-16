<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI product assistant
    |--------------------------------------------------------------------------
    |
    | Reads a product (name, text, images) and suggests Persian copy,
    | category, brand and features. Suggestions are stored for review and
    | only touch the product when an admin applies them.
    |
    */
    'enabled' => env('SHOP_AI_ENABLED', true),

    // anthropic, openai (any OpenAI-compatible endpoint) or gemini
    'driver' => env('SHOP_AI_DRIVER', 'anthropic'),

    'drivers' => [
        'anthropic' => [
            'api_key' => env('SHOP_AI_ANTHROPIC_KEY'),
            'model' => env('SHOP_AI_ANTHROPIC_MODEL', 'claude-sonnet-5-5'),
            'base_url' => env('SHOP_AI_ANTHROPIC_URL', 'https://api.anthropic.com/v1'),
            'version' => env('SHOP_AI_ANTHROPIC_VERSION', '2023-06-01'),
        ],
        'openai' => [
            'api_key' => env('SHOP_AI_OPENAI_KEY'),
            'model' => env('SHOP_AI_OPENAI_MODEL', 'gpt-4o-mini'),
            'base_url' => env('SHOP_AI_OPENAI_URL', 'https://api.openai.com/v1'),
        ],
        'gemini' => [
            'api_key' => env('SHOP_AI_GEMINI_KEY'),
            'model' => env('SHOP_AI_GEMINI_MODEL', 'gemini-2.5-flash'),
            // Google blocks some regions (including Iran); point this at a proxy if needed.
            'base_url' => env('SHOP_AI_GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            // Let the model check specs with Google Search; the pages it used are shown for review.
            'search' => (bool) env('SHOP_AI_GEMINI_SEARCH', true),
            // Thinking tokens count toward this limit, so it is higher than the shared default.
            'max_tokens' => (int) env('SHOP_AI_GEMINI_MAX_TOKENS', 8192),
        ],
    ],

    'max_tokens' => (int) env('SHOP_AI_MAX_TOKENS', 4096),
    'timeout' => (int) env('SHOP_AI_TIMEOUT', 120),

    // The catalog plan reads a whole product list in one call, so it gets more room.
    'catalog_max_tokens' => (int) env('SHOP_AI_CATALOG_MAX_TOKENS', 16000),
    'catalog_timeout' => (int) env('SHOP_AI_CATALOG_TIMEOUT', 300),

    // Images sent per product (main image first). Zero disables vision.
    'max_images' => (int) env('SHOP_AI_MAX_IMAGES', 3),
    // Files larger than this (KB) are skipped; providers reject very large images.
    'max_image_kb' => (int) env('SHOP_AI_MAX_IMAGE_KB', 4500),

    'queue' => env('SHOP_QUEUE_AI', 'shop-ai'),
    // Seconds between queued jobs in bulk runs (e.g. 6 keeps a 10 requests/minute free tier happy).
    'dispatch_spacing' => (int) env('SHOP_AI_DISPATCH_SPACING', 0),

    // Extra guidance appended to the prompt, e.g. tone or store niche.
    'store_context' => env('SHOP_AI_STORE_CONTEXT', ''),
];
