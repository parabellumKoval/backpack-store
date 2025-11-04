<?php
namespace Backpack\Store\app\Services\Variant\Vertical;

use Illuminate\Database\Eloquent\Builder;

use Backpack\Store\app\Contracts\Modification as Contract;
use Backpack\Store\app\Models\Product;

class Modification implements Contract {
    public function get(Product $product) {
      return $product->children()->available()->get()->sortBy('price');
    }
}
