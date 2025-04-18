<?php

return [
    'entity_singular' => 'категорію',
    'entity_plural' => 'категорії',
    'fields' => [
        'is_active' => 'Активна',
        'name' => 'Назва',
        'slug' => 'URL',
        'slug_hint' => 'За замовчуванням буде згенеровано з назви',
        'parent' => 'Батьківська категорія',
        'content' => 'Опис',
        'images' => 'Зображення',
        'h1' => 'H1 заголовок',
        'meta_title' => 'Meta title',
        'meta_description' => 'Meta description',
        'params' => 'Параметри',
        'params_columns' => [
            'key' => 'Ключ',
            'value' => 'Значення'
        ]
    ],
    'filters' => [
        'parent_category' => 'Батьківська категорія',
        'active' => [
            'label' => 'Активна',
            'options' => [
                0 => 'Не активна',
                1 => 'Активна'
            ]
        ],
        'with_products' => [
            'label' => 'З товарами',
            'options' => [
                0 => 'Без товарів',
                1 => 'З товарами'
            ]
        ],
        'seo' => [
            'label' => 'Заповнено SEO',
            'options' => [
                0 => 'Не заповнено SEO',
                2 => 'Заповнено SEO'
            ]
        ]
    ],
    'tabs' => [
        'main' => 'Основне',
        'images' => 'Зображення',
        'seo' => 'SEO',
        'additional' => 'Додатково'
    ]
];