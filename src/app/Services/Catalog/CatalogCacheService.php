<?php

namespace Backpack\Store\app\Services\Catalog;

use Illuminate\Support\Facades\DB;
use Backpack\Store\app\Models\Catalog;

/**
 * Единый сервис пересборки кеша каталога:
 * - rebuildAll(?array $countryCodes = null, int $chunk = 1000)
 * - rebuildCountry(string $countryCode, int $chunk = 1000)
 * - syncProduct(int $productId, ?array $countryCodes = null)
 *
 * Единая точка правды для:
 *   • построения строки ak_catalog (buildCatalogRow)
 *   • пересборки ak_catalog_attr (insertDiscrete/insertNumber и точечные варианты)
 *   • расчёта sale через $product->getModificationSale()
 *
 * Важно:
 *   • disableOthers() теперь фильтруется по country_code (исправление потенциального бага).
 *   • Настройки класса Product берём с двух ключей (fallback): 
 *     'dress.product.model_admin' → 'backpack.store.product.class_admin'.
 */
class CatalogCacheService
{
    protected string $tblCatalog = 'ak_catalog';        // country_code, group_id, product_id, is_visible...
    protected string $tblAttr    = 'ak_catalog_attr';   // country_code, group_id, product_id, attribute_id, attribute_value_id, value
    protected string $tblAP      = 'ak_attribute_product'; // product_id, attribute_id, attribute_value_id?, value?

    /** @var class-string */
    protected string $productClass;

    /** Ключевые колонки для upsert, едины для rebuild/sync */
    protected array $upsertUnique = ['product_id', 'country_code'];

    protected array $upsertColumns = [
        'group_id', 'item_type', 'currency_code', 'is_available', 'in_stock',
        'price', 'old_price', 'sale', 'brand_id', 'category_ids',
        'short_name', 'name', 'excerpt', 'slug', 'images', 'code',
        'rating', 'reviews', 'ratings', 'content', 'merchant_content', 'seo', 'attrs'
    ];

    public function __construct()
    {
        // fallback: сначала берём то, что сейчас использует полный ребилдер, затем — то, что было в точечной версии
        $this->productClass = \Settings::get('dress.product.model') ?? 'Backpack\Store\app\Models\Product';
    }

    /* ===========================
     * ПУБЛИЧНЫЕ МЕТОДЫ
     * =========================== */

    /**
     * Полная пересборка по всем или заданным странам (батчами).
     */
    public function rebuildAll(?array $countryCodes = null, int $chunk = 1000): void
    {
        $countries = $countryCodes ?: $this->availableCountries();
        foreach ($this->extractCodes($countries) as $countryCode) {
            $this->rebuildCountry($countryCode, $chunk);
            $this->rebuildAttributesForCountry($countryCode);
        }
    }

    /**
     * Пересборка каталога по одной стране (батчами) с безопасным disableOthers по стране.
     */
    public function rebuildCountry(string $countryCode, int $chunk = 1000): void
    {
        $processed = []; // product_ids для данной страны
        $currency  = $this->targetCurrency($countryCode);

        \Store::withContext($countryCode, $currency, function () use ($countryCode, $chunk, &$processed) {
            $this->productClass::query()
                ->leafs()
                ->available()
                ->chunk($chunk, function ($products) use ($countryCode, &$processed) {
                    $rows = [];

                    foreach ($products as $p) {
                        // пропускаем без цены — как в исходном ребилдере
                        if ($p->price === null) {
                            continue;
                        }

                        $rows[]    = $this->buildCatalogRow($p, $countryCode);
                        $processed[] = (int) $p->id;
                    }

                    if (!empty($rows)) {
                        DB::table($this->tblCatalog)->upsert($rows, $this->upsertUnique, $this->upsertColumns);
                    }
                });
        });

        // Снимем is_available=0 для ТЕКУЩЕЙ страны, если товар не попал в обработку.
        $this->disableOthers($countryCode, $processed);
    }

    /**
     * Точечная пересборка одного товара по всем или заданным странам.
     */
    public function syncProduct(int $productId, ?array $countryCodes = null): void
    {
        $countries = $countryCodes ?: $this->availableCountries();
        foreach ($this->extractCodes($countries) as $countryCode) {
            $this->syncProductForCountry($productId, $countryCode);
        }
    }

    /**
     * Точечная пересборка нескольких товаров (например, $product->children).
     *
     * @param iterable<int,\Illuminate\Database\Eloquent\Model|array|int> $products  Коллекция моделей/массивов/ID
     * @param array<string,mixed>|array<string>|\null $countryCodes  Страны; если null — все из \Store::countries()
     */
    public function syncMany(iterable $products, ?array $countryCodes = null): void
    {
        // Соберём уникальные ID
        $ids = [];
        foreach ($products as $item) {
            if (is_object($item) && isset($item->id)) {
                $ids[] = (int) $item->id;
            } elseif (is_array($item) && isset($item['id'])) {
                $ids[] = (int) $item['id'];
            } else {
                $ids[] = (int) $item;
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));

        if (empty($ids)) {
            return;
        }

        // Пройдемся по ID и переиспользуем существующую логику syncProduct
        foreach ($ids as $id) {
            $this->syncProduct($id, $countryCodes);
        }
    }

    /**
     * Точечная пересборка одного товара по конкретной стране (ak_catalog + точечные attrs).
     */
    public function syncProductForCountry(int $productId, string $countryCode): void
    {
        $currency = $this->targetCurrency($countryCode);

        \Store::withContext($countryCode, $currency, function () use ($productId, $countryCode) {
            DB::transaction(function () use ($productId, $countryCode) {
                $product = $this->productClass::query()->whereKey($productId)->first();
                $indexAction = 'delete';

                if (!$product) {
                    $this->markUnavailableAndDropAttrs_NoTx($productId, $countryCode);
                } else {
                    $isLeafAndAvailable = $this->productClass::query()
                        ->whereKey($productId)
                        ->leafs()
                        ->available()
                        ->exists();

                    if (!$isLeafAndAvailable) {
                        $this->markUnavailableAndDropAttrs_NoTx($productId, $countryCode);
                    } else {
                        // upsert строки каталога
                        $row = $this->buildCatalogRow($product, $countryCode);

                        if(empty($row)) {
                            $this->markUnavailableAndDropAttrs_NoTx($productId, $countryCode);
                            return;
                        }

                        // dd($row, $product->id, $product->price, $countryCode);

                        DB::table($this->tblCatalog)->upsert(
                            [$row],
                            $this->upsertUnique,
                            $this->upsertColumns
                        );

                        // точечная пересборка только атрибутов варианта
                        $this->rebuildAttrsForProduct_NoTx($product, $countryCode);

                        // если это базовый (group) — пересобрать групповые атрибуты
                        if (empty($product->parent_id)) {
                            $this->rebuildGroupAttrsForGroup_NoTx((int) $product->id, $countryCode);
                        }

                        $indexAction = ((int)($row['is_available'] ?? 0) === 1) ? 'upsert' : 'delete';
                    }
                }

                // После коммита — управление индексом (Meilisearch/Scout)
                DB::afterCommit(function () use ($productId, $countryCode, $indexAction) {
                    $model = Catalog::query()
                        ->where('product_id', $productId)
                        ->where('country_code', $countryCode)
                        ->first();

                    if ($indexAction === 'upsert' && $model && (int)$model->is_available === 1) {
                        $model->searchable();
                    } elseif ($model) {
                        $model->unsearchable();
                    }
                });
            });
        });
    }

    /**
     * Полная пересборка атрибутов по стране (truncate-by-country + re-insert).
     */
    public function rebuildAttributesForCountry(string $country): void
    {
        DB::transaction(function () use ($country) {
            DB::table($this->tblAttr)->where('country_code', $country)->delete();
            $this->insertDiscrete($country);
            $this->insertNumber($country);
        });
    }

    /* ===========================
     * ВНУТРЕННИЕ ХЕЛПЕРЫ
     * =========================== */

    /** Единое построение строки ak_catalog. */
    protected function buildCatalogRow($p, string $countryCode): array
    {
        // категории
        $category_ids_array = $p->getAllCategoryIds($countryCode);
        $category_ids_json  = $category_ids_array ? json_encode($category_ids_array) : null;

        // картинки
        $images_array = $p->effective()->images;
        $images_json  = $images_array ? json_encode($images_array) : null;

        
        if($p->price === null) {
            return [];
        }

        // dd($p->base->rating, $p->base, $p);
        // цена есть → доступен
        return [
            'product_id'    => $p->id,
            'group_id'      => $p->parent_id ?: $p->id,
            'item_type'     => $p->parent_id ? 'm' : 's',
            'country_code'  => $countryCode,
            'currency_code' => \Store::context()->currency,
            'is_available'  => 1,
            'in_stock'      => (int) ($p->inStock ?? 0),
            'price'         => $p->price,
            'old_price'     => $p->oldPrice,
            'sale'          => $p->getModificationSale(), // <-- унификация: всегда есть в строке
            'brand_id'      => $p->brand_id ?? null,
            'category_ids'  => $category_ids_json,
            'short_name'    => $p->getRawOriginal('short_name'),

            
            'name'          => $p->inherited(true)->name,
            'excerpt'       => $p->effective(true)->excerpt,
            'slug'          => $p->slug,
            'images'        => $images_json,
            'code'          => $p->effective()->code,

            // Reviews
            'rating'        => $p->base->rating ?? 0,
            'reviews'       => $p->base->reviewsCount ?? 0,
            'ratings'       => $p->base->reviewsWithRatingCount ?? 0,

            // Additional
            'content'       => $p->effective(true)->content,
            'merchant_content' => $p->effective(true)->merchant_content,
            'seo'           => $p->effective(true)->seo,
            'attrs'         => $p->effective(true)->properties,
        ];
    }

    /** Снять is_available и удалить атрибуты конкретного товара по стране. */
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

    /** Отключить все неупомянутые product_id по указанной стране. */
    protected function disableOthers(string $countryCode, array $processedProductIds): void
    {
        DB::table($this->tblCatalog)
            ->where('country_code', $countryCode)
            ->when(!empty($processedProductIds), function ($q) use ($processedProductIds) {
                $q->whereNotIn('product_id', $processedProductIds);
            }, function ($q) {
                // если processed пуст — значит не было листов/цен; тогда снимем всё в стране
                // (альтернативно можно ничего не делать — решаем бизнес-логикой)
            })
            ->update(['is_available' => 0]);
    }

    /** Полная пересборка ДИСКРЕТНЫХ атрибутов по стране (групповые + вариативные). */
    protected function insertDiscrete(string $country): void
    {
        // ГРУППОВЫЕ (ap.product_id = c.group_id)
        $subGroup = DB::table($this->tblCatalog.' as c')
            ->join($this->tblAP.' as ap', 'ap.product_id', '=', 'c.group_id')
            ->where('c.country_code', $country)
            ->where('c.is_available', 1)
            ->whereNotNull('ap.attribute_value_id')
            ->distinct()
            ->selectRaw('?, c.group_id, NULL as product_id, ap.attribute_id, ap.attribute_value_id, NULL as value', [$country]);

        DB::table($this->tblAttr)->insertUsing(
            ['country_code','group_id','product_id','attribute_id','attribute_value_id','value'],
            $subGroup
        );

        // ВАРИАНТНЫЕ (ap.product_id = c.product_id)
        $subVariant = DB::table($this->tblCatalog.' as c')
            ->join($this->tblAP.' as ap', 'ap.product_id', '=', 'c.product_id')
            ->where('c.country_code', $country)
            ->where('c.is_available', 1)
            ->whereNotNull('ap.attribute_value_id')
            ->distinct()
            ->selectRaw('?, c.group_id, ap.product_id, ap.attribute_id, ap.attribute_value_id, NULL as value', [$country]);

        DB::table($this->tblAttr)->insertUsing(
            ['country_code','group_id','product_id','attribute_id','attribute_value_id','value'],
            $subVariant
        );
    }

    /** Полная пересборка ЧИСЛОВЫХ атрибутов по стране (групповые + вариативные). */
    protected function insertNumber(string $country): void
    {
        // ГРУППОВЫЕ
        $subGroup = DB::table($this->tblCatalog.' as c')
            ->join($this->tblAP.' as ap', 'ap.product_id', '=', 'c.group_id')
            ->where('c.country_code', $country)
            ->where('c.is_available', 1)
            ->whereNotNull('ap.value')
            ->distinct()
            ->selectRaw('?, c.group_id, NULL as product_id, ap.attribute_id, NULL as attribute_value_id, ap.value', [$country]);

        DB::table($this->tblAttr)->insertUsing(
            ['country_code','group_id','product_id','attribute_id','attribute_value_id','value'],
            $subGroup
        );

        // ВАРИАНТНЫЕ
        $subVariant = DB::table($this->tblCatalog.' as c')
            ->join($this->tblAP.' as ap', 'ap.product_id', '=', 'c.product_id')
            ->where('c.country_code', $country)
            ->where('c.is_available', 1)
            ->whereNotNull('ap.value')
            ->distinct()
            ->selectRaw('?, c.group_id, ap.product_id, ap.attribute_id, NULL as attribute_value_id, ap.value', [$country]);

        DB::table($this->tblAttr)->insertUsing(
            ['country_code','group_id','product_id','attribute_id','attribute_value_id','value'],
            $subVariant
        );
    }

    /** Точечная пересборка атрибутов КОНКРЕТНОГО товара по стране. */
    protected function rebuildAttrsForProduct_NoTx($product, string $country): void
    {
        $pid = (int) $product->id;

        DB::table($this->tblAttr)
            ->where('country_code', $country)
            ->where('product_id', $pid)
            ->delete();

        // дискретные для варианта
        $subVariantDiscrete = DB::table($this->tblCatalog.' as c')
            ->join($this->tblAP.' as ap', 'ap.product_id', '=', 'c.product_id')
            ->where('c.country_code', $country)
            ->where('c.is_available', 1)
            ->where('c.product_id', $pid)
            ->whereNotNull('ap.attribute_value_id')
            ->distinct()
            ->selectRaw('?, c.group_id, ap.product_id, ap.attribute_id, ap.attribute_value_id, NULL as value', [$country]);

        DB::table($this->tblAttr)->insertUsing(
            ['country_code','group_id','product_id','attribute_id','attribute_value_id','value'],
            $subVariantDiscrete
        );

        // числовые для варианта
        $subVariantNumber = DB::table($this->tblCatalog.' as c')
            ->join($this->tblAP.' as ap', 'ap.product_id', '=', 'c.product_id')
            ->where('c.country_code', $country)
            ->where('c.is_available', 1)
            ->where('c.product_id', $pid)
            ->whereNotNull('ap.value')
            ->distinct()
            ->selectRaw('?, c.group_id, ap.product_id, ap.attribute_id, NULL as attribute_value_id, ap.value', [$country]);

        DB::table($this->tblAttr)->insertUsing(
            ['country_code','group_id','product_id','attribute_id','attribute_value_id','value'],
            $subVariantNumber
        );
    }

    /** Точечная пересборка ГРУППОВЫХ атрибутов (product_id=NULL) для одного group_id. */
    protected function rebuildGroupAttrsForGroup_NoTx(int $groupId, string $country): void
    {
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

    /* ===========================
     * ВСПОМОГАТЕЛЬНЫЕ УТИЛИТЫ
     * =========================== */

    protected function availableCountries(): array
    {
        return (array) \Store::countries();
    }

    protected function extractCodes(array $countries): array
    {
        // поддержка как формата ['UA'=>['currency'=>'UAH'], ...], так и ['UA','CZ',...]
        $first = reset($countries);
        return is_array($first) ? array_keys($countries) : array_values($countries);
    }

    protected function targetCurrency(string $countryCode): string
    {
        $map = (array) $this->availableCountries();
        return $map[$countryCode]['currency'] ?? 'EUR';
    }
}
