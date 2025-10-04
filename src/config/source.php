<?php

return [
  'enable' => true,
  
  'model' => 'Backpack\Store\app\Models\Source',
  
  'model_admin' => 'Backpack\Store\app\Models\Admin\Source',
  
  'model_upload_history' => 'Backpack\Store\app\Models\UploadHistory',

  'test' => [
    'enable' => env('STORE_SOURCE_TEST', false),
    'items' => env('STORE_SOURCE_TEST_ITEMS', 1)
  ]
];