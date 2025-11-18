<?php

namespace Backpack\Store\Facades;

use Illuminate\Support\Facades\Facade;
use Backpack\Store\app\Services\Product\ProductOrdersAttachService;

/**
 * @method static void attachToCrud(\Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud, string $tab = null, array $options = [])
 */
class ProductOrders extends Facade
{
    protected static function getFacadeAccessor()
    {
        return ProductOrdersAttachService::class;
    }
}
