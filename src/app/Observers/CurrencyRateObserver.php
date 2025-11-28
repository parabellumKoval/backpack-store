<?php

namespace Backpack\Store\app\Observers;

use Backpack\Store\app\Events\CurrencyRateChanged;
use Backpack\Store\app\Models\Admin\CurrencyRate;

class CurrencyRateObserver
{
    public function saved(CurrencyRate $rate): void
    {
        event(CurrencyRateChanged::for($rate));
    }
}
