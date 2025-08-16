<?php

namespace Backpack\Store\app\Models\Traits;

use Backpack\Store\app\Models\SupplierProduct;
use Backpack\Store\app\Models\ProductCountryOverride;

trait MultistoreProductTrait {  


    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    
    /**
     * Get supplier products for a specific country
     *
     * @param string $countryCode Two letter country code
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function spByCountry($countryCode)
    {
        return $this->hasMany(SupplierProduct::class)
            ->whereHas('supplier', function($query) use ($countryCode) {
                $query->whereIn('supplier_id', function($subquery) use ($countryCode) {
                    $subquery->select('supplier_id')
                        ->from('ak_supplier_country')
                        ->where('country_code', $countryCode);
                });
            });
    }

    public function countryOverrides()
    {
      return $this->hasMany(ProductCountryOverride::class, 'product_id');
    }
    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */


}