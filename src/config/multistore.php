<?php

return [
  'enabled' => true,
  
  'support_global' => true,
  
  'default_country' => 'zz',
  
  'default_currency' => 'USD',

  'currencies' => [
    [
      'enabled' => true,
      'name' => 'EUR (Euro)',
      'code' => 'EUR',
      'key' => 'eur'
    ],[
      'enabled' => true,
      'name' => 'CZK (Czech crown)',
      'code' => 'CZK',
      'key' => 'czk'
    ],[
      'enabled' => true,
      'name'  => 'UAH (Ukrainian hryvnia)',
      'code' => 'UAH',
      'key' => 'uah'
    ]
  ],

  'countries' => [
    'uk' => [
      'enabled' => true,
      'country' => 'Ukraine',
      'locale' => 'uk',
      'code' => 'uk',
      'currency' => 'UAH',
      'delivery' => [],
      'payment' => []
    ],
    'es' => [
      'enabled' => true,
      'country' => 'Spain',
      'locale' => 'es',
      'code' => 'es',
      'currency' => 'EUR',
      'delivery' => [],
      'payment' => []
    ],
    'de' => [
      'enabled' => true,
      'country' => 'Germany',
      'locale' => 'de',
      'code' => 'de',
      'currency' => 'EUR',
      'delivery' => [],
      'payment' => []
    ],
    'cz' => [
      'enabled' => true,
      'country' => 'Czech',
      'locale' => 'cz',
      'code' => 'cz',
      'currency' => 'CZK',
      'delivery' => [],
      'payment' => []
    ]
  ]
];