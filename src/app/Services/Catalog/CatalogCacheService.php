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
        'short_name', 'name', 'excerpt', 'slug', 'images', 'code', 'extras',
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
    public function rebuildAll(?array $countryCodes = null, int $chunk = 1000, ?callable $heartbeat = null): void
    {
        $countries = $countryCodes ?: $this->availableCountries();
        foreach ($this->extractCodes($countries) as $countryCode) {
            if ($heartbeat) {
                $heartbeat();
            }

            $this->rebuildCountry($countryCode, $chunk, $heartbeat);

            if ($heartbeat) {
                $heartbeat();
            }

            $this->rebuildAttributesForCountry($countryCode, $heartbeat);
        }
    }

    /**
     * Пересборка каталога по одной стране (батчами) с безопасным disableOthers по стране.
     */
    public function rebuildCountry(string $countryCode, int $chunk = 1000, ?callable $heartbeat = null): void
    {
        $processed = []; // product_ids для данной страны
        $currency  = $this->targetCurrency($countryCode);
        $iteration = 0;

        \Store::withContext($countryCode, $currency, function () use ($countryCode, $chunk, &$processed, $heartbeat, &$iteration) {
            $this->productClass::query()
                ->leafs()
                ->available($countryCode)
                ->with(['regionalContents', 'parent.regionalContents'])
                ->chunk($chunk, function ($products) use ($countryCode, &$processed, $heartbeat, &$iteration) {
                    $rows = [];

                    foreach ($products as $p) {
                        $iteration++;

                        try {
                            $row = $this->buildCatalogRow($p, $countryCode);
                        } catch (\Throwable $e) {
                            \Log::error('Catalog rebuild failed for product', [
                                'product_id' => $p->id ?? null,
                                'country' => $countryCode,
                                'error' => $e->getMessage(),
                            ]);
                            continue;
                        }

                        if (empty($row)) {
                            continue;
                        }

                        $rows[] = $row;
                        $processed[] = (int) $p->id;

                        if ($heartbeat && ($iteration % 25) === 0) {
                            $heartbeat();
                        }
                    }

                    if (!empty($rows)) {
                        DB::table($this->tblCatalog)->upsert($rows, $this->upsertUnique, $this->upsertColumns);
                    }

                    if ($heartbeat) {
                        $heartbeat();
                    }
                });
        });

        // Снимем is_available=0 для ТЕКУЩЕЙ страны, если товар не попал в обработку.
        $this->disableOthers($countryCode, $processed);

        if ($heartbeat) {
            $heartbeat();
        }
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
                $product = $this->productClass::query()
                    ->with(['regionalContents', 'parent.regionalContents'])
                    ->whereKey($productId)
                    ->first();
                $indexAction = 'delete';

                if (!$product) {
                    $this->markUnavailableAndDropAttrs_NoTx($productId, $countryCode);
                } else {
                    $isLeafAndAvailable = $this->productClass::query()
                        ->whereKey($productId)
                        ->leafs()
                        ->available($countryCode)
                        ->exists();
                    $isBaseProduct = empty($product->parent_id);
                    $hasChildren = $isBaseProduct
                        ? $this->productClass::query()->where('parent_id', $product->id)->exists()
                        : false;

                    if (!$isLeafAndAvailable) {
                        $this->markUnavailableAndDropAttrs_NoTx($productId, $countryCode);

                        if ($isBaseProduct) {
                            $this->rebuildGroupAttrsForGroup_NoTx((int) $product->id, $countryCode);

                            if ($hasChildren) {
                                $this->rebuildVariantAttrsForGroup_NoTx((int) $product->id, $countryCode);
                            }
                        }
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
                            $this->rebuildAttrsForProduct_NoTx((int) $product->id, $countryCode);

                            // если это базовый (group) — пересобрать групповые атрибуты
                            if ($isBaseProduct) {
                                $this->rebuildGroupAttrsForGroup_NoTx((int) $product->id, $countryCode);

                                if ($hasChildren) {
                                    $this->rebuildVariantAttrsForGroup_NoTx((int) $product->id, $countryCode);
                                }
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
    public function rebuildAttributesForCountry(string $country, ?callable $heartbeat = null): void
    {
        DB::transaction(function () use ($country) {
            DB::table($this->tblAttr)->where('country_code', $country)->delete();
            $this->insertDiscrete($country);
            $this->insertNumber($country);
        });

        if ($heartbeat) {
            $heartbeat();
        }
    }

    /* ===========================
     * ВНУТРЕННИЕ ХЕЛПЕРЫ
     * =========================== */

    /** Единое построение строки ak_catalog. */
    protected function buildCatalogRow($p, string $countryCode): array
    {
        \Log::info('p - ' . $p->id);

        // категории
        $category_ids_array = $p->getAllCategoryIds($countryCode);
        $category_ids_json  = $category_ids_array ? json_encode($category_ids_array) : null;

        // картинки
        $images_array = $p->effective()->images;
        $images_json  = $images_array ? json_encode($images_array) : null;

        if($p->price === null) {
            return [];
        }
        $extrasJson = $p->effective(true)->extras;

        \Log::info('p - ' . $p->id);

        $contentTranslations = $this->encodeTranslations($p->getEffectiveRegionalizedTranslations('content', $countryCode));
        $excerptTranslations = $this->encodeTranslations($p->getEffectiveRegionalizedTranslations('excerpt', $countryCode));
        $merchantContentTranslations = $this->encodeTranslations($p->getEffectiveRegionalizedTranslations('merchant_content', $countryCode));


        // \Log::info('contentTranslations - ' . $contentTranslations);
        // \Log::info('excerptTranslations - ' . $excerptTranslations);
        // \Log::info('merchantContentTranslations - ' . $merchantContentTranslations);
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
            'excerpt'       => $excerptTranslations,
            'slug'          => $p->slug,
            'images'        => $images_json,
            'code'          => $p->effective()->code,
            'extras'        => $extrasJson,

            // Reviews
            'rating'        => $p->base->rating ?? 0,
            'reviews'       => $p->base->reviewsCount ?? 0,
            'ratings'       => $p->base->reviewsWithRatingCount ?? 0,

            // Additional
            'content'       => $contentTranslations,
            'merchant_content' => $merchantContentTranslations,
            'seo'           => $p->effective(true)->seo,
            'attrs'         => $p->effective(true)->properties,
        ];
    }

    protected function encodeTranslations(?array $translations): ?string
    {
        if (empty($translations)) {
            return null;
        }

        return json_encode($translations, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

        // Наследование групповых значений для модификаций без собственного значения
        $subVariantInherited = DB::table($this->tblCatalog.' as c')
            ->join($this->tblAP.' as ap_group', 'ap_group.product_id', '=', 'c.group_id')
            ->leftJoin($this->tblAP.' as ap_variant', function ($join) {
                $join->on('ap_variant.product_id', '=', 'c.product_id')
                    ->on('ap_variant.attribute_id', '=', 'ap_group.attribute_id');
            })
            ->where('c.country_code', $country)
            ->where('c.is_available', 1)
            ->whereNotNull('ap_group.attribute_value_id')
            ->whereNull('ap_variant.id')
            ->distinct()
            ->selectRaw('?, c.group_id, c.product_id, ap_group.attribute_id, ap_group.attribute_value_id, NULL as value', [$country]);

        DB::table($this->tblAttr)->insertUsing(
            ['country_code','group_id','product_id','attribute_id','attribute_value_id','value'],
            $subVariantInherited
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

        // Наследование числовых значений базового товара для модификаций
        $subVariantInherited = DB::table($this->tblCatalog.' as c')
            ->join($this->tblAP.' as ap_group', 'ap_group.product_id', '=', 'c.group_id')
            ->leftJoin($this->tblAP.' as ap_variant', function ($join) {
                $join->on('ap_variant.product_id', '=', 'c.product_id')
                    ->on('ap_variant.attribute_id', '=', 'ap_group.attribute_id');
            })
            ->where('c.country_code', $country)
            ->where('c.is_available', 1)
            ->whereNotNull('ap_group.value')
            ->whereNull('ap_variant.id')
            ->distinct()
            ->selectRaw('?, c.group_id, c.product_id, ap_group.attribute_id, NULL as attribute_value_id, ap_group.value', [$country]);

        DB::table($this->tblAttr)->insertUsing(
            ['country_code','group_id','product_id','attribute_id','attribute_value_id','value'],
            $subVariantInherited
        );
    }

    /** Точечная пересборка атрибутов КОНКРЕТНОГО товара по стране. */
    protected function rebuildAttrsForProduct_NoTx(int $productId, string $country): void
    {
        $pid = $productId;

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

        // наследованные дискретные значения от базового товара
        $subVariantInheritedDiscrete = DB::table($this->tblCatalog.' as c')
            ->join($this->tblAP.' as ap_group', 'ap_group.product_id', '=', 'c.group_id')
            ->leftJoin($this->tblAP.' as ap_variant', function ($join) {
                $join->on('ap_variant.product_id', '=', 'c.product_id')
                    ->on('ap_variant.attribute_id', '=', 'ap_group.attribute_id');
            })
            ->where('c.country_code', $country)
            ->where('c.is_available', 1)
            ->where('c.product_id', $pid)
            ->whereNotNull('ap_group.attribute_value_id')
            ->whereNull('ap_variant.id')
            ->distinct()
            ->selectRaw('?, c.group_id, c.product_id, ap_group.attribute_id, ap_group.attribute_value_id, NULL as value', [$country]);

        DB::table($this->tblAttr)->insertUsing(
            ['country_code','group_id','product_id','attribute_id','attribute_value_id','value'],
            $subVariantInheritedDiscrete
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

        // числовые наследованные значения
        $subVariantInheritedNumber = DB::table($this->tblCatalog.' as c')
            ->join($this->tblAP.' as ap_group', 'ap_group.product_id', '=', 'c.group_id')
            ->leftJoin($this->tblAP.' as ap_variant', function ($join) {
                $join->on('ap_variant.product_id', '=', 'c.product_id')
                    ->on('ap_variant.attribute_id', '=', 'ap_group.attribute_id');
            })
            ->where('c.country_code', $country)
            ->where('c.is_available', 1)
            ->where('c.product_id', $pid)
            ->whereNotNull('ap_group.value')
            ->whereNull('ap_variant.id')
            ->distinct()
            ->selectRaw('?, c.group_id, c.product_id, ap_group.attribute_id, NULL as attribute_value_id, ap_group.value', [$country]);

        DB::table($this->tblAttr)->insertUsing(
            ['country_code','group_id','product_id','attribute_id','attribute_value_id','value'],
            $subVariantInheritedNumber
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

    /**
     * Пересобрать атрибуты для всех доступных модификаций указанной группы с учётом наследования.
     */
    protected function rebuildVariantAttrsForGroup_NoTx(int $groupId, string $country): void
    {
        $productIds = DB::table($this->tblCatalog)
            ->where('country_code', $country)
            ->where('group_id', $groupId)
            ->whereNotNull('product_id')
            ->pluck('product_id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->unique()
            ->filter()
            ->values()
            ->all();

        foreach ($productIds as $pid) {
            $this->rebuildAttrsForProduct_NoTx($pid, $country);
        }
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
