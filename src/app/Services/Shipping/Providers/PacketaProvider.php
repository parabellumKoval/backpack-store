<?php

namespace Backpack\Store\app\Services\Shipping\Providers;

use Backpack\Store\app\Contracts\ShippingProviderInterface;
use Backpack\Store\app\DTO\ShippingQuoteRequest;
use Backpack\Store\app\DTO\ShippingQuoteResult;

class PacketaProvider implements ShippingProviderInterface
{
    public function supports(string $methodKey): bool
    {
        // Поддерживаем и адрес (address), и пункт выдачи (warehouse)
        return \str_starts_with($methodKey, 'packeta_');
    }

    public function quote(ShippingQuoteRequest $r): ShippingQuoteResult
    {
        // Ограничение стран назначения: CZ, DE, ES
        $allowed = ['CZ', 'DE', 'ES'];
        if (!\in_array($r->destinationCountry, $allowed, true)) {
            return new ShippingQuoteResult('packeta', $r->methodKey, 'CZK', 0.0, [
                'note' => 'Destination not supported by Packeta',
            ]);
        }

        // Валюта тарифов
        $currency = (string) \Settings::get('shipping.zasilkovna.currency', 'CZK');

        // Базовый тариф по весу
        // type: 'warehouse' => pickup_rates, 'address' => home_rates
        $rateListKey = \str_ends_with($r->methodKey, '_warehouse') ? 'pickup_rates' : 'home_rates';
        $rates = \Settings::get("shipping.zasilkovna.$rateListKey", [], ['region' => $r->destinationCountry]);
        $rates = $rates? json_decode($rates, true): [];
        
        $base = $this->pickByWeight($rates, $r->weightG) ?? 0.0;

        // НДС
        $vatIncluded = (bool) \Settings::get('shipping.zasilkovna.vat_included', false);
        $vatRate     = (float) \Settings::get('shipping.zasilkovna.vat_rate', 21.0);
        $vatMode     = (string) \Settings::get('shipping.zasilkovna.vat_mode', 'destination');
        // Здесь можно усложнить логику по $vatMode (domestic/destination/oss). Пока применим одну ставку как задано.
        [$net, $vat, $gross] = $this->applyVat($base, $vatRate, $vatIncluded);

        // COD (наложенный платеж)
        $codBreak = [];
        if ($r->codEnabled && (bool) \Settings::get('shipping.zasilkovna.cod.enabled', true)) {
            $allowedFor = (array) \Settings::get('shipping.zasilkovna.cod.allowed_for', ['pickup','home']);
            $typeAlias  = \str_ends_with($r->methodKey, '_warehouse') ? 'pickup' : 'home'; // z-box опущен

            if (\in_array($typeAlias, $allowedFor, true)) {
                $codFixed   = (float) \Settings::get('shipping.zasilkovna.cod.surcharge_fixed', 21);
                $codPercent = (float) \Settings::get('shipping.zasilkovna.cod.surcharge_percent', 0);
                $codMax     = (float) \Settings::get('shipping.zasilkovna.cod.max_amount', 20000);

                $codBase = $codFixed + ($codPercent > 0 ? ($r->codAmount * $codPercent / 100.0) : 0.0);
                $codBase = \min($codBase, $codMax);

                // Применим ту же НДС-логику к COD-доплате
                [$codNet, $codVat, $codGross] = $this->applyVat($codBase, $vatRate, $vatIncluded);

                $net  += $codNet;
                $vat  += $codVat;
                $gross += $codGross;

                $codBreak = [
                    'cod_base'   => $this->round2($codBase),
                    'cod_net'    => $this->round2($codNet),
                    'cod_vat'    => $this->round2($codVat),
                    'cod_gross'  => $this->round2($codGross),
                ];
            }
        }

        $breakdown = \array_filter([
            'base'      => $this->round2($base),
            'net'       => $this->round2($net),
            'vat'       => $this->round2($vat),
            'gross'     => $this->round2($gross),
            'vat_rate'  => $vatRate,
            'vat_mode'  => $vatMode,
        ]) + $codBreak;

        return new ShippingQuoteResult('packeta', $r->methodKey, $currency, $gross, $breakdown);
    }

    protected function pickByWeight(array $rows, int $weightG): ?float
    {
        // Ожидается каждая строка: ['max_weight_g' => int, 'price' => float]
        $candidate = null;
        foreach ($rows as $row) {
            $max = (int) ($row['max_weight_g'] ?? 0);
            $price = (float) ($row['price'] ?? 0.0);
            if ($weightG <= $max) {
                $candidate = $price;
                break;
            }
        }
        // Если ничего не подошло — можно взять последний тариф, либо null
        if ($candidate === null && !empty($rows)) {
            $last = \end($rows);
            $candidate = (float) ($last['price'] ?? 0.0);
        }
        return $candidate;
    }

    protected function applyVat(float $base, float $ratePerc, bool $included): array
    {
        if ($base <= 0.0 || $ratePerc <= 0.0) {
            return [$base, 0.0, $base];
        }
        if ($included) {
            $net = $base / (1.0 + $ratePerc/100.0);
            $vat = $base - $net;
            return [$this->round2($net), $this->round2($vat), $this->round2($base)];
        } else {
            $vat = $base * ($ratePerc/100.0);
            $gross = $base + $vat;
            return [$this->round2($base), $this->round2($vat), $this->round2($gross)];
        }
    }

    protected function round2(float $v): float
    {
        return \round($v, 2);
    }
}
