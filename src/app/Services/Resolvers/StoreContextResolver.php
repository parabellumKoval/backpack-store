<?php

// src/Services/Store/StoreContextResolver.php
namespace Backpack\Store\app\Services\Resolvers;

use Backpack\Store\app\Services\StoreContext;

class StoreContextResolver
{
    public function resolve(): StoreContext
    {
        $country  = request()->get('country')
            ?? session('country')
            ?? config('backpack.multistore.default_country');

        $currency = request()->get('currency')
            ?? session('currency')
            ?? config('backpack.multistore.default_currency');

        return new StoreContext($country, $currency);
    }
}
