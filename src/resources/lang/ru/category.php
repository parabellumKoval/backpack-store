<?php

return [
    'entity_singular' => 'категорию',
    'entity_plural' => 'категории',
    'fields' => [
        'is_active' => 'Активна',
        'name' => 'Название',
        'slug' => 'URL',
        'slug_hint' => 'По умолчанию будет сгенерирован из названия',
        'parent' => 'Родительская категория',
        'content' => 'Описание',
        'images' => 'Изображения',
        'h1' => 'H1 заголовок',
        'meta_title' => 'Meta title',
        'meta_description' => 'Meta description',
        'params' => 'Параметры',
        'params_columns' => [
            'key' => 'Ключ',
            'value' => 'Значение'
        ]
    ],
    'filters' => [
        'parent_category' => 'Родительская категория',
        'active' => [
            'label' => 'Активная',
            'options' => [
                0 => 'Не активная',
                1 => 'Активная'
            ]
        ],
        'with_products' => [
            'label' => 'С товарами',
            'options' => [
                0 => 'Без товаров',
                1 => 'С товарами'
            ]
        ],
        'seo' => [
            'label' => 'Заполнено SEO',
            'options' => [
                0 => 'Не заполнено SEO',
                2 => 'Заполнено SEO'
            ]
        ]
    ],
    'tabs' => [
        'main' => 'Основное',
        'images' => 'Изображения',
        'seo' => 'SEO',
        'additional' => 'Дополнительно'
    ]
];