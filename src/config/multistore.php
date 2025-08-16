<?php

return [
  'enabled' => true,
  
  'default_country' => 'cz',
  
  'default_currency' => 'CZK',

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
      'delivery' => [],
      'payment' => []
    ],
    'es' => [
      'enabled' => true,
      'country' => 'Spain',
      'locale' => 'es',
      'delivery' => [],
      'payment' => []
    ],
    'de' => [
      'enabled' => true,
      'country' => 'Germany',
      'locale' => 'de',
      'delivery' => [],
      'payment' => []
    ],
    'cz' => [
      'enabled' => true,
      'country' => 'Czech',
      'locale' => 'cz',
      'delivery' => [],
      'payment' => []
    ]
  ]
];