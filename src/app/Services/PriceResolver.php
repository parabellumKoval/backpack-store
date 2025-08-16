<?php

namespace Backpack\Store\Services;

use Backpack\Store\app\Models\Product;
use Backpack\Store\Services\Currency\CurrencyConverter;

class PriceResolver
{
    public function resolve(Product $product, ?string $countryCode = null, ?string $targetCurrency = null): ?array
    {
        $countryCode = $countryCode ?? \Store::country();
        $targetCurrency = $targetCurrency ?? \Store::currency();

        // 1. Найти override для (product, country)
        $override = $product->countryOverrides()
            ->where('country_code', $countryCode)
            ->first();

        if ($override && $override->price_override !== null) {
            if ($override->currency_code === $targetCurrency) {
                return [
                    'price'      => $override->price_override,
                    'old_price'  => $override->old_price_override,
                    'currency'   => $override->currency_code,
                    'source'     => 'override'
                ];
            }
            // Конвертация override в валюту страны
            $converted_price = app(CurrencyConverter::class)
                ->convert($override->price_override, $override->currency_code, $targetCurrency);

            $converted_old_price = app(CurrencyConverter::class)
                ->convert($override->old_price_override, $override->currency_code, $targetCurrency);

            return [
                'price'      => $converted_price,
                'old_price'  => $converted_old_price,
                'currency'   => $targetCurrency,
                'source'     => 'override-converted'
            ];
        }

        // 2. Получить список supplier_id, обслуживающих страну
        $supplierIds = \DB::table('ak_supplier_country')
            ->where('country_code', $countryCode)
            ->pluck('supplier_id');

        // 3. Найти активный supplier_product для этого продукта
        $sp = $product->sp()
            ->whereIn('supplier_id', $supplierIds)
            ->where('is_active', true)
            ->where('in_stock', '>', 0)
            ->orderBy('price') // по минимальной цене
            ->first();

        if (!$sp) {
            return null; // Товар недоступен в регионе
        }

        $fromCurrency = $sp->supplier->currency_code;
        $price = $sp->price;

        if ($fromCurrency !== $targetCurrency) {
            $price = app(CurrencyConverter::class)->convert($price, $fromCurrency, $targetCurrency);
        }

        return [
            'price'      => $price,
            'currency'   => $targetCurrency,
            'discounted' => $sp->old_price > $sp->price,
            'supplier_id'=> $sp->supplier_id,
            'source'     => 'supplier'
        ];
    }
}
