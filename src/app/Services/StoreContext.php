<?php
namespace Backpack\Store\app\Services;

final class StoreContext
{
    public function __construct(
        public readonly string $country,
        public readonly string $currency,
        public readonly string $storefront,
        // на будущее: public readonly ?string $locale = null, ...
    ) {}
}
