<?php

namespace Backpack\Store\app\Services\Shipping\Providers;

use Backpack\Store\app\Contracts\ShippingProviderInterface;
use Backpack\Store\app\DTO\ShippingQuoteRequest;
use Backpack\Store\app\DTO\ShippingQuoteResult;

class MessengerProvider implements ShippingProviderInterface
{
    public function supports(string $methodKey): bool
    {
        return \str_starts_with($methodKey, 'messenger_');
    }

    public function quote(ShippingQuoteRequest $r): ShippingQuoteResult
    {
        if ($r->destinationCountry !== 'CZ') {
            return new ShippingQuoteResult('messenger', $r->methodKey, 'CZK', 0.0, [
                'note' => 'Destination not supported by Messenger.cz',
            ]);
        }

        $context = ['region' => $r->destinationCountry];
        $currency = (string) \Settings::get('shipping.messenger.currency', 'CZK', $context);
        $shipmentWeightG = (int) \Settings::get('shipping.messenger.shipment_weight_g', 10000, $context);
        $maxDimensionCm = (int) \Settings::get('shipping.messenger.max_dimension_cm', 40, $context);
        $fuelSurchargePercent = (float) \Settings::get('shipping.messenger.fuel_surcharge_percent', 0, $context);
        $vatIncluded = (bool) \Settings::get('shipping.messenger.vat_included', false, $context);
        $vatRate = (float) \Settings::get('shipping.messenger.vat_rate', 21.0, $context);

        $rates = $this->normalizeRates(\Settings::get('shipping.messenger.address_rates', [], $context));
        $shipmentsCount = $this->resolveShipmentsCount($r->weightG, $shipmentWeightG);
        $base = $this->pickByShipmentCount($rates, $shipmentsCount);
        $baseWithFuel = $base * (1 + ($fuelSurchargePercent / 100));

        [$net, $vat, $gross] = $this->applyVat($baseWithFuel, $vatRate, $vatIncluded);

        $codBreakdown = [];
        $codEnabled = (bool) \Settings::get('shipping.messenger.cod.enabled', true, $context);
        if ($r->codEnabled && $codEnabled) {
            $paymentType = strtolower((string) ($r->meta['cod_payment_type'] ?? 'cash'));
            $cashFee = (float) \Settings::get('shipping.messenger.cod.cash_fee', 30, $context);
            $cardFeeFixed = (float) \Settings::get('shipping.messenger.cod.card_fee_fixed', 30, $context);
            $cardFeePercent = (float) \Settings::get('shipping.messenger.cod.card_fee_percent', 1.25, $context);

            $codBase = $paymentType === 'card'
                ? $cardFeeFixed + ($r->codAmount * $cardFeePercent / 100.0)
                : $cashFee;

            [$codNet, $codVat, $codGross] = $this->applyVat($codBase, $vatRate, $vatIncluded);
            $net += $codNet;
            $vat += $codVat;
            $gross += $codGross;

            $codBreakdown = [
                'cod_payment_type' => $paymentType,
                'cod_base' => $this->round2($codBase),
                'cod_net' => $this->round2($codNet),
                'cod_vat' => $this->round2($codVat),
                'cod_gross' => $this->round2($codGross),
            ];
        }

        return new ShippingQuoteResult('messenger', $r->methodKey, $currency, $this->round2($gross), [
            'shipments_count' => $shipmentsCount,
            'shipment_weight_g' => $shipmentWeightG,
            'max_dimension_cm' => $maxDimensionCm,
            'base' => $this->round2($base),
            'base_with_fuel' => $this->round2($baseWithFuel),
            'fuel_surcharge_percent' => $this->round2($fuelSurchargePercent),
            'net' => $this->round2($net),
            'vat' => $this->round2($vat),
            'gross' => $this->round2($gross),
            'vat_rate' => $vatRate,
        ] + $codBreakdown);
    }

    protected function normalizeRates(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    protected function resolveShipmentsCount(int $weightG, int $shipmentWeightG): int
    {
        $safeWeight = max(1, $weightG);
        $perShipment = max(1, $shipmentWeightG);

        return (int) ceil($safeWeight / $perShipment);
    }

    protected function pickByShipmentCount(array $rates, int $shipmentsCount): float
    {
        $normalized = collect($rates)
            ->map(function ($row) {
                return [
                    'shipments_count' => (int) ($row['shipments_count'] ?? 0),
                    'price' => (float) ($row['price'] ?? 0),
                ];
            })
            ->filter(fn (array $row) => $row['shipments_count'] > 0)
            ->sortBy('shipments_count')
            ->values();

        foreach ($normalized as $row) {
            if ($shipmentsCount <= $row['shipments_count']) {
                return (float) $row['price'];
            }
        }

        $last = $normalized->last();
        return $last ? (float) $last['price'] : 0.0;
    }

    protected function applyVat(float $base, float $ratePerc, bool $included): array
    {
        if ($base <= 0.0 || $ratePerc <= 0.0) {
            return [$base, 0.0, $base];
        }

        if ($included) {
            $net = $base / (1.0 + $ratePerc / 100.0);
            $vat = $base - $net;
            return [$this->round2($net), $this->round2($vat), $this->round2($base)];
        }

        $vat = $base * ($ratePerc / 100.0);
        $gross = $base + $vat;

        return [$this->round2($base), $this->round2($vat), $this->round2($gross)];
    }

    protected function round2(float $value): float
    {
        return round($value, 2);
    }
}
