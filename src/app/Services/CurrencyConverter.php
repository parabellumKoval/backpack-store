<?php

namespace Backpack\Store\app\Services;

use Backpack\Store\app\Contracts\ExchangeRateProvider;

class CurrencyConverter
{
    protected ExchangeRateProvider $provider;

    public function __construct(ExchangeRateProvider $provider)
    {
        $this->provider = $provider;
    }

    public function convert(float $amount, string $fromCurrency, string $toCurrency): float
    {
        $rate = $this->provider->getExchangeRate($fromCurrency, $toCurrency);
        return round($amount * $rate, 2);
    }
}
