<?php

return [
    'payments' => [
        'default_cash' => [
            'label' => 'backpack-store::shop.payment_methods.default_cash',
            'icon' => 'la la-wallet',
        ],
        'zasilkovna_cod' => [
            'label' => 'backpack-store::shop.payment_methods.zasilkovna_cod',
            'icon' => 'la la-parachute-box',
        ],
        'novaposhta_cod' => [
            'label' => 'backpack-store::shop.payment_methods.novaposhta_cod',
            'icon' => 'la la-shipping-fast',
        ],
        'messenger_cod' => [
            'label' => 'backpack-store::shop.payment_methods.messenger_cod',
            'icon' => 'la la-truck-loading',
        ],
        'liqpay_online' => [
            'label' => 'backpack-store::shop.payment_methods.liqpay_online',
            'icon' => 'la la-qrcode',
        ],
        'niftipay_online' => [
            'label' => 'backpack-store::shop.payment_methods.niftipay_online',
            'icon' => 'la la-credit-card',
        ],
        'card_online' => [
            'label' => 'backpack-store::shop.payment_methods.card_online',
            'icon' => 'la la-credit-card',
        ],
        'bank_transfer' => [
            'label' => 'backpack-store::shop.payment_methods.bank_transfer',
            'icon' => 'la la-university',
        ],
    ],

    'deliveries' => [
        'novaposhta_address' => [
            'label' => 'backpack-store::shop.delivery_methods.novaposhta_address',
            'icon' => 'la la-truck',
        ],
        'novaposhta_warehouse' => [
            'label' => 'backpack-store::shop.delivery_methods.novaposhta_warehouse',
            'icon' => 'la la-warehouse',
        ],
        'packeta_address' => [
            'label' => 'backpack-store::shop.delivery_methods.packeta_address',
            'logo' => 'packages/backpack/store/img/providers/packeta.svg',
            'logo_alt' => 'Packeta',
        ],
        'packeta_warehouse' => [
            'label' => 'backpack-store::shop.delivery_methods.packeta_warehouse',
            'logo' => 'packages/backpack/store/img/providers/packeta.svg',
            'logo_alt' => 'Packeta',
        ],
        'messenger_address' => [
            'label' => 'backpack-store::shop.delivery_methods.messenger_address',
            'icon' => 'la la-truck-loading',
        ],
        'messenger_express' => [
            'label' => 'backpack-store::shop.delivery_methods.messenger_express',
            'icon' => 'la la-bolt',
        ],
        'default_pickup' => [
            'label' => 'backpack-store::shop.delivery_methods.default_pickup',
            'icon' => 'la la-store',
        ],
    ],
];
