<?php 

return [
  'enable' => true,

  'model' => 'Backpack\Store\app\Models\Promocode',
  'model_admin' => 'Backpack\Store\app\Models\Promocode',

  'resource' => [
    'large' => 'Backpack\Store\app\Http\Resources\PromocodeLargeResource',
    'small' => 'Backpack\Store\app\Http\Resources\PromocodeSmallResource'
  ],
];
