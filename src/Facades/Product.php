<?php

namespace ParabellumKoval\Product\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \ParabellumKoval\Product\Product
 */
class Product extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \ParabellumKoval\Product\Product::class;
    }
}
