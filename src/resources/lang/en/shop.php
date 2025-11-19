<?php

return [
  'fieldType' => [
    'checkbox' => 'Множественный выбор',
    'radio' => 'Единичный выбор',
    'number' => 'Число',
    'string' => 'Свободная строка',
    'color' => 'Цвет'
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
    'default_cash' => 'Cash on pickup',
    'zasilkovna_cod' => 'Cash on delivery (Packeta)',
    'novaposhta_cod' => 'Cash on delivery (Nova Poshta)',
    'liqpay_online' => 'LiqPay online payment',
    'card_online' => 'Card payment online',
    'bank_transfer' => 'Bank transfer',
  ],

  'delivery_methods' => [
    'novaposhta_address' => 'Nova Poshta courier',
    'novaposhta_warehouse' => 'Nova Poshta pickup point',
    'packeta_address' => 'Packeta courier',
    'packeta_warehouse' => 'Packeta pickup point',
    'default_pickup' => 'Store pickup',
  ],
];
