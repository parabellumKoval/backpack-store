<?php

namespace Backpack\Store\app\Services\Catalog;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Backpack\Store\app\Services\Currency\CurrencyConverter;
use Carbon\Carbon;

class CatalogCacheBuilder
{
    public function __construct(
        private CurrencyConverter $converter,
    ) {}

    /**
     * Полная пересборка (батчами).
     * @param array|null $countryCodes если null — все доступные
     * @param int $chunk
     */
    public function rebuild(?array $countryCodes = null, int $chunk = 1000): void
    {
        $countryCodes = $countryCodes ?: $this->availableCountries();

        // Чистить таблицу целиком не обязательно; можно upsert'ить.
        foreach ($countryCodes as $country) {
            $this->rebuildCountry($country, $chunk);
        }
    }

    /**
     * Пересборка по стране.
     */
    public function rebuildCountry(string $countryCode, int $chunk = 1000): void
    {
        // 1) Берем активные модификации (children). Родители абстрактны.
        DB::table('ak_products')
            ->where('is_active', 1)
            ->whereNotNull('parent_id') // только дети
            ->orderBy('id')
            ->chunk($chunk, function (Collection $products) use ($countryCode) {
                $rows = [];

                foreach ($products as $p) {
                    // 2) Определить обслуживающие склады для страны
                    $suppliers = $this->activeSuppliersFor($p->id, $countryCode);
                    if ($suppliers->isEmpty()) {
                        // Нет склада — товар недоступен
                        $rows[] = $this->rowUnavailable($p, $countryCode);
                        continue;
                    }

                    // 3) Базовая цена/валюта из склада (выбираем "лучший" склад; можно партиционировать по приоритету)
                    $best = $this->chooseBestSupplier($suppliers);

                    $baseCurrency = $best['currency_code'];
                    $basePrice    = $best['price'];         // цена поставщика для модификации
                    $inStock      = $best['in_stock_total']; // суммарный остаток по стране

                    // 4) Override по стране?
                    $override = $this->countryOverrideFor($p->id, $countryCode);
                    if ($override) {
                        $finalCurrency = $override['currency_code'] ?? $baseCurrency;
                        $finalPrice    = $override['price'];          // Жёсткая цена
                        $oldPrice      = $override['old_price'] ?? null;
                    } else {
                        // 5) Конвертация если нужно
                        $finalCurrency = $this->targetCurrency($countryCode); // из конфигурации
                        $rate = $this->converter->rate($baseCurrency, $finalCurrency);
                        $finalPrice = $this->converter->convert($basePrice, $baseCurrency, $finalCurrency, $rate);
                        $oldPrice   = null;
                    }

                    // 6) Эффективные витринные поля (наследование parent → child)
                    $eff = $this->effectivePresentation($p);

                    $rows[] = [
                        'product_id'    => $p->id,
                        'group_id'      => $p->parent_id ?: $p->id,
                        'country_code'  => $countryCode,
                        'currency_code' => $finalCurrency,
                        'is_visible'    => $inStock > 0 && $p->is_active == 1, // можно расширить политиками витрины
                        'in_stock'      => $inStock,
                        'price'         => $finalPrice,
                        'old_price'     => $oldPrice,
                        'brand_id'      => $eff['brand_id'],
                        'category_id'   => $eff['category_id'],
                        'name'          => $eff['name'],
                        'short_name'    => $eff['short_name'],
                        'slug'          => $eff['slug'],
                        'image'         => $eff['image'] ?? null,
                        'images'        => empty($eff['images']) ? null : json_encode($eff['images']),
                        'has_fast_delivery' => $best['has_fast_delivery'] ?? false,
                        'popularity'        => $eff['popularity'] ?? 0,
                    ];
                }

                // 7) Upsert батчем
                if (!empty($rows)) {
                    DB::table('ak_catalog')->upsert(
                        $rows,
                        ['product_id', 'country_code'], // уникальный ключ
                        [
                            'group_id','currency_code','is_visible','in_stock',
                            'price','old_price','brand_id','category_id',
                            'name','short_name','slug','image','images',
                            'has_fast_delivery','popularity'
                        ]
                    );
                }
            });
    }

    private function availableCountries(): array
    {
        // Из конфигурации Store: список стран, подключённых к витрине
        return (array) config('backpack-store.countries', ['UA','CZ','DE','ES']);
    }

    private function targetCurrency(string $countryCode): string
    {
        // Маппинг страна → валюта витрины
        $map = (array) config('backpack-store.country_currency', ['UA' => 'UAH','CZ' => 'CZK','DE' => 'EUR','ES' => 'EUR']);
        return $map[$countryCode] ?? 'EUR';
    }

    private function activeSuppliersFor(int $productId, string $countryCode): Collection
    {
        // Примерная агрегация: активные склады, обслуживающие страну
        return DB::table('ak_supplier_product as sp')
            ->join('ak_suppliers as s', 's.id', '=', 'sp.supplier_id')
            ->join('ak_supplier_countries as sc', 'sc.supplier_id', '=', 's.id')
            ->where('sp.product_id', $productId)
            ->where('s.is_active', 1)
            ->where('sc.country_code', $countryCode)
            ->selectRaw('
                sp.product_id,
                s.id as supplier_id,
                s.currency_code,
                COALESCE(sp.price, 0) as price,
                COALESCE(sp.in_stock, 0) as in_stock_total,
                s.has_fast_delivery
            ')
            ->get();
    }

    private function chooseBestSupplier(Collection $suppliers): array
    {
        // Пример: выбираем по наличию, затем минимальную цену
        return $suppliers
            ->sortBy([
                fn($x) => $x->in_stock_total > 0 ? 0 : 1,
                fn($x) => $x->price,
            ])
            ->first()
            ?->toArray() ?? [];
    }

    private function countryOverrideFor(int $productId, string $countryCode): ?array
    {
        $ov = DB::table('ak_product_country_overrides')
            ->where('product_id', $productId)
            ->where('country_code', $countryCode)
            ->first();

        if (!$ov) return null;

        return [
            'currency_code' => $ov->currency_code ?? null,
            'price'         => $ov->price,       // фиксированная цена
            'old_price'     => $ov->old_price,   // при необходимости
        ];
    }

    private function effectivePresentation(object $p): array
    {
        // Здесь можно подтянуть parent и применить стратегию наследования
        $parent = null;
        if ($p->parent_id) {
            $parent = DB::table('ak_products')->where('id', $p->parent_id)->first();
        }

        $coalesce = fn($child, $field) => $child->{$field} ?? ($parent->{$field} ?? null);

        return [
            'brand_id'    => $coalesce($p, 'brand_id'),
            'category_id' => $coalesce($p, 'category_id'),
            'name'        => $coalesce($p, 'name'),
            'short_name'  => $p->short_name ?? null,       // модификация может задавать сама
            'slug'        => $coalesce($p, 'slug'),
            'image'       => $coalesce($p, 'image'),
            'images'      => $this->decodeJson($coalesce($p, 'images')),
            'popularity'  => (int) ($p->popularity ?? 0),
        ];
    }

    private function decodeJson($val): ?array
    {
        if (!$val) return null;
        if (is_array($val)) return $val;
        try { return json_decode($val, true, 512, JSON_THROW_ON_ERROR); }
        catch (\Throwable $e) { return null; }
    }

    private function rowUnavailable(object $p, string $country): array
    {
        $eff = $this->effectivePresentation($p);
        return [
            'product_id'    => $p->id,
            'group_id'      => $p->parent_id ?: $p->id,
            'country_code'  => $country,
            'currency_code' => $this->targetCurrency($country),
            'is_visible'    => false,
            'in_stock'      => 0,
            'price'         => 0,
            'old_price'     => null,
            'brand_id'      => $eff['brand_id'],
            'category_id'   => $eff['category_id'],
            'name'          => $eff['name'],
            'short_name'    => $eff['short_name'],
            'slug'          => $eff['slug'],
            'image'         => $eff['image'] ?? null,
            'images'        => empty($eff['images']) ? null : json_encode($eff['images']),
            'has_fast_delivery' => false,
            'popularity'        => (int) ($eff['popularity'] ?? 0),
        ];
    }
}
