<?php

return [
  'enable' => true,

  'model' => 'Backpack\Store\app\Models\Attribute',
  'model_admin' => 'Backpack\Store\app\Models\Attribute',

  // Is pivot values translatable
  'translatable_value' => false,

  'resource' => [
    'product' => 'Backpack\Store\app\Http\Resources\AttributeProductResource',
    'large' => 'Backpack\Store\app\Http\Resources\AttributeLargeResource',
    'small' => 'Backpack\Store\app\Http\Resources\AttributeSmallResource'
  ]
];