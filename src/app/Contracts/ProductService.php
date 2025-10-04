<?php
namespace Backpack\Store\app\Contracts;

use Backpack\Store\app\Models\Product;

use Illuminate\Database\Eloquent\Builder;

interface ProductService {
  public function setProduct(Product $product): self;
  public function price(): ?float;
  public function oldPrice(): ?float;
  public function supplierProducts(?string $country_code = null);
  public function supplierProduct(?string $country_code = null);
}
