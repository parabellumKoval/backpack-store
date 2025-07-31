<?php

return [
  'parameters' => [
    'description' => [
        'unit' => 'символы',
        'ideal' => 2000, // 100% при >=2000 символов
        'weight' => 30, // % в общей оценке
    ],
    'images' => [
        'unit' => 'шт',
        'ideal' => 5, // 5 фото = 100%
        'weight' => 20,
    ],
    'properties' => [
        'unit' => 'шт',
        'ideal' => 5,
        'weight' => 15,
    ],
    'brand' => [
        'unit' => 'наличие',
        'ideal' => 1,
        'weight' => 10,
    ],
    'category' => [
        'unit' => 'наличие',
        'ideal' => 1,
        'weight' => 10,
    ],
    'name' => [
        'unit' => 'символы',
        'ideal' => 50,
        'weight' => 15,
    ]
  ]
];