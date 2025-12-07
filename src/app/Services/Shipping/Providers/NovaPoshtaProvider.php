<?php

namespace Backpack\Store\app\Services\Shipping\Providers;

use Backpack\Store\app\Contracts\ShippingProviderInterface;
use Backpack\Store\app\DTO\ShippingQuoteRequest;
use Backpack\Store\app\DTO\ShippingQuoteResult;

class NovaPoshtaProvider implements ShippingProviderInterface
{
    public function supports(string $methodKey): bool
    {
        return \str_starts_with($methodKey, 'novaposhta_');
    }

    public function quote(ShippingQuoteRequest $r): ShippingQuoteResult
    {
        // НП работает только по Украине (domestic)
        if ($r->destinationCountry !== 'UA') {
            return new ShippingQuoteResult('novaposhta', $r->methodKey, 'UAH', 0.0, [
                'note' => 'Nova Poshta supports UA domestic only',
            ]);
        }

        $currency   = (string) \Settings::get('shipping.novaposhta.currency', 'UAH');
        $vatRate    = (float) \Settings::get('shipping.novaposhta.vat_rate', 20.0);
        $vatIncl    = (bool) \Settings::get('shipping.novaposhta.vat_included', true);

        // Вычислим billable weight (учёт volumetric)
        $weightG = $this->billableWeightG($r);

        // Выбор тарифной таблицы
        $base = 0.0;
        $break = [];


        if (\str_ends_with($r->methodKey, '_warehouse')) {
            // Если передан lockerSize → берём тарифы почтомата; иначе — отделение
            if ($r->lockerSize) {
                $rows = \Settings::get('shipping.novaposhta.locker_rates', []);
                $rows = $rows? json_decode($rows, true): [];

                $base = $this->pickLockerRate($rows, $r->lockerSize, $weightG) ?? 0.0;
                $break['mode'] = 'locker';
                $break['locker_size'] = $r->lockerSize;
            } else {
                $rows =  \Settings::get('shipping.novaposhta.branch_rates', []);
                $rows = $rows? json_decode($rows, true): [];

                $base = $this->pickByWeight($rows, $weightG) ?? 0.0;
                $break['mode'] = 'branch';
            }
        } else { // _address
            $rows =  \Settings::get('shipping.novaposhta.courier_rates', []);
            $rows = $rows? json_decode($rows, true): [];
            
            $base = $this->pickByWeight($rows, $weightG) ?? 0.0;
            $break['mode'] = 'courier';
        }

        // НДС
        [$net, $vat, $gross] = $this->applyVat($base, $vatRate, $vatIncl);

        // Страховка (оценочная)
        if ((bool) \Settings::get('shipping.novaposhta.insurance.enabled', true) && $r->declaredValue) {
            $insPercent = (float) \Settings::get('shipping.novaposhta.insurance.percent', 0.5);
            $insMin     = (float) \Settings::get('shipping.novaposhta.insurance.min_amount', 5.0);

            $insBase = \max($insMin, $r->declaredValue * $insPercent / 100.0);
            [$insNet, $insVat, $insGross] = $this->applyVat($insBase, $vatRate, $vatIncl);

            $net   += $insNet;
            $vat   += $insVat;
            $gross += $insGross;

            $break += [
                'insurance_base'  => $this->round2($insBase),
                'insurance_net'   => $this->round2($insNet),
                'insurance_vat'   => $this->round2($insVat),
                'insurance_gross' => $this->round2($insGross),
            ];
        }

        // COD
        if ($r->codEnabled && (bool) \Settings::get('shipping.novaposhta.cod.enabled', true)) {
            $allowedFor = (array) \Settings::get('shipping.novaposhta.cod.allowed_for', ['branch','courier','locker']);

            // Сопоставление режимов
            $typeAlias = match ($break['mode'] ?? '') {
                'branch'  => 'branch',
                'locker'  => 'locker',
                'courier' => 'courier',
                default   => 'branch',
            };


            if (\in_array($typeAlias, $allowedFor, true)) {
                $codFixed   = (float) \Settings::get('shipping.novaposhta.cod.surcharge_fixed', 20);
                $codPercent = (float) \Settings::get('shipping.novaposhta.cod.surcharge_percent', 0);
                $codMax     = (float) \Settings::get('shipping.novaposhta.cod.max_amount', 30000);

                $codBase = $codFixed + ($codPercent > 0 ? ($r->codAmount * $codPercent / 100.0) : 0.0);
                $codBase = \min($codBase, $codMax);

                [$codNet, $codVat, $codGross] = $this->applyVat($codBase, $vatRate, $vatIncl);

                $net   += $codNet;
                $vat   += $codVat;
                $gross += $codGross;

                $break += [
                    'cod_base'   => $this->round2($codBase),
                    'cod_net'    => $this->round2($codNet),
                    'cod_vat'    => $this->round2($codVat),
                    'cod_gross'  => $this->round2($codGross),
                ];
            }
        }

        $break += [
            'weight_g'  => $weightG,
            'base'      => $this->round2($base),
            'vat_rate'  => $vatRate,
            'vat_incl'  => $vatIncl,
            'net'       => $this->round2($net),
            'vat'       => $this->round2($vat),
            'gross'     => $this->round2($gross),
        ];

        return new ShippingQuoteResult('novaposhta', $r->methodKey, $currency, $gross, $break);
    }

    protected function billableWeightG(ShippingQuoteRequest $r): int
    {
        $useVol = (bool) \Settings::get('shipping.novaposhta.dimensions.use_volumetric', true);
        $minG   = (int) \Settings::get('shipping.novaposhta.dimensions.min_billable_weight_g', 1000);

        $actual = \max($r->weightG, $minG);

        if (!$useVol || !$r->lengthCm || !$r->widthCm || !$r->heightCm) {
            return $actual;
        }

        $divisor = (float) \Settings::get('shipping.novaposhta.dimensions.volumetric_divisor', 4000.0);
        // объёмный вес (кг) = (L*W*H)/divisor; переведём в граммы:
        $volumetricG = (int) \ceil((($r->lengthCm * $r->widthCm * $r->heightCm) / $divisor) * 1000);

        return \max($actual, $volumetricG);
    }

    protected function pickByWeight(array $rows, int $weightG): ?float
    {
        // ['max_weight_g' => int, 'price' => float]
        $candidate = null;
        foreach ($rows as $row) {
            $max = (int) ($row['max_weight_g'] ?? 0);
            $price = (float) ($row['price'] ?? 0.0);
            if ($weightG <= $max) {
                $candidate = $price;
                break;
            }
        }
        if ($candidate === null && !empty($rows)) {
            $last = \end($rows);
            $candidate = (float) ($last['price'] ?? 0.0);
        }
        return $candidate;
    }

    protected function pickLockerRate(array $rows, string $size, int $weightG): ?float
    {
        $filtered = \array_values(\array_filter($rows, fn($r) => ($r['size'] ?? null) === $size));
        return $this->pickByWeight($filtered, $weightG);
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
