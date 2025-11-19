<?php

return [
    'entity_singular' => 'виробник',
    'entity_plural' => 'виробники',
    'fields' => [
        'name' => 'Назва',
        'slug' => 'URL',
        'slug_hint' => 'За замовчуванням буде згенеровано з назви',
        'logo' => 'Логотип',
        'country' => 'Країна',
        'description' => 'Опис'
    ],
    'filters' => [
        'country' => 'Країна',
        'language' => 'Мова',
        'seo' => [
            'label' => 'Заповнено SEO',
            'options' => [
                0 => 'Не заповнено',
                2 => 'Заповнено',
            ],
        ],
    ]
];
