<?php

return [
    'ckeditor' => [
        'css' => [],
        'js' => [
            'ckeditor.js',
            'ckeditor-init.js',
        ],
        'plugin_path' => 'ckeditor',
        'enabled' => true,
        'load_order' => 4,
        'description' => 'CKEditor 5 for rich text editing in the shop admin',
    ],

    'fancytree' => [
        'css' => [
            'fancytree.css',
        ],
        'js' => [
            'fancytree_all.min.js',
            'fancytree_childcounter.js',
            'fancytree.dnd5.js',
            'rms-fancytree.js',
        ],
        'dependencies' => ['jquery'],
        'load_order' => 4,
        'plugin_path' => 'fancytree',
        'enabled' => true,
        'description' => 'Tree component for shop category selection',
    ],
];
