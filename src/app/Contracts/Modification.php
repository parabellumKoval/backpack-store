<?php
namespace Backpack\Store\app\Contracts;

use Backpack\Store\app\Models\Product;

interface Modification {
  public function get(Product $product);
}
