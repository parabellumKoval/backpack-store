<?php

return [
    'entity_singular' => 'товар',
    'entity_plural' => 'товари',
    'bulk_actions' => [
        'select_items' => 'Будь ласка, виберіть елементи для обробки',
        'activated' => 'Активовано товарів: :count',
        'deactivated' => 'Деактивовано товарів: :count',
        'categories_assigned' => 'Призначено категорій для :count товарів',
        'categories_removed' => 'Видалено всі категорії у :count товарів',
        'invalid_action' => 'Невірна дія'
    ],
    'fields' => [
        'barcode' => 'Штрих-код/код',
        'name' => 'Назва',
        'slug' => 'URL',
        'slug_hint' => 'За замовчуванням буде згенеровано з назви',
        'brand' => 'Бренд',
        'description' => 'Опис',
        'images' => 'Зображення',
        'price' => 'Ціна',
        'old_price' => 'Стара ціна',
        'stock' => 'Кількість на складі',
        'in_stock' => 'В наявності',
        'categories' => 'Категорії',
    ]
];