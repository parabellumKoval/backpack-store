<?php
namespace Backpack\Store\app\Services\Resolvers;

use Backpack\Store\app\Services\StoreContext;
use Backpack\Store\app\Services\Store;

class StoreContextResolver
{
    public function resolve(): StoreContext
    {
        $country  = request()->get('country')
            ?? session('country')
            ?? config('dress.multistore.default_country');

        $currency = request()->get('currency')
            ?? Store::countryCurrency($country)
            ?? session('currency')
            ?? config('dress.multistore.default_currency');

        return new StoreContext($country, $currency);
    }
}
