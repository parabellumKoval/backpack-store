<?php

use \Backpack\Reviews\app\Traits\ReviewFields;
use \Backpack\Store\app\Traits\OrderFields;


$class = '
  namespace Backpack\Store\app\Http\Controllers\Admin\Base;

  use Backpack\CRUD\app\Http\Controllers\CrudController;

  use \Backpack\Reviews\app\Traits\ReviewFields;
  use \Backpack\Store\app\Traits\OrderFields;

  class ProductCrudBase extends CrudController { ';

  if(trait_exists(ReviewFields::class) && config('backpack.store.enable_reviews_in_product_crud', false)) {
    $class .= 'use ReviewFields; ';
  }

  if(trait_exists(OrderFields::class) && config('backpack.store.enable_orders_in_product_crud', false)) {
    $class .= 'use OrderFields; ';
  }

$class .= ' }';

eval ($class);