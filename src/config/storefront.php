<?php

return [
    'enabled' => false,

    'request_key' => 'storefront',

    'header_name' => 'X-Storefront',

    'default' => 'main',

    // When true, categories without explicit storefront assignment are treated
    // as visible only on the default storefront.
    'apply_unassigned_to_default' => false,

    'values' => [
        'main' => [
            'enabled' => true,
            'code' => 'main',
            'label' => 'Main',
            'badge' => [
                'background' => '#E5E7EB',
                'color' => '#111827',
            ],
            'is_default' => true,
        ],
    ],

    // Maps generic business keys to storefront-specific settings keys.
    // Applications can override this structure and define any number of
    // storefronts without changing the package code.
    'settings_overrides' => [],
];
