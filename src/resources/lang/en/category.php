<?php

return [
    'entity_singular' => 'category',
    'entity_plural' => 'categories',
    'fields' => [
        'is_active' => 'Active',
        'name' => 'Name',
        'slug' => 'URL',
        'slug_hint' => 'Will be generated from name by default',
        'parent' => 'Parent Category',
        'content' => 'Description',
        'images' => 'Images',
        'h1' => 'H1 Title',
        'meta_title' => 'Meta Title',
        'meta_description' => 'Meta Description',
        'params' => 'Parameters',
        'params_columns' => [
            'key' => 'Key',
            'value' => 'Value'
        ]
    ],
    'filters' => [
        'parent_category' => 'Parent Category',
        'active' => [
            'label' => 'Active',
            'options' => [
                0 => 'Inactive',
                1 => 'Active'
            ]
        ],
        'with_products' => [
            'label' => 'With Products',
            'options' => [
                0 => 'Without Products',
                1 => 'With Products'
            ]
        ],
        'seo' => [
            'label' => 'SEO Filled',
            'options' => [
                0 => 'SEO Not Filled',
                2 => 'SEO Filled'
            ]
        ]
    ],
    'tabs' => [
        'main' => 'Main',
        'images' => 'Images',
        'seo' => 'SEO',
        'additional' => 'Additional'
    ]
];