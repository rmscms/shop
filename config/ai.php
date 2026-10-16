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

    // anthropic or openai (any OpenAI-compatible endpoint)
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
    ],

    'max_tokens' => (int) env('SHOP_AI_MAX_TOKENS', 4096),
    'timeout' => (int) env('SHOP_AI_TIMEOUT', 120),

    // Images sent per product (main image first). Zero disables vision.
    'max_images' => (int) env('SHOP_AI_MAX_IMAGES', 3),
    // Files larger than this (KB) are skipped; providers reject very large images.
    'max_image_kb' => (int) env('SHOP_AI_MAX_IMAGE_KB', 4500),

    'queue' => env('SHOP_QUEUE_AI', 'shop-ai'),

    // Extra guidance appended to the prompt, e.g. tone or store niche.
    'store_context' => env('SHOP_AI_STORE_CONTEXT', ''),
];
