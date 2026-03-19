<?php

return [
    'catalog_table_cache' => true,
    'slug_map_ttl' => env('BP_STORE_SLUG_MAP_TTL', 3600),
    
    'global_region_code' => 'zz',

    'base_currency' => 'USD',

    'currency_label_resolver' => env('STORE_CURRENCY_LABEL_RESOLVER', 'Backpack\\Profile\\app\\Contracts\\CurrencyNameResolver'),
    
    'currency' => [
      'value' => 'usd',
      'symbol' => '$',
    ],
    
    'path' => [
      'image' => [
        'placeholder' => '/public/images/noimage.png'
      ]
    ],

    // CATALOG
    'per_page' => 12,
    'catalog' => [
      'touch_inline' => env('BP_STORE_CATALOG_TOUCH_INLINE', false),
    ],

    // GUARD
    'auth_guard' => 'profile',
    
    // USER
    'user_model' => 'Backpack\Profile\app\Models\Profile',

    // REVIEW
    'review_model' => 'Backpack\Reviews\app\Models\Review',
    'enable_reviews_in_product_crud' => true,

    // ORDER
    'order_model' => 'Backpack\Store\app\Models\Order',
    'enable_orders_in_product_crud' => true,

    // CACHE
    'cache' => [
      'enable' => true,
      'cases' => []
    ],
];
