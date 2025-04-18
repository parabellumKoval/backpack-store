<?php

return [
    'entity_singular' => 'товар',
    'entity_plural' => 'товары',
    'bulk_actions' => [
        'select_items' => 'Пожалуйста, выберите элементы для обработки',
        'activated' => 'Активировано товаров: :count',
        'deactivated' => 'Деактивировано товаров: :count',
        'categories_assigned' => 'Назначено категорий для :count товаров',
        'categories_removed' => 'Удалены все категории у :count товаров',
        'invalid_action' => 'Неверное действие'
    ],
    'fields' => [
        'barcode' => 'Баркод/код',
        'name' => 'Название',
        'slug' => 'URL',
        'slug_hint' => 'По умолчанию будет сгенерирован из названия',
        'brand' => 'Бренд',
        'description' => 'Описание',
        'images' => 'Изображения',
        'price' => 'Цена',
        'old_price' => 'Старая цена',
        'stock' => 'Количество на складе',
        'in_stock' => 'В наличии',
        'categories' => 'Категории',
    ]
];