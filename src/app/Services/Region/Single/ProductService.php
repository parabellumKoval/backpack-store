<?php
namespace Backpack\Store\app\Services\Region\Single;

use Backpack\Store\app\Contracts\ProductService as Contract;
use \Backpack\Store\app\Services\Resolvers\SupplierProductResolver;

use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\SupplierProduct;

class ProductService implements Contract {

  protected ?Product $product = null;
  
  public function setProduct(Product $product): self
  {
      $this->product = $product;
      return $this;
  }

  public function supplierProducts()
  {
    return $this->product->hasMany(SupplierProduct::class);
  }


  public function supplierProduct() {
    return app(SupplierProductResolver::class)->current($this->product);
  }

  public function price() {
    return 0;
  }

  public function oldPrice() {
    return 0;
  }
}
