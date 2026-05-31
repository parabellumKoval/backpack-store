<?php

return [
    'methods' => [
        [
            'name' => 'novaposhta',
            'type' => 'address',
            'label' => 'Адресная доставка Новая Почта',
            'calculable' => true
        ],[
            'name' => 'novaposhta',
            'type' => 'warehouse',
            'label' => 'Отделение/почтомат Новая Почта',
            'calculable' => true
        ],[
            'name' => 'packeta',
            'type' => 'address',
            'label' => 'Адресная доставка Zasilkovna',
            'calculable' => true
        ],[
            'name' => 'packeta',
            'type' => 'warehouse',
            'label' => 'Отделение/почтомат Zasilkovna',
            'calculable' => true
        ],[
            'name' => 'messenger',
            'type' => 'address',
            'label' => 'Курьер Messenger.cz',
            'calculable' => true
        ],[
            'name' => 'messenger',
            'type' => 'express',
            'label' => 'Экспресс-курьер Messenger.cz',
            'calculable' => true
        ],[
            'name' => 'default',
            'type' => 'pickup',
            'label' => 'Самовывоз',
            'calculable' => false
        ],[
            'name' => 'default',
            'type' => 'address',
            'label' => 'Адресная доставка',
            'calculable' => false
        ]
    ]
];
