<?php
namespace Backpack\Store\app\Services\Catalog;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\Catalog;
use Backpack\Store\app\Services\Cache\SlugMapCache;

use Backpack\Store\app\Services\Catalog\AbstractQueryService;

use Backpack\Store\app\Http\Resources\ProductCollection;

class CatalogQueryService extends AbstractQueryService
{
    protected Request $request;
    protected string $country;
    protected $query;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->country = \Store::context()->country;
    }

    /** Базовый запрос к ak_catalog */
    public function startQuery(): self
    {
        $this->query = DB::table('ak_catalog as c')
            ->where('c.country_code', $this->country)
            ->where('c.is_available', 1);

        return $this;
    }

    /** Категория: в c.category_ids уже лежат ВСЕ категории; ноды не нужны */
    public function filterByCategories(): self
    {
        /** @var SlugMapCache $map */
        $map = app(SlugMapCache::class);

        // либо category_id, либо category_slug
        $categoryId = $this->request->input('category_id');

        if (!$categoryId && ($slug = $this->request->input('category_slug'))) {
            $categoryId = $map->categoryIdBySlug($slug);
        }

        if ($categoryId) {
            // category_ids — JSON-массив всех категорий товара (вкл. родительские/дочерние уже записаны при кэше)
            $this->query->whereJsonContains('c.category_ids', (int) $categoryId);
        }

        return $this;
    }

    public function filterByBrandSlug(): self
    {
        if ($slug = $this->request->input('brand_slug')) {
            /** @var SlugMapCache $map */
            $map = app(SlugMapCache::class);
            if ($id = $map->brandIdBySlug($slug)) {
                $q->where('c.brand_id', $id);
            } else {
                // не найден — гарантированно пустой результат
                $q->whereRaw('1=0');
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
            // по варианту; если нужно по группе — можно заменить на подзапрос с суммой по группе
            $this->query->join('ak_order_product as op', 'op.product_id', '=', 'c.product_id')
              ->groupBy('c.product_id')
              ->havingRaw('SUM(op.amount) >= ?', [$salesMin]);
        }

        return $this;
    }

    /** Атрибуты (EXISTS к ak_catalog_attr по group_id) */
    public function filterByAttributes($except_attribute_id = null): self
    {
        $attrs = (array) $this->request->input('attrs', []);
        if (empty($attrs)) return $this;

        // привести к виду [attr_id => ['value_ids'=>[], 'from'=>.., 'to'=>..]]
        $byAttr = [];
        foreach ($attrs as $item) {
            $attrId = (int) ($item['attr_id'] ?? 0);
            if (!$attrId) continue;

            if (isset($item['attr_value_id'])) {
                $vals = (array) $item['attr_value_id'];
                $vals = array_filter(array_map('intval', $vals));
                if (!empty($vals)) {
                    $byAttr[$attrId]['value_ids'] = array_values(array_unique(array_merge($byAttr[$attrId]['value_ids'] ?? [], $vals)));
                }
            } else {
                $from = array_key_exists('from', $item) ? $item['from'] : null;
                $to   = array_key_exists('to', $item)   ? $item['to']   : null;
                $byAttr[$attrId]['from'] = $from !== null ? (float)$from : null;
                $byAttr[$attrId]['to']   = $to   !== null ? (float)$to   : null;
            }
        }

        if (empty($byAttr)) return $this;

        $country = $this->country;

        $this->query->whereExists(function ($outer) use ($country, $byAttr, $except_attribute_id) {
            // Ищем модификацию c2 в той же группе, что и строка catalog 'c'
            $outer->selectRaw('1')
                ->from('ak_catalog as c2')
                ->whereColumn('c2.group_id', 'c.group_id')
                ->where('c2.country_code', $country)
                ->where('c2.is_available', 1);

            // Для КАЖДОГО правила должно существовать подходящее значение в ak_catalog_attr
            foreach ($byAttr as $attrId => $rule) {
                if(is_int($except_attribute_id) && $except_attribute_id === $attrId) {
                    continue;
                }
                        
                $outer->whereExists(function ($sub) use ($country, $attrId, $rule) {
                    $sub->selectRaw('1')
                        ->from('ak_catalog_attr as a')
                        ->where('a.country_code', $country)
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

    /** Сортировка (по умолчанию: в наличии -> с картинкой -> id desc (приближение к новизне)) */
    public function sorting(): self
    {
        $orderBy  = $this->request->input('order_by');
        $orderDir = strtolower($this->request->input('order_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (!$orderBy) {
            $this->query->orderByRaw('IF(c.in_stock > 0, 1, 0) DESC');
            $this->query->orderByRaw('JSON_LENGTH(c.images) DESC');
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
        $activeDir = strtolower($this->request->input('order_dir','desc')) === 'asc' ? 'asc' : 'desc';

        $list = [
            ['id'=>'default', 'name'=>__('backpack-store::sort.default'), 'by'=>null,        'dir'=>null],
            ['id'=>'price_asc','name'=>__('backpack-store::sort.price_asc'),'by'=>'price', 'dir'=>'asc'],
            ['id'=>'price_desc','name'=>__('backpack-store::sort.price_desc'),'by'=>'price','dir'=>'desc'],
            ['id'=>'sale_desc','name'=>__('backpack-store::sort.sale_desc'),'by'=>'sale',  'dir'=>'desc'],
            ['id'=>'stock_desc','name'=>__('backpack-store::sort.stock_desc'),'by'=>'in_stock','dir'=>'desc'],
            ['id'=>'sales_desc','name'=>__('backpack-store::sort.sales_desc'),'by'=>'sales','dir'=>'desc'],
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
                          ->filterBySearch()
                          ->getQuery();

        // Все product_id, прошедшие фильтр (для флага)
        $passedIds = (clone $filtered)->pluck('c.product_id')->all();

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

        // 4) Собрать по одной «карточке» на группу: берём любую модификацию как базу,
        //    пришиваем все модификации и проставляем passed_filter на каждой модификации
        $items = collect();
        foreach ($pageGroupIds as $gid) {
            /** @var \Illuminate\Support\Collection $mods */
            $mods = $modsByGroup[$gid] ?? collect();
            if ($mods->isEmpty()) {
                continue;
            }

            // Базовая запись товара — берём первую модификацию (поля общие для группы)
            /** @var Catalog $base */
            $base = $mods->first();

            // Проставим флаг на модификации + отсортируем модификации (например, по price asc)
            $mods = $mods->map(function (Catalog $m) use ($passedIds) {
                if (in_array($m->product_id, $passedIds, true)) {
                    $m->setAttribute('passed_filter', true);
                } else {
                    $m->setAttribute('passed_filter', false);
                }
                return $m;
            })->sortBy('price')->values();

            // Пришиваем модификации к базе, чтобы ресурсы могли $this->modifications()
            $base->setRelation('modifications', $mods);

            // (опционально) можно проставить «активную модификацию» = первая прошедшая фильтр, если нужна
            // $active = $mods->firstWhere('passed_filter', true) ?? $mods->first();
            // $base->setRelation('active_modification', $active);

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

        // dd($items[0]->modifications);
        return [$paginator, $items];
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
                        DB::raw('MIN(c.price) as sort_price'),
                        DB::raw('MAX( IF(c.in_stock>0, 1, 0) ) as sort_stock'),
                        DB::raw('MAX( (c.old_price - c.price) ) as sort_sale'),
                        DB::raw('MAX(c.reviews) as sort_reviews'),
                    ])
                    ->groupBy('c.group_id'),
                'g'
            );

        // считаем всего групп
        $total = (clone $g)->count();

        // применяем сортировку по группе (price/in_stock/sale/sales/default)
        if ($orderBy === 'in_stock') {
            $g->orderBy('g.sort_stock', $orderDir)->orderBy('g.sort_price', 'asc');
        } elseif ($orderBy === 'sale') {
            $g->orderBy('g.sort_sale', $orderDir)->orderBy('g.sort_price', 'asc');
        } elseif ($orderBy === 'sales') {
            // у нас нет суммарных sales в кэше — используем reviews как прокси, либо sort_reviews
            $g->orderBy('g.sort_reviews', $orderDir)->orderBy('g.sort_price', 'asc');
        } elseif ($orderBy === 'price') {
            $g->orderBy('g.sort_price', $orderDir);
        } else {
            // дефолт: в наличии -> цена возр.
            $g->orderBy('g.sort_stock', 'desc')->orderBy('g.sort_price', 'asc');
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
            ->where('c.is_available', 1)
            ->whereIn('c.group_id', $groupIds)
            ->get(['c.*']);

        return Catalog::hydrate($rows->map(fn($r)=>(array)$r)->all())
            ->groupBy('group_id')
            ->all(); // [group_id => Collection<Catalog>]
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
            return $items->sortBy([
                fn($a,$b)=>($b->in_stock > 0 <=> $a->in_stock > 0),
                fn($a,$b)=>(count($b->images ?? []) <=> count($a->images ?? [])),
                fn($a,$b)=>($b->product_id <=> $a->product_id),
            ])->values();
        }

        $reverse = $orderDir !== 'asc';

        if ($orderBy === 'price') {
            return $items->sortBy('price', SORT_REGULAR, $reverse)->values();
        }
        if ($orderBy === 'in_stock') {
            return $items->sortBy(fn($x)=>$x->in_stock > 0 ? 1 : 0, SORT_REGULAR, $reverse)->values();
        }
        if ($orderBy === 'sale') {
            return $items->sortBy(fn($x)=>(float)$x->old_price - (float)$x->price, SORT_REGULAR, $reverse)->values();
        }
        if ($orderBy === 'sales') {
            // прокси: reviews
            return $items->sortBy(fn($x)=>(int)$x->reviews, SORT_REGULAR, $reverse)->values();
        }

        return $items->sortBy(fn($x)=>$x->{$orderBy} ?? null, SORT_REGULAR, $reverse)->values();
    }



    public function getProducts()
    {
      [$paginator, $items] = $this->getPaginated();
      return new ProductCollection($paginator->setCollection($items));
    }
}
