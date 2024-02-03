<?php

namespace Backpack\Store\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Backpack\Store\Store
 */
class Store extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Backpack\Store\Store::class;
    }
}
