<?php

return [
    'enabled' => true, // Активировать блок cross/up-sell
    // Места: mini_cart / cart / checkout
    'placements' => [
        'product_page' => 'Страница товара',
        'cart' => 'Конзина',
        'checkout' => 'Оформление заказа'
    ],
    // Общая сортировка: random / popular / priority
    'sort' => 'priority',
    
    // Источники авто-подбора: category|tags|bought_together (порядок = приоритет провайдера)
    'sources' => [
        'links' => 'Ассоциированные товары (выбранные вручную)',
        'bought_together' => 'Покупают вместе с товаром',
        'category' => 'Товар схожей категории',
        'tags' => 'Общие теги'
    ],
    // Лимиты
    'limits' => [
        'mini_cart' => 8,
        'cart'      => 12,
        'checkout'  => 12,
    ],
];
