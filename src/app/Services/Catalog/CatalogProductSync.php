<?php

namespace Backpack\Store\app\Services\Catalog;

use Illuminate\Support\Facades\DB;
use Backpack\Store\app\Models\Catalog;

/**
 * Точечная пересборка кеша каталога для ОДНОГО товара:
 * - обновляет/создаёт строку в ak_catalog (по стране) только если товар — лист и доступен;
 * - помечает строку как is_available=0, если товар перестал быть leaf/available;
 * - пересобирает ТОЛЬКО атрибуты этого товара в ak_catalog_attr (дискретные и числовые);
 * - при апдейте базового (группового) товара — пересобирает ещё и ГРУППОВЫЕ атрибуты (product_id=NULL) для его group_id;
 *
 * Ничего из существующего CatalogCacheBuilder НЕ ломает.
 */
class CatalogProductSync
{
    protected string $tblCatalog = 'ak_catalog';
    protected string $tblAttr    = 'ak_catalog_attr';
    protected string $tblAP      = 'ak_attribute_product'; // product_id, attribute_id, attribute_value_id?, value?

    /** Класс модели товара берём так же, как в CatalogCacheBuilder */
    protected string $productClass;

    public function __construct()
    {
        // важно: оставляем тот же ключ, что и в твоём рабочем ребилдере
        $this->productClass = \Settings::get('backpack.store.product.class_admin', 'Backpack\Store\app\Models\Product');
    }

    /**
     * Полная точечная пересборка товара по всем странам витрины (или заданному списку).
     *
     * @param int         $productId
     * @param array|null  $countryCodes  Список кодов стран; если null — берём все из \Store::countries()
     */
    public function sync(int $productId, ?array $countryCodes = null): void
    {
        $countries = $countryCodes ?: $this->availableCountries(); // ['UA'=>['currency'=>'UAH'], ...] или просто список кодов
        $codes = array_keys(is_array(reset($countries)) ? $countries : array_flip($countries));
        
        foreach ($codes as $countryCode) {
            $this->syncForCountry($productId, $countryCode);
        }
    }

    /**
     * Пересборка для одной страны: ak_catalog + точечные ak_catalog_attr.
     */
    public function syncForCountry(int $productId, string $countryCode): void
    {
        $currency = $this->targetCurrency($countryCode);

        \Store::withContext($countryCode, $currency, function () use ($productId, $countryCode) {

            DB::transaction(function () use ($productId, $countryCode) {

                // 1) Перечитываем продукт (ТОЛЬКО тут используем Eloquent)
                $product = $this->productClass::query()->whereKey($productId)->first();

                // «какое действие» делать по индексу в конце: 'upsert' или 'delete'
                $indexAction = 'delete';
                $catalogId   = null;

                if (!$product) {
                    // товара нет → снимем его строки + атрибуты
                    $this->markUnavailableAndDropAttrs_NoTx($productId, $countryCode);
                    // indexAction остаётся 'delete'
                } else {
                    // лист и доступен в контексте страны?
                    $isLeafAndAvailable = $this->productClass::query()
                        ->whereKey($productId)
                        ->leafs()
                        ->available()
                        ->exists();

                    if (!$isLeafAndAvailable) {
                        $this->markUnavailableAndDropAttrs_NoTx($productId, $countryCode);
                        // indexAction = 'delete'
                    } else {
                        // 2) upsert строки каталога
                        $row = $this->buildCatalogRow($product, $countryCode);

                        DB::table($this->tblCatalog)->upsert(
                            [$row],
                            ['product_id','country_code'],
                            [
                                'group_id','item_type','currency_code','is_available','in_stock',
                                'price','old_price','brand_id','category_ids',
                                'short_name','name','excerpt','slug','images','code','rating','reviews','ratings'
                            ]
                        );

                        // 3) атрибуты *ТОЛЬКО* для этого товара
                        $this->rebuildAttrsForProduct_NoTx($product, $countryCode);

                        // 4) если базовый товар — пересоберём *групповые* атрибуты
                        if (empty($product->parent_id)) {
                            $this->rebuildGroupAttrsForGroup_NoTx((int)$product->id, $countryCode);
                        }

                        $indexAction = ((int)($row['is_available'] ?? 0) === 1) ? 'upsert' : 'delete';
                    }
                }

                // 5) ОДИН раз — после фиксации транзакции — запускаем индексацию
                DB::afterCommit(function () use ($product, $countryCode, $indexAction) {
                    $model = Catalog::query()
                        ->where('product_id', $product->id)
                        ->where('country_code', $countryCode)
                        ->first();

                        // dd($model, $indexAction === 'upsert' && $model && (int)$model->is_available === 1);
                    if ($indexAction === 'upsert' && $model && (int)$model->is_available === 1) {
                        $model->searchable();   // в индекс
                    } elseif ($model) {
                        $model->unsearchable(); // снять из индекса
                    }
                });

            }); // конец транзакции

        }); // конец withContext
    }


    /** Пометить строку каталога «товар недоступен» + убрать его атрибуты по стране. */
    protected function markUnavailableAndDropAttrs_NoTx(int $productId, string $country): void
    {
        DB::table($this->tblCatalog)
            ->where('country_code', $country)
            ->where('product_id', $productId)
            ->update(['is_available' => 0]);

        DB::table($this->tblAttr)
            ->where('country_code', $country)
            ->where('product_id', $productId)
            ->delete();
    }

    /** Построение строки ak_catalog — строго по логике твоего ребилдера. */
    protected function buildCatalogRow($p, string $countryCode): array
    {
        // категории
        $category_ids_array = $p->getAllCategoryIds();
        $category_ids_json  = $category_ids_array ? json_encode($category_ids_array) : null;

        // картинки
        $images_array = $p->effective()->images;
        $images_json  = $images_array ? json_encode($images_array) : null;

        // если нет цены — как и в ребилдере, просто ничего не индексируем (но сюда попадём только если leaf+available)
        if ($p->price === null) {
            // сохраним корректный upsert с is_available=0 на всякий случай
            return [
                'product_id'    => $p->id,
                'group_id'      => $p->parent_id ?: $p->id,
                'item_type'     => $p->parent_id ? 'm' : 's',
                'country_code'  => $countryCode,
                'currency_code' => \Store::context()->currency,
                'is_available'  => 0,
                'in_stock'      => (int) ($p->inStock ?? 0),
                'price'         => null,
                'old_price'     => null,
                'brand_id'      => $p->brand_id ?? null,
                'category_ids'  => $category_ids_json,
                'short_name'    => $p->getRawOriginal('short_name'),
                'name'          => $p->inherited(true)->name,
                'excerpt'       => $p->effective(true)->excerpt,
                'slug'          => $p->inherited()->slug,
                'images'        => $images_json,
                'code'          => $p->effective()->code,
                'rating'        => $p->base->rating ?? 0,
                'reviews'       => $p->base->reviewsCount ?? 0,
                'ratings'       => $p->base->reviewsWithRatingCount ?? 0,
            ];
        }

        return [
            'product_id'    => $p->id,
            'group_id'      => $p->parent_id ?: $p->id,
            'item_type'     => $p->parent_id ? 'm' : 's', // 'm' — modification, 's' — simple
            'country_code'  => $countryCode,
            'currency_code' => \Store::context()->currency,
            'is_available'  => 1,
            'in_stock'      => (int) ($p->inStock ?? 0),
            'price'         => $p->price,
            'old_price'     => $p->oldPrice,
            'brand_id'      => $p->brand_id ?? null,
            'category_ids'  => $category_ids_json,
            'short_name'    => $p->getRawOriginal('short_name'),

            // Effective / Inherited поля — как в твоём ребилдере
            'name'          => $p->inherited(true)->name,
            'excerpt'       => $p->effective(true)->excerpt,
            'slug'          => $p->inherited()->slug,
            'images'        => $images_json,
            'code'          => $p->effective()->code,

            // Reviews
            'rating'        => $p->base->rating ?? 0,
            'reviews'       => $p->base->reviewsCount ?? 0,
            'ratings'       => $p->base->reviewsWithRatingCount ?? 0,
        ];
    }

    /** Точечная пересборка атрибутов КОНКРЕТНОГО товара для заданной страны. */
    protected function rebuildAttrsForProduct_NoTx($product, string $country): void
    {
        $productId = (int) $product->id;

        // 1) чистим атрибуты ВАРИАНТА
        DB::table($this->tblAttr)
            ->where('country_code', $country)
            ->where('product_id', $productId)
            ->delete();

        // 2) дискретные
        $subVariantDiscrete = DB::table($this->tblCatalog.' as c')
            ->join($this->tblAP.' as ap', 'ap.product_id', '=', 'c.product_id')
            ->where('c.country_code', $country)
            ->where('c.is_available', 1)
            ->where('c.product_id', $productId)
            ->whereNotNull('ap.attribute_value_id')
            ->distinct()
            ->selectRaw('?, c.group_id, ap.product_id, ap.attribute_id, ap.attribute_value_id, NULL as value', [$country]);

        DB::table($this->tblAttr)->insertUsing(
            ['country_code','group_id','product_id','attribute_id','attribute_value_id','value'],
            $subVariantDiscrete
        );

        // 3) числовые
        $subVariantNumber = DB::table($this->tblCatalog.' as c')
            ->join($this->tblAP.' as ap', 'ap.product_id', '=', 'c.product_id')
            ->where('c.country_code', $country)
            ->where('c.is_available', 1)
            ->where('c.product_id', $productId)
            ->whereNotNull('ap.value')
            ->distinct()
            ->selectRaw('?, c.group_id, ap.product_id, ap.attribute_id, NULL as attribute_value_id, ap.value', [$country]);

        DB::table($this->tblAttr)->insertUsing(
            ['country_code','group_id','product_id','attribute_id','attribute_value_id','value'],
            $subVariantNumber
        );
    }

    /** Пересборка ГРУППОВЫХ атрибутов (product_id=NULL) для одного group_id и страны. */
    protected function rebuildGroupAttrsForGroup_NoTx(int $groupId, string $country): void
    {
        // чистим групповые
        DB::table($this->tblAttr)
            ->where('country_code', $country)
            ->where('group_id', $groupId)
            ->whereNull('product_id')
            ->delete();

        // дискретные групповые
        $subGroupDiscrete = DB::table($this->tblCatalog.' as c')
            ->join($this->tblAP.' as ap', 'ap.product_id', '=', 'c.group_id')
            ->where('c.country_code', $country)
            ->where('c.is_available', 1)
            ->where('c.group_id', $groupId)
            ->whereNotNull('ap.attribute_value_id')
            ->distinct()
            ->selectRaw('?, c.group_id, NULL as product_id, ap.attribute_id, ap.attribute_value_id, NULL as value', [$country]);

        DB::table($this->tblAttr)->insertUsing(
            ['country_code','group_id','product_id','attribute_id','attribute_value_id','value'],
            $subGroupDiscrete
        );

        // числовые групповые
        $subGroupNumber = DB::table($this->tblCatalog.' as c')
            ->join($this->tblAP.' as ap', 'ap.product_id', '=', 'c.group_id')
            ->where('c.country_code', $country)
            ->where('c.is_available', 1)
            ->where('c.group_id', $groupId)
            ->whereNotNull('ap.value')
            ->distinct()
            ->selectRaw('?, c.group_id, NULL as product_id, ap.attribute_id, NULL as attribute_value_id, ap.value', [$country]);

        DB::table($this->tblAttr)->insertUsing(
            ['country_code','group_id','product_id','attribute_id','attribute_value_id','value'],
            $subGroupNumber
        );
    }

    /** ====== те же util'ы, что и в твоём ребилдере (адаптированы 1-в-1) ====== */

    protected function availableCountries(): array
    {
        return (array) \Store::countries();
    }

    protected function targetCurrency(string $countryCode): string
    {
        $map = (array) $this->availableCountries();
        return $map[$countryCode]['currency'] ?? 'EUR';
    }
}
