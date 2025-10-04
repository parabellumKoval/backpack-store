<?php

return [
    'enabled' => env('STORE_SEARCH_ENABLED', true),
    'driver'  => env('STORE_SEARCH_DRIVER', 'meilisearch'),

    'suffix_per_country' => env('STORE_SEARCH_INDEX_SUFFIX_PER_COUNTRY', true),

    // 'index'   => [
    //     'products' => env('STORE_SEARCH_INDEX_PRODUCTS', 'products'),
    //     'suffix_per_country' => env('STORE_SEARCH_INDEX_SUFFIX_PER_COUNTRY', true),
    // ],

    'models' => [
        'products'   => \Backpack\Store\app\Models\Catalog::class,
        'categories' => \Backpack\Store\app\Models\Category::class,
        'brands'     => \Backpack\Store\app\Models\Brand::class,
    ],

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://meilisearch:7700'),
        'key'  => env('MEILISEARCH_KEY', null),

        // дефолтные настройки индекса (могут быть перекрыты из \Settings)
        'settings' => [
            'searchableAttributes' => ['name', 'brand', 'category'],
            'filterableAttributes' => ['brand', 'category', 'in_stock', 'country_code'],
            'sortableAttributes'   => ['price', 'popularity', 'created_at'],
            'distinctAttribute'    => 'group_id',
            'pagination'           => ['maxTotalHits' => 20000],
            'typoTolerance'        => ['enabled' => true],
            'synonyms'             => [],
            'stopWords'            => [],
            'rankingRules'         => [
                'words','typo','proximity','attribute','sort','exactness','popularity:desc',
            ],
        ],
    ],
];