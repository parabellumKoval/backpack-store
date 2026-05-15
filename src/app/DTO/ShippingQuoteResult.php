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
        $this->amount    = $this->normalizeAmount($amount);
        $this->breakdown = $breakdown;
    }

    protected function normalizeAmount(float $amount): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        return (float) floor($amount + 1e-9);
    }
}
