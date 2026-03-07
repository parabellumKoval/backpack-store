<?php
namespace Backpack\Store\app\Services\Region\Multi;

use Illuminate\Database\Eloquent\Builder;

use Backpack\Store\app\Contracts\ProductService as Contract;
use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\SupplierProduct;

use Backpack\Store\app\Services\Currency\CurrencyConverter;
use Backpack\Store\app\Services\Campaign\CampaignResolverService;
use Backpack\Store\app\Services\Product\SupplierProductResolver;

use Illuminate\Support\Facades\DB;

class ProductService implements Contract {

  protected ?Product $product = null;
  
  public function setProduct(Product $product): self
  {
      $this->product = $product;
      return $this;
  }

  public function supplierProducts(?string $country_code = null)
  {
    $countryCode = $country_code ?? \Store::context()->country;

    return $this->product->hasMany(SupplierProduct::class)
        ->whereHas('supplier', function($query) use ($countryCode) {
            $query->whereIn('supplier_id', function($subquery) use ($countryCode) {
                $subquery->select('supplier_id')
                    ->from('ak_supplier_country')
                    ->where('country_code', $countryCode);
            });
        });
  }

  public function supplierProduct(?string $country_code = null) {
    return app(SupplierProductResolver::class)->current($this->product, $country_code);
  }

  public function price(): ?float {
    // Для абстрактных товаров или товаров без поставщика
    if(!$this->product->supplierProduct) return null;

    $prices = $this->resolvePrice();
    $price = $prices['price'] ?? null;
    
    // Округление цены товара
    if ($price !== null) {
        $decimals = \Settings::get('dress.pricing.product_price_decimal_places', 2);
        $price = round($price, $decimals);
    }
    
    return $price;
  }

  public function oldPrice(): ?float {
    // Для абстрактных товаров или товаров без поставщика
    if(!$this->product->supplierProduct) return null;

    $prices = $this->resolvePrice();
    $oldPrice = $prices['old_price'] ?? null;
    
    // Округление цены товара
    if ($oldPrice !== null) {
        $decimals = \Settings::get('dress.pricing.product_price_decimal_places', 2);
        $oldPrice = round($oldPrice, $decimals);
    }
    
    return $oldPrice;
  }

  public function currency(): ?string {
    // Для абстрактных товаров или товаров без поставщика
    if(!$this->product->supplierProduct) return null;

    $prices = $this->resolvePrice();
    return $prices['currency'] ?? null;
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

    if($override) return $this->applyCampaignPricing($override, $countryCode);

    $originCurrency = $this->product->supplierProduct->supplier->currency_code ?? $targetCurrency;
    $originPrice = $this->product->supplierProduct->price;
    $originOldPrice = $this->product->supplierProduct->old_price;

    if($originCurrency !== $targetCurrency) {
        $converted_price = $this->converter()->convert($originPrice, $originCurrency, $targetCurrency);
        $converted_old_price = $this->converter()->convert($originOldPrice, $originCurrency, $targetCurrency);

        $payload = [
            'price'      => $converted_price,
            'old_price'  => $converted_old_price,
            'currency'   => $targetCurrency,
            'source'     => 'supplier-converted'
        ];
        return $this->applyCampaignPricing($payload, $countryCode);
    }else {
        $payload = [
            'price'      => $originPrice,
            'old_price'  => $originOldPrice,
            'currency'   => $targetCurrency,
            'source'     => 'supplier'
        ];
        return $this->applyCampaignPricing($payload, $countryCode);
    }
  }

  private function applyCampaignPricing(array $payload, string $countryCode): array
  {
    if (!$this->product || !isset($payload['price']) || $payload['price'] === null) {
      return $payload;
    }

    $applied = app(CampaignResolverService::class)->applyPricing(
      productId: (int) $this->product->id,
      price: (float) $payload['price'],
      oldPrice: isset($payload['old_price']) ? (float) $payload['old_price'] : null,
      countryCode: $countryCode
    );

    $payload['price'] = $applied['price'];
    $payload['old_price'] = $applied['old_price'];
    $payload['campaign_discount_amount'] = $applied['campaign_discount_amount'];
    $payload['campaign'] = $applied['campaign'];

    return $payload;
  }
}
