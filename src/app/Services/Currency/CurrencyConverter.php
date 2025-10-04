<?php

namespace Backpack\Store\app\Services\Currency;

use Backpack\Store\app\Contracts\ExchangeRateProvider;

class CurrencyConverter
{
    protected ExchangeRateProvider $provider;

    public function __construct(ExchangeRateProvider $provider)
    {
        $this->provider = $provider;
    }

    public function convert(float|null $amount, string $fromCurrency, string $toCurrency, int $fixTo = 2): float|null
    {
        if($amount === null || !is_numeric($amount))
            return $amount;

        $rate = $this->provider->getExchangeRate($fromCurrency, $toCurrency);
        return round($amount * $rate, $fixTo);
    }
}
