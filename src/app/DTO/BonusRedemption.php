<?php

namespace Backpack\Store\app\DTO;

class BonusRedemption
{
    public function __construct(
        public readonly float $points,
        public readonly float $fiatAmount,
        public readonly string $fiatCurrency,
        public readonly ?array $meta = null
    ) {
    }

    public function toArray(): array
    {
        return [
            'points' => $this->points,
            'fiat_amount' => $this->fiatAmount,
            'fiat_currency' => $this->fiatCurrency,
            'meta' => $this->meta,
        ];
    }
}

