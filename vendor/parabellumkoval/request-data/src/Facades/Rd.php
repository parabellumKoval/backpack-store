<?php

namespace Rd\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Backpack\Store\Store
 */
class Rd extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Rd\RequestData::class;
    }
}
