<?php
namespace Backpack\Store\app\Services\Variant\Horizontal;

use Illuminate\Database\Eloquent\Builder;

use Backpack\Store\app\Contracts\Modification as Contract;
use Backpack\Store\app\Models\Product;

class Modification implements Contract {
    public function get(Product $product) {

      if($product->children->count())
      {
        return $product->children;
      }
      else if($product->parent)
      {
        $parent_children = clone $product->parent->children()->where('id', '!=', $product->id)->get();
        return $parent_children->prepend($product->parent);
      }
    }
}
