<?php

return [
  'fieldType' => [
    'checkbox' => 'Множественный выбор',
    'radio' => 'Единичный выбор',
    'number' => 'Число',
    'string' => 'Свободная строка',
    'color' => 'Цвет',
    'colors' => 'colors'
  ],

  "order_status" => [
    "new" => "Новый",
    "canceled" => "Отменен",
    "failed" => "Ошибка",
    "completed" => "Выполнен",
  ],

  "pay_status" => [
    "waiting" => "Ожидает оплаты",
    "failed" => "Ошибка",
    "paied" => "Оплачен"
  ],

  "delivery_status" => [
    "waiting" => "Ожидает отправки",
    "sent" => "Отправлен",
    "failed" => "Ошибка",
    "delivered" => "Доставлен",
    "pickedup" => "Забран"
  ],

  'payment_methods' => [
    'default_cash' => 'Оплата наличными',
    'zasilkovna_cod' => 'Наложенный платёж Zásilkovna',
    'novaposhta_cod' => 'Наложенный платёж Новая Почта',
    'messenger_cod' => 'Наложенный платёж Messenger.cz',
    'liqpay_online' => 'Оплата LiqPay онлайн',
    'niftipay_online' => 'Оплата Niftipay онлайн',
    'card_online' => 'Оплата картой онлайн',
    'bank_transfer' => 'Банковский перевод',
  ],

  'delivery_methods' => [
    'novaposhta_address' => 'Курьер Новая Почта',
    'novaposhta_warehouse' => 'Отделение Новая Почта',
    'packeta_address' => 'Курьер Packeta',
    'packeta_warehouse' => 'Пункт выдачи Packeta',
    'messenger_address' => 'Курьер Messenger.cz',
    'default_pickup' => 'Самовывоз',
  ],
];
