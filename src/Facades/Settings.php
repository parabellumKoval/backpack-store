<?php

namespace Backpack\Store\Facades;

use Illuminate\Support\Facades\Facade;

class Settings extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'store.settings';
    }
}
