<?php

return [
  'model' => 'Backpack\Store\app\Models\Product',

  'model_admin' => 'Backpack\Store\app\Models\Admin\Product',

  'image' => [
    'enable' => true,
    'base_path' => '/public/images/products'
  ],
  
  'modifications' => [
    'enable' => false
  ],

  'resource' => [
    // PRODUCT -> resources
    'tiny' => 'Backpack\Store\app\Http\Resources\ProductTinyResource',
    
    // Small product resource used for catalog pages (index route)
    'small' => 'Backpack\Store\app\Http\Resources\ProductSmallResource',
    'medium' => 'Backpack\Store\app\Http\Resources\ProductMediumResource',
    'kratom_small' => 'Backpack\Store\app\Http\Resources\ProductKratomSmallResource',
    
    // Large product resource used for product page (show route)
    'large' => 'Backpack\Store\app\Http\Resources\ProductLargeResource',
    'kratom_large' => 'Backpack\Store\app\Http\Resources\ProductKratomLargeResource',

    // Cart product resource used for order
    'cart' => 'Backpack\Store\app\Http\Resources\ProductCartResource',
  ]
];
