<?php
namespace Backpack\Store\app\Contracts;

use Backpack\Store\app\Models\Product;

interface PricingService {
  public function finalPrice(Product $product): ?int;
}
