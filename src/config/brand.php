<?php

return [
  'enable' => true,

  'model' => 'Backpack\Store\app\Models\Brand',
  
  'model_admin' => 'Backpack\Store\app\Models\Brand',

  'image' => [
    'base_path' => '/public/images/brands'
  ],

  'resource' => [
    'large' => 'Backpack\Store\app\Http\Resources\BrandLargeResource',
    'small' => 'Backpack\Store\app\Http\Resources\BrandSmallResource',
    'product' => 'Backpack\Store\app\Http\Resources\BrandProductResource',
    'filter' => 'Backpack\Store\app\Http\Resources\BrandFilterResource',
    'filter_tiny' => 'Backpack\Store\app\Http\Resources\BrandFilterTinyResource'
  ],

  'alpha_groups' => [
    'patterns' => [
      '/[А-Яа-яЁё]/u',
      '/[0-9]/u',
      '/[A-Za-z]/u',
    ]
  ]
];