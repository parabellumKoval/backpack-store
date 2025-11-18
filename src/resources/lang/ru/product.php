<?php

return [
    'entity_singular' => 'товар',
    'entity_plural' => 'товары',
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
    ],
    'orders_tab' => [
        'tab_title' => 'Заказы',
        'title' => 'Заказы',
        'summary' => [
            'orders' => 'Заказы',
            'quantity' => 'Куплено, шт.',
            'revenue' => 'Выручка',
            'revenue_empty' => 'Недостаточно данных по выручке.',
        ],
        'chart' => [
            'title' => 'Динамика покупок по месяцам',
            'quantity_label' => 'Количество',
            'revenue_label' => 'Сумма',
        ],
        'table' => [
            'order' => 'Заказ',
            'status' => 'Статусы',
            'customer' => 'Клиент',
            'quantity' => 'Кол-во',
            'price' => 'Цена',
            'total' => 'Итого',
        ],
        'messages' => [
            'loading' => 'Загружаем заказы…',
            'empty' => 'Заказов пока нет.',
            'error' => 'Не удалось загрузить заказы, попробуйте позже.',
            'chart_empty' => 'Для графика пока нет данных.',
        ],
        'pagination' => [
            'showing' => 'Показаны :from–:to из :total заказов',
        ],
    ],
];