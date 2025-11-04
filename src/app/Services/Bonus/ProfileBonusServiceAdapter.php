<?php

namespace Backpack\Store\app\Services\Bonus;

use Backpack\Store\app\Contracts\BonusService;
use Backpack\Store\app\DTO\BonusRedemption;
use Backpack\Profile\app\Contracts\BonusAccount;

class ProfileBonusServiceAdapter implements BonusService
{
    public function __construct(
        protected BonusAccount $bonusAccount
    ) {
    }

    public function canSpend(int $userId, float $points): bool
    {
        return $this->bonusAccount->canSpend($userId, $points);
    }

    public function spend(
        int $userId,
        float $points,
        string $orderReference,
        string $orderCurrency,
        array $context = []
    ): BonusRedemption {
        $transaction = $this->bonusAccount->spend($userId, $points, array_merge($context, [
            'currency' => $orderCurrency,
            'reference_type' => $context['reference_type'] ?? 'order',
            'reference_id' => $context['reference_id'] ?? $orderReference,
        ]));

        return new BonusRedemption(
            points: $transaction->points,
            fiatAmount: $transaction->fiatAmount,
            fiatCurrency: $transaction->fiatCurrency,
            meta: array_merge($transaction->meta ?? [], [
                'wallet_currency' => $transaction->walletCurrency,
            ])
        );
    }

    public function refund(
        int $userId,
        float $points,
        string $orderReference,
        string $orderCurrency,
        array $context = []
    ): void {
        $this->bonusAccount->refund($userId, $points, array_merge($context, [
            'currency' => $orderCurrency,
            'reference_type' => $context['reference_type'] ?? 'order',
            'reference_id' => $context['reference_id'] ?? $orderReference,
        ]));
    }
}

