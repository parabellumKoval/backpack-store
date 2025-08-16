<?php
namespace Backpack\Store\app\Services\Region\Multi;

use Illuminate\Database\Eloquent\Builder;

use Backpack\Store\app\Contracts\ProductService as Contract;
use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\SupplierProduct;

use Backpack\Store\Services\Currency\CurrencyConverter;

use Illuminate\Support\Facades\DB;

class ProductService implements Contract {

  protected ?Product $product = null;
  
  public function setProduct(Product $product): self
  {
      $this->product = $product;
      return $this;
  }

  public function supplierProducts()
  {
    $countryCode = \Store::country();

    return $this->product->hasMany(SupplierProduct::class)
        ->whereHas('supplier', function($query) use ($countryCode) {
            $query->whereIn('supplier_id', function($subquery) use ($countryCode) {
                $subquery->select('supplier_id')
                    ->from('ak_supplier_country')
                    ->where('country_code', $countryCode);
            });
        });
  }

  public function supplierProduct() {
    return app(SupplierProductResolver::class)->current($this->product);
  }

  public function price(): ?float {
    // Для абстрактных товаров или товаров без поставщика
    if(!$this->product->supplierProduct) return null;

    $prices = $this->resolvePrice();
    return $prices['price'] ?? null;
  }

  public function oldPrice(): ?float {
    // Для абстрактных товаров или товаров без поставщика
    if(!$this->product->supplierProduct) return null;

    $prices = $this->resolvePrice();
    return $prices['old_price'] ?? null;
  }

  private function converter() {
    return app(CurrencyConverter::class);
  }

  private function getOverridesInTargetCurrency(string $countryCode, string $targetCurrency): array {
    $override = $this->product->countryOverrides()
        ->where('country_code', $countryCode)
        ->first();
  
    if ($override && $override->price_override !== null) {
        if ($override->currency_code === $targetCurrency) {
            return [
                'price' => $override->price_override,
                'old_price'  => $override->old_price_override,
                'currency'   => $override->currency_code,
                'source'     => 'override'
            ];
        }

        $converted_price = $this->converter()->convert($override->price_override, $override->currency_code, $targetCurrency);
        $converted_old_price = $this->converter()->convert($override->old_price_override, $override->currency_code, $targetCurrency);

        return [
            'price'      => $converted_price,
            'old_price'  => $converted_old_price,
            'currency'   => $targetCurrency,
            'source'     => 'override-converted'
        ];
    }else {
        return [];
    }
  }

private function resolvePrice() {
    $countryCode = \Store::context()->country;
    $targetCurrency = \Store::context()->currency;

    $override = $this->getOverridesInTargetCurrency($countryCode, $targetCurrency);

    if($override) return $override;

    $originCurrency = $this->product->supplierProduct->supplier->currency_code ?? $targetCurrency;
    $originPrice = $this->product->supplierProduct->price;
    $originOldPrice = $this->product->supplierProduct->old_price;

    if($originCurrency !== $targetCurrency) {
        $converted_price = $this->converter()->convert($originPrice, $originCurrency, $targetCurrency);
        $converted_old_price = $this->converter()->convert($originOldPrice, $originCurrency, $targetCurrency);

        return [
            'price'      => $converted_price,
            'old_price'  => $converted_old_price,
            'currency'   => $targetCurrency,
            'source'     => 'supplier-converted'
        ];
    }else {
        return [
            'price'      => $originPrice,
            'old_price'  => $originOldPrice,
            'currency'   => $targetCurrency,
            'source'     => 'supplier'
        ];
    }
  }
}
