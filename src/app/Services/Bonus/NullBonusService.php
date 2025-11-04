<?php

namespace Backpack\Store\app\Services\Bonus;

use Backpack\Store\app\Contracts\BonusService;
use Backpack\Store\app\DTO\BonusRedemption;

class NullBonusService implements BonusService
{
    public function canSpend(int $userId, float $points): bool
    {
        return $points <= 0;
    }

    public function spend(
        int $userId,
        float $points,
        string $orderReference,
        string $orderCurrency,
        array $context = []
    ): BonusRedemption {
        return new BonusRedemption(points: 0.0, fiatAmount: 0.0, fiatCurrency: $orderCurrency);
    }

    public function refund(
        int $userId,
        float $points,
        string $orderReference,
        string $orderCurrency,
        array $context = []
    ): void {
        // nothing to do
    }
}

