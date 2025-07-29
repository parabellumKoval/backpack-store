<?php
namespace Backpack\Store\app\Contracts;

interface ExchangeRateProvider
{
    public function getExchangeRate(string $fromCurrency, string $toCurrency): float;
}