<?php

namespace Backpack\Store\app\Contracts;

use Backpack\Store\app\Dto\ShippingQuoteRequest;
use Backpack\Store\app\Dto\ShippingQuoteResult;

interface ShippingProviderInterface
{
    /**
     * Возвращает true, если провайдер умеет считать данный ключ способа доставки (name_type).
     * Пример ключа: 'novaposhta_warehouse', 'packeta_address'
     */
    public function supports(string $methodKey): bool;

    /**
     * Основной расчёт.
     */
    public function quote(ShippingQuoteRequest $request): ShippingQuoteResult;
}
