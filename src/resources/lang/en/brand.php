<?php

return [
    'entity_singular' => 'brand',
    'entity_plural' => 'brands',
    'fields' => [
        'name' => 'Name',
        'slug' => 'URL',
        'slug_hint' => 'Will be generated from name by default',
        'logo' => 'Logo',
        'country' => 'Country',
        'description' => 'Description'
    ],
    'filters' => [
        'country' => 'Country',
        'language' => 'Language',
        'seo' => [
            'label' => 'SEO filled',
            'options' => [
                0 => 'Empty',
                2 => 'Filled',
            ],
        ],
    ]
];
