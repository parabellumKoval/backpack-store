<?php

namespace Backpack\Store\app\Services\Catalog;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

// use Backpack\Store\app\Models\Product;

class CatalogCacheBuilder
{
    private $processed_product_ids = [];
    private $product_class = null;


    protected string $tblCatalog = 'ak_catalog';            // country_code, group_id, product_id, is_visible...
    protected string $tblAttr    = 'ak_catalog_attr';
    protected string $tblAP      = 'ak_attribute_product';  // product_id, attribute_id, attribute_value_id?, value?


    public function __construct() {
        $this->product_class = \Settings::get('dress.product.model_admin', 'Backpack\Store\app\Models\Product');
    }

    /**
     * Полная пересборка (батчами).
     * @param array|null $countryCodes если null — все доступные
     * @param int $chunk
     */
    public function rebuild(?array $countryCodes = null, int $chunk = 1000): void
    {
        $this->processed_product_ids = [];
        $countryCodes = $countryCodes ?: $this->availableCountries();

        // Чистить таблицу целиком не обязательно; можно upsert'ить.
        foreach ($countryCodes as $country_code => $country) {
            $this->rebuildCountry($country_code, $chunk);
            $this->rebuildAttributesForCountry($country_code);
        }
    }

    /**
     * Пересборка по стране.
     */
    public function rebuildCountry(string $countryCode, int $chunk = 1000): void
    {
        $currency = $this->targetCurrency($countryCode);

        \Store::withContext($countryCode, $currency, function () use ($countryCode, $chunk) {
            $this->product_class::query()
                ->leafs()
                ->available()
                ->chunk($chunk, function ($products) use ($countryCode) {
                    $rows = [];

                    foreach ($products as $p) { // $p — Eloquent-модель Product

                        $category_ids_array = $p->getAllCategoryIds();
                        $category_ids_json = $category_ids_array? json_encode($category_ids_array): null;

                        $images_array = $p->effective()->images;
                        $images_json = $images_array? json_encode($images_array): null;

                        //
                        if($p->price === null)
                            continue;

                        $rows[] = [
                            'product_id'    => $p->id,
                            'group_id'      => $p->parent_id ?: $p->id,
                            'item_type'     => $p->parent_id? 'm': 's', // simple or modification
                            'country_code'  => $countryCode,
                            'currency_code' => \Store::context()->currency,
                            'is_available'    => 1,
                            'in_stock'      => $p->inStock,
                            'price'         => $p->price,
                            'old_price'     => $p->oldPrice,
                            'sale'          => $p->getModificationSale(),
                            'brand_id'      => $p->brand_id,
                            'category_ids'  => $category_ids_json,
                            'short_name'    => $p->getRawOriginal('short_name'),
                            
                            // Effective
                            'name'          => $p->inherited(true)->name,
                            'excerpt'       => $p->effective(true)->excerpt,
                            'slug'          => $p->slug,
                            'images'        => $images_json,
                            'code'          => $p->effective()->code,

                            // Reviews
                            'rating'        => $p->base->rating ?? 0,
                            'reviews'       => $p->base->reviewsCount ?? 0,
                            'ratings'       => $p->base->reviewsWithRatingCount ?? 0,
                        ];

                        $this->processed_product_ids[] = $p->id;
                    }

                    if (!empty($rows)) {
                        DB::table('ak_catalog')->upsert(
                            $rows,
                            ['product_id', 'country_code'], // уникальный ключ
                            [
                                'group_id', 'item_type', 'currency_code','is_available','in_stock',
                                'price','old_price', 'sale', 'brand_id','category_ids',
                                'short_name','name','excerpt','slug','images','code','rating','reviews','ratings'
                            ]
                        );
                    }
                });
        });

        $this->disableOthers();
    }

    private function disableOthers() {
        DB::table('ak_catalog')->whereNotIn('product_id', $this->processed_product_ids)->update(['is_available' => 0]);
    }

    private function availableCountries(): array
    {
        // Из конфигурации Store: список стран, подключённых к витрине
        return (array) \Store::countries();
    }

    private function targetCurrency(string $countryCode): string
    {
        // Маппинг страна → валюта витрины
        $map = (array) $this->availableCountries();
        return $map[$countryCode]['currency'] ?? 'EUR';
    }


    // ATTRIBUTES
    public function rebuildAttributesForCountry(string $country): void
    {
        DB::transaction(function () use ($country) {
            // 0) очистка страны
            DB::table($this->tblAttr)->where('country_code', $country)->delete();

            // 1) дискретные (check/radio) — attribute_value_id IS NOT NULL
            $this->insertDiscrete($country);

            // 2) числовые (number) — value IS NOT NULL
            $this->insertNumber($country);

            // (строковые value_trans при желании можно тоже материализовать отдельно)
        });
    }

    protected function insertDiscrete(string $country): void
    {
        // 1) ГРУППОВЫЕ атрибуты (ap привязан к базовому продукту = c.group_id)
        $subGroup = DB::table($this->tblCatalog.' as c')
            ->join($this->tblAP.' as ap', 'ap.product_id', '=', 'c.group_id')
            ->where('c.country_code', $country)
            ->where('c.is_available', 1)
            ->whereNotNull('ap.attribute_value_id')
            ->distinct()
            ->selectRaw(
                '?, c.group_id, NULL as product_id, ap.attribute_id, ap.attribute_value_id, NULL as value',
                [$country]
            );

        DB::table($this->tblAttr)->insertUsing(
            ['country_code','group_id','product_id','attribute_id','attribute_value_id','value'],
            $subGroup
        );

        // 2) ВАРИАНТНЫЕ атрибуты (ap привязан к конкретной модификации = c.product_id)
        $subVariant = DB::table($this->tblCatalog.' as c')
            ->join($this->tblAP.' as ap', 'ap.product_id', '=', 'c.product_id')
            ->where('c.country_code', $country)
            ->where('c.is_available', 1)
            ->whereNotNull('ap.attribute_value_id')
            ->distinct()
            ->selectRaw(
                '?, c.group_id, ap.product_id, ap.attribute_id, ap.attribute_value_id, NULL as value',
                [$country]
            );

        DB::table($this->tblAttr)->insertUsing(
            ['country_code','group_id','product_id','attribute_id','attribute_value_id','value'],
            $subVariant
        );
    }


    protected function insertNumber(string $country): void
    {
        // 1) ГРУППОВЫЕ
        $subGroup = DB::table($this->tblCatalog.' as c')
            ->join($this->tblAP.' as ap', 'ap.product_id', '=', 'c.group_id')
            ->where('c.country_code', $country)
            ->where('c.is_available', 1)
            ->whereNotNull('ap.value')
            ->distinct()
            ->selectRaw(
                '?, c.group_id, NULL as product_id, ap.attribute_id, NULL as attribute_value_id, ap.value',
                [$country]
            );

        DB::table($this->tblAttr)->insertUsing(
            ['country_code','group_id','product_id','attribute_id','attribute_value_id','value'],
            $subGroup
        );

        // 2) ВАРИАНТНЫЕ
        $subVariant = DB::table($this->tblCatalog.' as c')
            ->join($this->tblAP.' as ap', 'ap.product_id', '=', 'c.product_id')
            ->where('c.country_code', $country)
            ->where('c.is_available', 1)
            ->whereNotNull('ap.value')
            ->distinct()
            ->selectRaw(
                '?, c.group_id, ap.product_id, ap.attribute_id, NULL as attribute_value_id, ap.value',
                [$country]
            );

        DB::table($this->tblAttr)->insertUsing(
            ['country_code','group_id','product_id','attribute_id','attribute_value_id','value'],
            $subVariant
        );
    }
}
