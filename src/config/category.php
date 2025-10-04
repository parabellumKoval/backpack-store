<?php

return [
  'model' => 'Backpack\Store\app\Models\Category',
  
  'model_admin' => 'Backpack\Store\app\Models\Category',

  'depth_level' => 3,

  'per_page' => 12,

  'image' => [
    'base_path' => '/public/images/categories/'
  ],

  'resource' => [
    'tiny' => 'Backpack\Store\app\Http\Resources\CategoryTinyResource',
    'small' => 'Backpack\Store\app\Http\Resources\CategorySmallResource',
    'large' => 'Backpack\Store\app\Http\Resources\CategoryLargeResource',
  ]
];