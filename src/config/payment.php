<?php

return [
    'package_methods' => [
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
            'name' => 'niftipay',
            'type' => 'online',
            'label' => 'Online-оплата Niftipay'
        ],[
            'name' => 'card',
            'type' => 'online',
            'label' => 'Online-оплата картой'
        ],[
            'name' => 'bank',
            'type' => 'transfer',
            'label' => 'Банковский перевод'
        ]
    ],

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
            'name' => 'niftipay',
            'type' => 'online',
            'label' => 'Online-оплата Niftipay'
        ],[
            'name' => 'card',
            'type' => 'online',
            'label' => 'Online-оплата картой'
        ],[
            'name' => 'bank',
            'type' => 'transfer',
            'label' => 'Банковский перевод'
        ]
    ],

    'provider_classes' => [
        'liqpay' => \Backpack\Store\app\Services\Payments\Providers\LiqpayPaymentProvider::class,
        'niftipay' => \Backpack\Store\app\Services\Payments\Providers\NiftipayPaymentProvider::class,
    ],

    'custom_provider_classes' => [
        // 'custom' => \App\Payments\CustomPaymentProvider::class,
    ],

    'custom_provider_settings' => [
        // 'custom' => [],
    ],

    'provider_settings' => [
        'liqpay' => [
            'public_key' => env('LIQPAY_PUBLIC_KEY'),
            'private_key' => env('LIQPAY_PRIVATE_KEY'),
            'callback' => env('LIQPAY_CALLBACK'),
            'result' => env('LIQPAY_RESULTS'),
            'client_url' => rtrim(env('CLIENT_URL', env('FRONT_URL', env('APP_URL', 'http://localhost:3000'))), '/'),
            'currency' => env('LIQPAY_CURRENCY', 'UAH'),
            'action' => 'pay',
            'version' => 3,
            'checkout_url' => 'https://www.liqpay.ua/api/3/checkout',
        ],

        'niftipay' => [
            'base_url' => env('NIFTIPAY_BASE_URL', 'https://www.niftipay.com'),
            'api_key' => env('NIFTIPAY_API_KEY'),
            'fiat_integration_id' => env('NIFTIPAY_FIAT_INTEGRATION_ID'),
            'webhook_secret' => env('NIFTIPAY_WEBHOOK_SECRET'),
            'service_fee_payer' => env('NIFTIPAY_SERVICE_FEE_PAYER'),
            'client_url' => rtrim(env('CLIENT_URL', env('FRONT_URL', env('APP_URL', 'http://localhost:3000'))), '/'),
            'storefronts' => [
                'main' => [
                    'client_url' => env('NIFTIPAY_CLIENT_URL_MAIN', env('CLIENT_URL_MAIN')),
                    'fiat_integration_id' => env('NIFTIPAY_FIAT_INTEGRATION_ID_MAIN'),
                    'webhook_secret' => env('NIFTIPAY_WEBHOOK_SECRET_MAIN'),
                    'service_fee_payer' => env('NIFTIPAY_SERVICE_FEE_PAYER_MAIN'),
                ],
                'kratom' => [
                    'client_url' => env('NIFTIPAY_CLIENT_URL_KRATOM', env('CLIENT_URL_KRATOM')),
                    'fiat_integration_id' => env('NIFTIPAY_FIAT_INTEGRATION_ID_KRATOM'),
                    'webhook_secret' => env('NIFTIPAY_WEBHOOK_SECRET_KRATOM'),
                    'service_fee_payer' => env('NIFTIPAY_SERVICE_FEE_PAYER_KRATOM'),
                ],
            ],
        ],
    ],
];
