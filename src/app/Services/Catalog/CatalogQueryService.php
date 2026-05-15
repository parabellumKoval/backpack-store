<?php
namespace Backpack\Store\app\Services\Catalog;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\Catalog;
use Backpack\Store\app\Models\Campaign;
use Backpack\Store\app\Services\Campaign\CampaignResolverService;
use Backpack\Store\app\Services\Cache\SlugMapCache;

use Backpack\Store\app\Services\Catalog\AbstractQueryService;

use Backpack\Store\app\Http\Resources\ProductCollection;

class CatalogQueryService extends AbstractQueryService
{
    protected Request $request;
    protected string $country;
    protected string $storefront;
    protected $query;
    protected array $visibleCategoryIds = [];

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->country = \Store::context()->country;
        $this->storefront = \Store::storefront();
    }

    public function setRequest(Request $request): static {
        $this->request = $request;
        $this->country = \Store::context()->country;
        $this->storefront = \Store::storefront();
        return $this;
    }

    /** Базовый запрос к ak_catalog */
    public function startQuery(): self
    {
        $this->visibleCategoryIds = Category::visibleIdsForContext($this->country, null, true);

        $this->query = DB::table('ak_catalog as c')
            ->where('c.country_code', $this->country)
            ->where('c.storefront_code', $this->storefront)
            ->where('c.is_available', 1);

        if (empty($this->visibleCategoryIds)) {
            $this->query->whereRaw('1=0');
            return $this;
        }

        $this->query->whereExists(function ($sub) {
            $sub->selectRaw('1')
                ->from('ak_category_product as cp')
                ->whereIn('cp.category_id', $this->visibleCategoryIds)
                ->where(function ($where) {
                    $where->whereColumn('cp.product_id', 'c.product_id')
                        ->orWhereColumn('cp.product_id', 'c.group_id');
                });
        });

        return $this;
    }

    /** Категория: в c.category_ids уже лежат ВСЕ категории; ноды не нужны */
    public function filterByCategories(): self
    {
        /** @var SlugMapCache $map */
        $map = app(SlugMapCache::class);

        // либо category_id, либо category_slug
        $categoryId = $this->request->input('category_id');
        $categorySlug = $this->request->input('category_slug');
        $hasCategoryFilter = filled($categoryId) || filled($categorySlug);

        if (!$categoryId && $categorySlug) {
            $categoryId = $map->categoryIdBySlug($categorySlug);
        }

        if ($categoryId) {
            // category_ids — JSON-массив всех категорий товара (вкл. родительские/дочерние уже записаны при кэше)
            $this->query->whereJsonContains('c.category_ids', (int) $categoryId);
        } elseif ($hasCategoryFilter) {
            // Если фильтр был явно запрошен, но категория не разрешилась,
            // это должен быть пустой результат, а не весь каталог.
            $this->query->whereRaw('1=0');
        }

        return $this;
    }

    public function filterByBrandSlug(): self
    {
        if ($slug = $this->request->input('brand_slug')) {
            /** @var SlugMapCache $map */
            $map = app(SlugMapCache::class);
            if ($id = $map->brandIdBySlug($slug)) {
                $this->query->where('c.brand_id', $id);
            } else {
                // не найден — гарантированно пустой результат
                $this->query->whereRaw('1=0');
            }
        }

        return $this;
    }

    public function filterByBrands(): self
    {
        // бренды списком id — как есть
        if ($ids = $this->request->input('brands')) {
            $ids = array_filter(array_map('intval', (array)$ids));
            if (!empty($ids)) {
                $this->query->whereIn('c.brand_id', $ids);
            }
        }

        return $this;
    }

    

    /** Поиск: по name/short_name (локаль) и code */
    public function filterBySearch(): self
    {
        if (!$term = $this->request->input('q')) return $this;
        $term = mb_strtolower(trim($term));
        $loc  = app()->getLocale();
        $fb   = config('app.fallback_locale');

        $this->query->where(function($w) use ($term, $loc, $fb) {
            $w->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(c.name,       '$.\"{$loc}\"'))) LIKE ?", ["%{$term}%"])
              ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(c.short_name, '$.\"{$loc}\"'))) LIKE ?", ["%{$term}%"])
              ->orWhereRaw("LOWER(c.code) LIKE ?", ["%{$term}%"])
              ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(c.name,       '$.\"{$fb}\"'))) LIKE ?", ["%{$term}%"])
              ->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(c.short_name, '$.\"{$fb}\"'))) LIKE ?", ["%{$term}%"]);
        });

        return $this;
    }

    /** Цена */
    public function filterByPrice(): self
    {
        if (!$this->request->has('price')) return $this;
        $min = $this->request->input('price.min', null);
        $max = $this->request->input('price.max', null);

        if ($min !== null) $this->query->where('c.price', '>=', (float)$min);
        if ($max !== null) $this->query->where('c.price', '<=', (float)$max);

        return $this;
    }

    /** Подборки (with_sales, top_price, top_sales, with_rating, in_stock) */
    public function filterBySelections(): self
    {
        $sel = (array) $this->request->input('selections', []);
        if (empty($sel)) return $this;

        $sel = array_values(array_unique($sel));
        $percent = 10;
        $salesMin = 3;

        if (in_array('with_sales', $sel, true)) {
            $this->query->whereNotNull('c.old_price')->where('c.old_price', '>', 0);
        }
        if (in_array('top_price', $sel, true)) {
            $this->query->whereRaw('(c.old_price - c.price) > c.price / ?', [$percent]);
        }
        if (in_array('with_rating', $sel, true)) {
            $this->query->where('c.reviews', '>', 0);
        }
        if (in_array('in_stock', $sel, true)) {
            $this->query->where('c.in_stock', '>', 0);
        }
        if (in_array('top_sales', $sel, true)) {
            $country = $this->country;
            $storefront = $this->storefront;
            $this->query->whereIn('c.group_id', function ($sub) use ($salesMin, $country, $storefront) {
                $sub->select('cat.group_id')
                    ->from('ak_catalog as cat')
                    ->join('ak_order_product as op', 'op.product_id', '=', 'cat.product_id')
                    ->where('cat.country_code', $country)
                    ->where('cat.storefront_code', $storefront)
                    ->groupBy('cat.group_id')
                    ->havingRaw('SUM(op.amount) >= ?', [$salesMin]);
            });
        }

        return $this;
    }

    public function filterByCampaign(): self
    {
        $campaignSlug = $this->request->input('campaign');
        if (!$campaignSlug || !is_string($campaignSlug)) {
            return $this;
        }

        $campaign = Campaign::query()
            ->activeAt()
            ->activeForCountry($this->country)
            ->where('slug', $campaignSlug)
            ->first();

        if (!$campaign) {
            $this->query->whereRaw('1=0');
            return $this;
        }

        $country = $this->country;
        $campaignId = (int) $campaign->id;

        $this->query->whereIn('c.product_id', function ($sub) use ($country, $campaignId) {
            $sub->select('cp.product_id')
                ->from('ak_campaign_product as cp')
                ->where('cp.country_code', $country)
                ->where('cp.campaign_id', $campaignId);
        });

        return $this;
    }

    /** Атрибуты (EXISTS к ak_catalog_attr по group_id) */
    public function filterByAttributes($except_attribute_id = null): self
    {
        $prepared = $this->prepareAttributes((array) $this->request->input('attrs', []));
        if (empty($prepared)) return $this;

        // привести к виду [attr_id => ['value_ids'=>[], 'from'=>.., 'to'=>..]]
        $byAttr = [];
        foreach ($prepared as $item) {
            $attrId = (int) ($item['attr_id'] ?? 0);
            if (!$attrId) continue;

            if (!empty($item['attr_value_id'])) {
                $vals = is_array($item['attr_value_id']) ? $item['attr_value_id'] : [$item['attr_value_id']];
                $vals = array_values(array_unique(array_filter(array_map('intval', $vals))));
                if (!empty($vals)) {
                    $byAttr[$attrId]['value_ids'] = $vals;
                }
                continue;
            }

            if (isset($item['from']) || isset($item['to'])) {
                $byAttr[$attrId]['from'] = array_key_exists('from', $item) && $item['from'] !== null ? (float) $item['from'] : null;
                $byAttr[$attrId]['to']   = array_key_exists('to', $item) && $item['to'] !== null ? (float) $item['to'] : null;
                continue;
            }

            if (isset($item['value']) && $item['value'] !== '') {
                $value = (float) $item['value'];
                $byAttr[$attrId]['from'] = $value;
                $byAttr[$attrId]['to']   = $value;
            }
        }

        if (empty($byAttr)) return $this;

        $country = $this->country;
        $storefront = $this->storefront;

        $this->query->whereExists(function ($outer) use ($country, $storefront, $byAttr, $except_attribute_id) {
            // Ищем модификацию c2 в той же группе, что и строка catalog 'c'
            $outer->selectRaw('1')
                ->from('ak_catalog as c2')
                ->whereColumn('c2.group_id', 'c.group_id')
                ->where('c2.country_code', $country)
                ->where('c2.storefront_code', $storefront)
                ->where('c2.is_available', 1);

            // Для КАЖДОГО правила должно существовать подходящее значение в ak_catalog_attr
            foreach ($byAttr as $attrId => $rule) {
                if(is_int($except_attribute_id) && $except_attribute_id === $attrId) {
                    continue;
                }
                        
                $outer->whereExists(function ($sub) use ($country, $storefront, $attrId, $rule) {
                    $sub->selectRaw('1')
                        ->from('ak_catalog_attr as a')
                        ->where('a.country_code', $country)
                        ->where('a.storefront_code', $storefront)
                        ->whereColumn('a.group_id', 'c2.group_id')
                        ->where('a.attribute_id', $attrId)
                        ->where(function ($w) {
                            // групповой атрибут подходит для любой модификации в группе
                            $w->whereNull('a.product_id')
                              ->orWhereColumn('a.product_id', 'c2.product_id');
                        });

                    if (isset($rule['value_ids'])) {
                        // дискретные (множество значений)
                        $sub->whereIn('a.attribute_value_id', $rule['value_ids']);
                    } else {
                        // числовые диапазоны
                        if ($rule['from'] !== null) $sub->where('a.value', '>=', (float) $rule['from']);
                        if ($rule['to']   !== null) $sub->where('a.value', '<=', (float) $rule['to']);
                    }
                });
            }
        });

        return $this;
    }

    /** Сортировка (по умолчанию: в наличии -> manual_sort (>=0, <0, null) -> created_at desc -> id desc) */
    public function sorting(): self
    {
        $orderBy  = $this->request->input('order_by');
        $orderDir = strtolower($this->request->input('order_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (!$orderBy) {
            $this->query->orderByRaw('IF(c.in_stock > 0, 1, 0) DESC');
            $this->query->orderByRaw('CASE WHEN c.manual_sort IS NULL THEN 2 WHEN c.manual_sort < 0 THEN 1 ELSE 0 END ASC');
            $this->query->orderBy('c.manual_sort', 'desc');
            $this->query->orderByRaw("COALESCE(c.created_at, '1970-01-01 00:00:00') DESC");
            $this->query->orderBy('c.product_id', 'desc');
            return $this;
        }

        if ($orderBy === 'in_stock') {
            $this->query->orderByRaw('IF(c.in_stock > 0, 1, 0) '.$orderDir);
        } elseif ($orderBy === 'sales') {
            $this->query->leftJoin('ak_order_product as op_sort', 'op_sort.product_id', '=', 'c.product_id')
              ->groupBy('c.product_id')
              ->orderByRaw('SUM(op_sort.amount) '.$orderDir);
        } elseif ($orderBy === 'sale') {
            $this->query->orderByRaw('(c.old_price - c.price) '.$orderDir);
        } else {
            // универсальная колонка (если есть в ak_catalog)
            $this->query->orderBy('c.'.$orderBy, $orderDir);
        }

        return $this;
    }

    /** Список вариантов сортировки (совместим с прежним Product::getSortingDataWithActive) */
    public function getSortingData(): array
    {
        $activeBy  = $this->request->input('order_by');
        $activeDir = $activeBy
            ? (strtolower($this->request->input('order_dir', 'desc')) === 'asc' ? 'asc' : 'desc')
            : null;

        $list = [
            ['id'=>'default', 'name'=>__('backpack-store::filter.sorting.default'), 'by'=>null,        'dir'=>null],
            ['id'=>'price_asc','name'=>__('backpack-store::filter.sorting.price_asc'),'by'=>'price', 'dir'=>'asc'],
            ['id'=>'price_desc','name'=>__('backpack-store::filter.sorting.price_desc'),'by'=>'price','dir'=>'desc'],
            ['id'=>'sale_desc','name'=>__('backpack-store::filter.sorting.sale_desc'),'by'=>'sale',  'dir'=>'desc'],
            ['id'=>'stock_desc','name'=>__('backpack-store::filter.sorting.stock_desc'),'by'=>'in_stock','dir'=>'desc'],
            ['id'=>'sales_desc','name'=>__('backpack-store::filter.sorting.sales_desc'),'by'=>'sales','dir'=>'desc'],
        ];

        foreach ($list as &$opt) {
            $opt['active'] = ($opt['by'] === $activeBy) && (($opt['dir'] ?? null) === $activeDir);
        }
        return $list;
    }




      /**
     * Главный метод: пагинация по ГРУППАМ, внутри каждой группы — все модификации,
     * у модификаций стоит флаг passed_filter (если мод прошёл отбор).
     */
    public function getPaginated(): array
    {
        // 1) Базовый фильтрованный срез (на уровне МОДИФИКАЦИЙ)
        $filtered = $this->startQuery()
                          ->filterByCategories()
                          ->filterByBrandSlug()
                          ->filterByBrands()
                          ->filterByPrice()
                          ->filterByAttributes()
                          ->filterBySelections()
                          ->filterByCampaign()
                          ->filterBySearch()
                          ->getQuery();

        // Все product_id, прошедшие фильтр (для флага)
        $passedIds = (clone $filtered)->pluck('c.product_id')->all();
        $passedSet = array_fill_keys(array_map('intval', $passedIds), true);
        $campaignFilterRequested = $this->isCampaignFilterRequested();

        // 2) Пагинация по group_id из ОТФИЛЬТРОВАННОГО среза
        [$pageGroupIds, $totalGroups] = $this->pageGroupIds($filtered);

        if (empty($pageGroupIds)) {
            $empty = collect();
            $paginator = new LengthAwarePaginator($empty, 0, (int)$this->request->input('per_page', 24), (int)$this->request->input('page', 1), [
                'path' => request()->url(),
                'query'=> request()->query(),
            ]);
            return [$paginator, $empty];
        }

        // 3) Вытянуть все модификации выбранных групп (страна + доступность), БЕЗ доп. фильтров
        $modsByGroup = $this->fetchAllModsForGroups($pageGroupIds);
        $groupNames = $this->fetchGroupNames($pageGroupIds);

        // 4) Собрать по одной «карточке» на группу: берём любую модификацию как базу,
        //    пришиваем все модификации и проставляем passed_filter на каждой модификации
        $items = collect();
        foreach ($pageGroupIds as $gid) {
            /** @var \Illuminate\Support\Collection $mods */
            $mods = $modsByGroup[$gid] ?? collect();
            if ($mods->isEmpty()) {
                continue;
            }

            // Проставим флаг на модификации + отсортируем модификации (например, по price asc)
            $mods = $mods->map(function (Catalog $m) use ($passedSet) {
                $m->setAttribute('passed_filter', isset($passedSet[(int) $m->product_id]));
                return $m;
            })->sortBy('price')->values();

            // На campaign-странице показываем только модификации, реально прошедшие campaign-фильтр.
            if ($campaignFilterRequested) {
                $mods = $mods->filter(function (Catalog $mod) {
                    return (bool) $mod->getAttribute('passed_filter');
                })->values();

                if ($mods->isEmpty()) {
                    continue;
                }
            }

            // Базовая запись товара — берём первую модификацию (поля общие для группы)
            /** @var Catalog $base */
            $base = $mods->first();
            $base->setAttribute('name', $groupNames[(int) $gid] ?? $base->getAttribute('name'));

            // Пришиваем модификации к базе, чтобы ресурсы могли $this->modifications()
            $base->setRelation('modifications', $mods);

            // (опционально) можно проставить «активную модификацию» = первая прошедшая фильтр, если нужна
            $active = $mods->where('in_stock', '>', 0)->firstWhere('passed_filter', true) ?? $mods->first();
            $base->setRelation('active_modification', $active);

            $hasStock = $mods->contains(fn($m) => (int) ($m->in_stock ?? 0) > 0);
            $maxStock = $mods->max('in_stock') ?? 0;
            $base->setAttribute('group_has_stock', $hasStock);
            $base->setAttribute('group_stock_level', $maxStock);

            $sortMod = $mods->first(function (Catalog $mod) {
                return $mod->getAttribute('passed_filter') && (int) ($mod->in_stock ?? 0) > 0;
            });
            $base->setAttribute('sort_price', $sortMod ? (float) $sortMod->price : null);
            $base->setAttribute('sort_sale', $sortMod ? ((float) ($sortMod->old_price ?? 0) - (float) ($sortMod->price ?? 0)) : null);
            $base->setAttribute('sort_reviews', $sortMod ? (int) ($sortMod->reviews ?? 0) : null);
            $base->setAttribute('sort_manual', $this->resolveRepresentativeManualSort($mods));
            $base->setAttribute('sort_created_at', $this->resolveRepresentativeCreatedAt($mods));

            $items->push($base);
        }

        // 5) Сортировка карточек групп (по выбранному правилу) — уже на коллекции
        $items = $this->sortRepresentatives($items);

        // 6) Пагинатор (total считаем по distinct group_id в отфильтрованном срезе)
        $perPage = (int) $this->request->input('per_page', 24);
        $page    = (int) max(1, (int)$this->request->input('page', 1));
        $paginator = new LengthAwarePaginator($items, $totalGroups, $perPage, $page, [
            'path' => request()->url(),
            'query'=> request()->query(),
        ]);

        return [$paginator, $items];
    }

    protected function fetchGroupNames(array $groupIds): array
    {
        if (empty($groupIds)) {
            return [];
        }

        $locale = backpack_translatable_request_locale(null) ?? app()->getLocale();
        $fallbackLocale = config('app.fallback_locale');

        return DB::table('ak_products')
            ->whereIn('id', array_map('intval', $groupIds))
            ->pluck('name', 'id')
            ->map(function ($name) use ($locale, $fallbackLocale) {
                if (!is_string($name) || trim($name) === '') {
                    return null;
                }

                $decoded = json_decode($name, true);

                if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                    return $name;
                }

                foreach (array_filter([$locale, $fallbackLocale]) as $localeCode) {
                    $value = $decoded[$localeCode] ?? null;

                    if (is_string($value) && trim($value) !== '') {
                        return $value;
                    }
                }

                foreach ($decoded as $value) {
                    if (is_string($value) && trim($value) !== '') {
                        return $value;
                    }
                }

                return null;
            })
            ->filter()
            ->all();
    }

    /**
     * Пагинация по group_id из ОТФИЛЬТРОВАННОГО среза (2 коротких запроса: count + ids страницы).
     * Сортировка для страниц — по MIN(price) среди ПРОШЕДШИХ модификаций (логично для price-сортировок).
     */
    protected function pageGroupIds($filtered): array
    {
        $orderBy  = $this->request->input('order_by');
        $orderDir = strtolower($this->request->input('order_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage  = (int) $this->request->input('per_page', 24);
        $page     = (int) max(1, (int)$this->request->input('page', 1));
        $offset   = ($page - 1) * $perPage;

        // sub: группы и «ключ сортировки по группе» из ПРОШЕДШИХ
        $g = DB::query()
            ->fromSub(
                (clone $filtered)
                    ->select([
                        'c.group_id',
                        DB::raw('MIN(CASE WHEN c.in_stock > 0 THEN c.price ELSE NULL END) as sort_price'),
                        DB::raw('MAX( IF(c.in_stock>0, 1, 0) ) as sort_stock'),
                        DB::raw('MAX(CASE WHEN c.in_stock > 0 THEN c.manual_sort ELSE NULL END) as sort_manual'),
                        DB::raw('MAX(CASE WHEN c.in_stock > 0 THEN c.created_at ELSE NULL END) as sort_created_at'),
                        DB::raw('MAX(CASE WHEN c.in_stock > 0 THEN (c.old_price - c.price) ELSE NULL END) as sort_sale'),
                        DB::raw('MAX(CASE WHEN c.in_stock > 0 THEN c.reviews ELSE NULL END) as sort_reviews'),
                    ])
                    ->groupBy('c.group_id'),
                'g'
            );

        // считаем всего групп
        $total = (clone $g)->count();

        // применяем сортировку по группе (price/in_stock/sale/sales/default)
        if ($orderBy === 'in_stock') {
            $g->orderBy('g.sort_stock', $orderDir)
              ->orderByRaw('CASE WHEN g.sort_price IS NULL THEN 1 ELSE 0 END ASC')
              ->orderBy('g.sort_price', 'asc');
        } elseif ($orderBy === 'sale') {
            $g->orderByRaw('CASE WHEN g.sort_sale IS NULL THEN 1 ELSE 0 END ASC')
              ->orderBy('g.sort_sale', $orderDir)
              ->orderBy('g.sort_price', 'asc');
        } elseif ($orderBy === 'sales') {
            // у нас нет суммарных sales в кэше — используем reviews как прокси, либо sort_reviews
            $g->orderByRaw('CASE WHEN g.sort_reviews IS NULL THEN 1 ELSE 0 END ASC')
              ->orderBy('g.sort_reviews', $orderDir)
              ->orderBy('g.sort_price', 'asc');
        } elseif ($orderBy === 'price') {
            $g->orderByRaw('CASE WHEN g.sort_price IS NULL THEN 1 ELSE 0 END ASC')
              ->orderBy('g.sort_price', $orderDir);
        } else {
            // дефолт: в наличии -> manual_sort (>=0, <0, null) -> created_at desc
            $g->orderBy('g.sort_stock', 'desc')
              ->orderByRaw('CASE WHEN g.sort_manual IS NULL THEN 2 WHEN g.sort_manual < 0 THEN 1 ELSE 0 END ASC')
              ->orderBy('g.sort_manual', 'desc')
              ->orderByRaw('CASE WHEN g.sort_created_at IS NULL THEN 1 ELSE 0 END ASC')
              ->orderBy('g.sort_created_at', 'desc')
              ->orderByRaw('CASE WHEN g.sort_price IS NULL THEN 1 ELSE 0 END ASC')
              ->orderBy('g.sort_price', 'asc');
        }

        // группа id текущей страницы
        $pageIds = (clone $g)
            ->forPage($page, $perPage)
            ->pluck('g.group_id')
            ->all();

        return [$pageIds, $total];
    }

    /**
     * Все модификации для набора group_id (страна + is_available=1).
     * Один запрос. Без повторного применения фильтров.
     */
    protected function fetchAllModsForGroups(array $groupIds): array
    {
        $rows = DB::table('ak_catalog as c')
            ->where('c.country_code', $this->country)
            ->where('c.storefront_code', $this->storefront)
            ->where('c.is_available', 1)
            ->whereIn('c.group_id', $groupIds)
            ->get(['c.*']);

        $grouped = Catalog::hydrate($rows->map(fn($r)=>(array)$r)->all())
            ->groupBy('group_id')
            ->all();

        foreach ($grouped as $groupId => $mods) {
            $grouped[$groupId] = $this->applyCampaignPricingToMods($mods);
        }

        return $grouped; // [group_id => Collection<Catalog>]
    }

    protected function applyCampaignPricingToMods(Collection $mods): Collection
    {
        if ($mods->isEmpty()) {
            return $mods;
        }

        $resolver = app(CampaignResolverService::class);
        $productIds = $mods->pluck('product_id')->map(fn($id) => (int) $id)->all();
        $campaignMap = $resolver->forProducts($productIds, $this->country);

        return $mods->map(function (Catalog $mod) use ($resolver, $campaignMap) {
            $productId = (int) $mod->product_id;
            $campaign = $campaignMap[$productId] ?? null;

            $price = (float) $mod->price;
            $oldPrice = $mod->old_price !== null ? (float) $mod->old_price : null;

            $applied = $resolver->applyPricing(
                productId: $productId,
                price: $price,
                oldPrice: $oldPrice,
                countryCode: $this->country
            );

            $mod->setAttribute('price', $applied['price']);
            $mod->setAttribute('old_price', $applied['old_price']);
            $mod->setAttribute('base_price', $applied['base_price']);
            $mod->setAttribute('campaign_discount_amount', $applied['campaign_discount_amount']);
            $mod->setAttribute('campaign', $campaign);
            $mod->setAttribute('sale', $this->calculateSalePercent($applied['price'], $applied['old_price']));

            return $mod;
        });
    }

    protected function calculateSalePercent(?float $price, ?float $oldPrice): ?float
    {
        if ($price === null || $oldPrice === null || $oldPrice <= 0 || $price >= $oldPrice) {
            return null;
        }

        return round((1 - ($price / $oldPrice)) * 100, 2);
    }

    /**
     * Сортировка карточек групп (уже собранных): default / price / in_stock / sale / sales.
     * Здесь сортируем по полям «базовой» модификации (она у нас просто первая в коллекции items).
     */
    protected function sortRepresentatives(Collection $items): Collection
    {
        $orderBy  = $this->request->input('order_by');
        $orderDir = strtolower($this->request->input('order_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (!$orderBy) {
            return $items->sort(function (Catalog $a, Catalog $b) {
                $stockCmp = ($this->groupHasStock($b) ? 1 : 0) <=> ($this->groupHasStock($a) ? 1 : 0);
                if ($stockCmp !== 0) {
                    return $stockCmp;
                }

                $manualCmp = $this->compareManualSortForDefault(
                    $this->representativeSortManual($a),
                    $this->representativeSortManual($b)
                );
                if ($manualCmp !== 0) {
                    return $manualCmp;
                }

                $createdCmp = $this->compareNullableMetric(
                    $this->representativeSortCreatedAt($a),
                    $this->representativeSortCreatedAt($b),
                    true
                );
                if ($createdCmp !== 0) {
                    return $createdCmp;
                }

                return ((int) $b->product_id) <=> ((int) $a->product_id);
            })->values();
        }

        $reverse = $orderDir !== 'asc';

        if ($orderBy === 'price') {
            return $this->sortByRepresentativeMetric($items, fn (Catalog $item) => $this->representativeSortPrice($item), $reverse);
        }
        if ($orderBy === 'in_stock') {
            return $items->sortBy(fn($x)=> $this->groupStockLevel($x), SORT_REGULAR, $reverse)->values();
        }
        if ($orderBy === 'sale') {
            return $this->sortByRepresentativeMetric($items, fn (Catalog $item) => $this->representativeSortSale($item), $reverse);
        }
        if ($orderBy === 'sales') {
            // прокси: reviews
            return $this->sortByRepresentativeMetric($items, fn (Catalog $item) => $this->representativeSortReviews($item), $reverse);
        }

        return $items->sortBy(fn($x)=>$x->{$orderBy} ?? null, SORT_REGULAR, $reverse)->values();
    }

    protected function sortByRepresentativeMetric(Collection $items, callable $resolver, bool $descending): Collection
    {
        return $items->sort(function (Catalog $a, Catalog $b) use ($resolver, $descending) {
            $aVal = $resolver($a);
            $bVal = $resolver($b);

            $aNull = $aVal === null;
            $bNull = $bVal === null;

            if ($aNull && $bNull) {
                return 0;
            }
            if ($aNull) {
                return 1;
            }
            if ($bNull) {
                return -1;
            }

            if ($aVal == $bVal) {
                return 0;
            }

            return $descending ? ($bVal <=> $aVal) : ($aVal <=> $bVal);
        })->values();
    }

    protected function representativeSortPrice(Catalog $item): ?float
    {
        $value = $item->getAttribute('sort_price');
        return $value !== null ? (float) $value : null;
    }

    protected function representativeSortSale(Catalog $item): ?float
    {
        $value = $item->getAttribute('sort_sale');
        return $value !== null ? (float) $value : null;
    }

    protected function representativeSortReviews(Catalog $item): ?int
    {
        $value = $item->getAttribute('sort_reviews');
        return $value !== null ? (int) $value : null;
    }

    protected function representativeSortManual(Catalog $item): ?float
    {
        $value = $item->getAttribute('sort_manual');
        if ($value === null) {
            $value = $item->manual_sort ?? null;
        }

        return $value !== null ? (float) $value : null;
    }

    protected function representativeSortCreatedAt(Catalog $item): ?int
    {
        $value = $item->getAttribute('sort_created_at');
        if ($value === null) {
            $value = $item->created_at ?? null;
        }

        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? null : $timestamp;
    }

    protected function resolveRepresentativeManualSort(Collection $mods): ?float
    {
        $candidate = $mods
            ->where('passed_filter', true)
            ->filter(function ($mod) {
                return (int) ($mod->in_stock ?? 0) > 0 && $mod->manual_sort !== null;
            })
            ->max('manual_sort');

        if ($candidate === null) {
            return null;
        }

        return (float) $candidate;
    }

    protected function resolveRepresentativeCreatedAt(Collection $mods): ?int
    {
        $preferred = $mods
            ->where('passed_filter', true)
            ->filter(fn($mod) => (int) ($mod->in_stock ?? 0) > 0);

        $fallback = $mods->where('passed_filter', true);
        $all = $mods;

        foreach ([$preferred, $fallback, $all] as $set) {
            $timestamps = $set
                ->map(function ($mod) {
                    $value = $mod->created_at ?? null;
                    if ($value instanceof \DateTimeInterface) {
                        return $value->getTimestamp();
                    }

                    if ($value === null) {
                        return null;
                    }

                    $timestamp = strtotime((string) $value);
                    return $timestamp === false ? null : $timestamp;
                })
                ->filter(function ($value) {
                    return $value !== null;
                });

            if ($timestamps->isNotEmpty()) {
                return (int) $timestamps->max();
            }
        }

        return null;
    }

    protected function compareNullableMetric($left, $right, bool $descending): int
    {
        $leftNull = $left === null;
        $rightNull = $right === null;

        if ($leftNull && $rightNull) {
            return 0;
        }
        if ($leftNull) {
            return 1;
        }
        if ($rightNull) {
            return -1;
        }

        if ($left == $right) {
            return 0;
        }

        return $descending ? ($right <=> $left) : ($left <=> $right);
    }

    protected function compareManualSortForDefault(?float $left, ?float $right): int
    {
        $leftBucket = $this->manualSortBucket($left);
        $rightBucket = $this->manualSortBucket($right);

        if ($leftBucket !== $rightBucket) {
            return $leftBucket <=> $rightBucket;
        }

        if ($left === null && $right === null) {
            return 0;
        }

        if ($left == $right) {
            return 0;
        }

        return $right <=> $left;
    }

    protected function manualSortBucket(?float $value): int
    {
        if ($value === null) {
            return 2;
        }

        return $value < 0 ? 1 : 0;
    }

    protected function groupHasStock(Catalog $item): bool
    {
        $value = $item->getAttribute('group_has_stock');
        if ($value !== null) {
            return (bool) $value;
        }

        return (int) ($item->in_stock ?? 0) > 0;
    }

    protected function groupStockLevel(Catalog $item)
    {
        $value = $item->getAttribute('group_stock_level');
        if ($value !== null) {
            return $value;
        }

        return $item->in_stock ?? 0;
    }

    protected function isCampaignFilterRequested(): bool
    {
        $slug = $this->request->input('campaign');

        return is_string($slug) && trim($slug) !== '';
    }



    public function getProducts($clear = false)
    {
      [$paginator, $items] = $this->getPaginated();

      if($clear) {
        return $paginator->setCollection($items);
      }else {
        return new ProductCollection($paginator->setCollection($items));
      }
    }
}
