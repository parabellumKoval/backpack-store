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
    'default_cash' => 'Готівкою при самовивозі',
    'zasilkovna_cod' => 'Накладений платіж Zásilkovna',
    'novaposhta_cod' => 'Накладений платіж Нова Пошта',
    'messenger_cod' => 'Накладений платіж Messenger.cz',
    'liqpay_online' => 'Оплата LiqPay онлайн',
    'card_online' => 'Оплата карткою онлайн',
    'bank_transfer' => 'Банківський переказ',
  ],

  'delivery_methods' => [
    'novaposhta_address' => 'Кур\'єр Нова Пошта',
    'novaposhta_warehouse' => 'Відділення Нова Пошта',
    'packeta_address' => 'Кур\'єр Packeta',
    'packeta_warehouse' => 'Пункт видачі Packeta',
    'messenger_address' => 'Кур\'єр Messenger.cz',
    'default_pickup' => 'Самовивіз',
  ],
];
