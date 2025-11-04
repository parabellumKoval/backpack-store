<?php

namespace Backpack\Store\app\DTO;

class ShippingQuoteResult
{
    public string $provider;     // 'packeta' | 'novaposhta' | ...
    public string $methodKey;    // исходный ключ
    public string $currency;     // валюта провайдера (из настроек)
    public float  $amount;       // ИТОГО
    public array  $breakdown;    // детализация: base, vat, cod_surcharge, insurance, ...

    public function __construct(string $provider, string $methodKey, string $currency, float $amount, array $breakdown = [])
    {
        $this->provider  = $provider;
        $this->methodKey = $methodKey;
        $this->currency  = $currency;
        $this->amount    = round($amount, 2);
        $this->breakdown = $breakdown;
    }
}
