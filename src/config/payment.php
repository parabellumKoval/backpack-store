<?php

return [
    'methods' => [
        [
            'name' => 'zasilkovna',
            'type' => 'cod',
            'label' => 'Наложенный платеж Zasilkovna'
        ],[
            'name' => 'novaposhta',
            'type' => 'cod',
            'label' => 'Наложенный платеж Новая Почта'
        ],[
            'name' => 'default',
            'type' => 'cash',
            'label' => 'При получении (самовывоз)'
        ],[
            'name' => 'liqpay',
            'type' => 'online',
            'label' => 'Online-оплата Liqpay'
        ],[
            'name' => 'card',
            'type' => 'online',
            'label' => 'Online-оплата картой'
        ],[
            'name' => 'bank',
            'type' => 'transfer',
            'label' => 'Банковский перевод'
        ]
    ]
];
