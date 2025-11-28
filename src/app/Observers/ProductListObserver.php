<?php

namespace Backpack\Store\app\Observers;

use Backpack\Store\app\Events\ProductListChanged;
use Backpack\Store\app\Models\ProductList;

class ProductListObserver
{
    public function saved(ProductList $list): void
    {
        event(ProductListChanged::for($list, 'saved'));
    }

    public function deleted(ProductList $list): void
    {
        event(ProductListChanged::for($list, 'deleted'));
    }
}
