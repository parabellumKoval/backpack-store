<?php

namespace Backpack\Store\app\Listeners;

use Backpack\Store\app\Contracts\BonusService;
use Backpack\Store\app\Models\Order;
use Illuminate\Support\Facades\Log;

class RefundOrderBonuses
{
    public function __construct(
        protected BonusService $bonusService
    ) {
    }

    public function handle($event): void
    {
        $order = $event->order instanceof Order ? $event->order : null;

        if (!$order) {
            return;
        }

        if (!$this->shouldRefund($order)) {
            return;
        }

        $userId = $order->orderable_id;
        if (!$userId) {
            return;
        }

        $points = $this->extractPoints($order);
        if ($points <= 0) {
            return;
        }

        $orderCurrency = $order->currency_code ?? \Store::countryCurrency($order->country_code);

        $info = $order->info ?? [];
        $referenceId = (string)($info['bonuses']['reference_id'] ?? $order->id);

        try {
            $this->bonusService->refund(
                (int)$userId,
                $points,
                $referenceId,
                $orderCurrency,
                [
                    'reference_type' => 'order',
                    'reference_id' => $referenceId,
                    'order_code' => $order->code,
                    'reason' => 'order_refund',
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to refund bonuses for order '.$order->id, [
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        $info['bonuses']['refunded'] = true;

        if (!($event instanceof \Backpack\Store\app\Events\OrderDeleted)) {
            $order->info = $info;
            $order->save();
        }
    }

    protected function shouldRefund(Order $order): bool
    {
        $info = $order->info ?? [];

        if (!empty($info['bonuses']['refunded'])) {
            return false;
        }

        $points = $info['bonuses']['points'] ?? 0;
        $fiat = $info['bonuses']['fiat_amount'] ?? ($info['bonusesUsed'] ?? 0);

        return ($points > 0) || ($fiat > 0);
    }

    protected function extractPoints(Order $order): float
    {
        $info = $order->info ?? [];
        $points = (float)($info['bonuses']['points'] ?? 0);

        return round($points, 6);
    }
}
