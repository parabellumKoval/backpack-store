<?php

return [
    'title' => 'Заказы',
    'single' => 'заказ',
    'fields' => [
        'code' => 'Номер заказа',
        'created_at' => 'Дата и время заказа',
        'status' => 'Статус заказа',
        'price' => 'Сумма заказа',
        'pay_status' => 'Статус оплаты',
        'payment_method' => 'Способ оплаты',
        'payment_methods' => [
            'cash' => 'Оплата наличными',
            'liqpay' => 'Онлайн оплата'
        ],
        'customer' => [
            'title' => 'Покупатель',
            'firstname' => 'Имя',
            'lastname' => 'Фамилия',
            'email' => 'Email',
            'phone' => 'Телефон'
        ],
        'delivery' => [
            'title' => 'Доставка',
            'status' => 'Статус доставки',
            'method' => 'Метод',
            'methods' => [
                'warehouse' => 'Отделение почты',
                'address' => 'Доставка Курьером',
                'pickup' => 'Самовывоз'
            ],
            'warehouse' => 'Отделение почты',
            'city' => 'Город',
            'address' => 'Адрес',
            'zip' => 'Индекс',
            'comment' => 'Комментарий покупателя'
        ],
        'products' => [
            'title' => 'Товары в заказе'
        ],
        'hints' => [
            'price' => 'Если оставить пустым сумма будет рассчитана автоматически'
        ]
    ]
];