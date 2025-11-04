<?php

namespace Backpack\Store\app\Models\Traits;

use Illuminate\Support\Collection;

trait HasModification
{
    private $digitsAfterPoint = 0;
    /**
     * Построить упрощённый вывод модификаций с полем `sale` (%),
     * рассчитанным относительно первой модификации (базы).
     *
     * @param \Illuminate\Support\Collection<int,\Backpack\Store\app\Models\Catalog> $mods
     * @return \Illuminate\Support\Collection<int,array>|null
     */
    protected function buildResourceModifications(Collection $mods): ?Collection
    {
        if ($mods->isEmpty()) {
            return null;
        }

        // оставим только доступные
        // $mods = $mods->filter(fn($m) => (int)$m->is_available === 1)->values();
        $mods = $mods->values();

        if ($mods->isEmpty()) {
            return null;
        }

        /** @var \Backpack\Store\app\Models\Catalog $base */
        $base = $mods->first();
        $basePrice      = (float) ($base->price ?? 0);
        $baseQuantity   = $this->extractQuantity($base->short_name);
        $baseUnitPrice  = $this->computeUnitPrice($basePrice, $baseQuantity);

        return $mods->map(function ($m) use ($baseUnitPrice) {
            $price    = (float) ($m->price ?? 0);
            $quantity = $this->extractQuantity($m->short_name);
            $sale     = $this->computeSalePercent($price, $quantity, $baseUnitPrice);

            return [
                'id'         => $m->product_id,
                'price'      => $price,
                'oldPrice'   => $m->old_price,
                'inStock'    => $m->in_stock,
                'slug'       => $m->slug,
                'name'       => $m->name,
                'short_name' => $m->short_name,
                'passed'     => $m->passedFilter,
                'sale'       => $sale,
                'currency'   => $m->currency_code,
            ];
        });
    }

    /**
     * % скидки для ТЕКУЩЕГО инстанса относительно базовой модификации
     * (база = модификация с МИНИМАЛЬНОЙ ценой среди inherited()->modifications).
     */
    public function getModificationSale(): ?float
    {
        // все модификации товара
        $mods = $this->inherited()->modifications;
        if (!$mods || $mods->isEmpty()) return null;

        // только доступные, отсортировать по цене по возрастанию
        $mods = $mods->sortBy('price')->values();

        $base = $mods->first();
        if (!$base) return null;

        // база: цена за единицу
        $basePrice = (float) ($base->price ?? 0.0);
        $baseQty   = self::extractQuantity($base->short_name ?? '');
        if ($basePrice <= 0.0 || $baseQty <= 0.0) return null;

        $baseUnit = $basePrice / $baseQty;

        // текущий инстанс
        $price = (float) ($this->price ?? 0.0);
        $qty   = self::extractQuantity($this->short_name ?? '');
        if ($price <= 0.0 || $qty <= 0.0) return null;

        $unit = $price / $qty;

        $sale = (1 - ($unit / $baseUnit)) * 100.0;
        if (!is_finite($sale)) return null;

        return round(max(0, $sale), $this->digitsAfterPoint);
    }


    /**
     * Посчитать цену за единицу (price / quantity), если возможно.
     */
    protected function computeUnitPrice(?float $price, ?float $quantity): ?float
    {
        $p = (float) ($price ?? 0);
        $q = (float) ($quantity ?? 0);
        if ($p > 0 && $q > 0) {
            return $p / $q;
        }
        return null;
    }

    /**
     * Посчитать скидку (%) относительно базовой цены за единицу.
     * sale% = max(0, (1 - unitPrice/baseUnitPrice) * 100)
     */
    protected function computeSalePercent(?float $price, ?float $quantity, ?float $baseUnitPrice): ?float
    {
        $unitPrice = $this->computeUnitPrice($price, $quantity);
        if (!$baseUnitPrice || $baseUnitPrice <= 0 || !$unitPrice || $unitPrice <= 0) {
            return null;
        }

        $sale = (1 - ($unitPrice / $baseUnitPrice)) * 100.0;
        if (!is_finite($sale)) {
            return null;
        }
        return round(max(0, $sale), $this->digitsAfterPoint);
    }

    /**
     * Извлечь количество из short_name и нормализовать:
     * - масса → граммы (g)
     * - объём → миллилитры (ml)
     * - без единицы → «штуки» (как есть)
     * Примеры: 25g, "50 g", "0.5 kg", "250ml", "1 l"
     */
    protected function extractNormalizedQuantity(?string $short): ?float
    {
        if (!is_string($short) || $short === '') return null;

        if (!preg_match('/(\d+(?:[.,]\d+)?)(?:\s*)?(kg|g|l|ml)?/iu', $short, $m)) {
            return null;
        }

        $num  = (float) str_replace(',', '.', $m[1]);
        $unit = isset($m[2]) ? mb_strtolower($m[2]) : null;

        return match ($unit) {
            'kg' => $num * 1000.0,   // кг → г
            'g'  => $num,            // г
            'l'  => $num * 1000.0,   // л → мл
            'ml' => $num,            // мл
            default => $num,         // нет единицы — считаем «штуки»
        };
    }


    protected function extractQuantity(?string $short): ?float
    {
        if (!is_string($short) || $short === '') return null;

        if (!preg_match('/(\d+(?:[.,]\d+)?)/u', $short, $m)) {
            return null;
        }
        return (float) str_replace(',', '.', $m[1]);
    }
}
