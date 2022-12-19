<?php

namespace Backpack\Store\app\Traits;

trait HasCart {
  public function carts() {
    return $this->HasMany('\Backpack\Store\app\Models\Cart', 'user_id')->with('product');
  }
}