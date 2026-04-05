<?php
namespace Backpack\Store\app\Services\Resolvers;

use Backpack\Store\app\Services\StoreContext;
use Backpack\Store\app\Services\Store;

class StoreContextResolver
{
    public function resolve(): StoreContext
    {
        $requestKey = Store::storefrontRequestKey();
        $headerName = Store::storefrontHeaderName();

        $country  = request()->get('country')
            ?? session('country')
            ?? config('dress.multistore.default_country');

        $currency = request()->get('currency')
            ?? Store::countryCurrency($country)
            ?? session('currency')
            ?? config('dress.multistore.default_currency');

        $storefront = request()->get($requestKey)
            ?? request()->header($headerName)
            ?? session($requestKey)
            ?? Store::defaultStorefront();

        return new StoreContext(
            $country,
            $currency,
            Store::normalizeStorefrontCode($storefront) ?? Store::defaultStorefront()
        );
    }
}
