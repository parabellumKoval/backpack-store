<?php

namespace Backpack\Store\app\Contracts;

use Backpack\Store\app\DTO\BonusRedemption;

interface BonusService
{
    /**
     * Ensure that the user can spend the requested number of bonus points.
     * Should return true when spending is possible, false otherwise.
     */
    public function canSpend(int $userId, float $points): bool;

    /**
     * Reserve and charge the requested bonus points.
     *
     * @param  int    $userId
     * @param  float  $points         Amount of bonus points to spend.
     * @param  string $orderReference Unique identifier used for tracing the transaction.
     * @param  string $orderCurrency  Currency code of the order totals (ISO 4217).
     * @param  array  $context        Extra context for logging (e.g. order id, code).
     */
    public function spend(
        int $userId,
        float $points,
        string $orderReference,
        string $orderCurrency,
        array $context = []
    ): BonusRedemption;

    /**
     * Return bonus points back to the user.
     *
     * @param  int    $userId
     * @param  float  $points
     * @param  string $orderReference
     * @param  string $orderCurrency
     * @param  array  $context
     */
    public function refund(
        int $userId,
        float $points,
        string $orderReference,
        string $orderCurrency,
        array $context = []
    ): void;
}

