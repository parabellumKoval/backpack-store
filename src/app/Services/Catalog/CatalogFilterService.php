<?php
namespace Backpack\Store\app\Services\Catalog;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Backpack\Store\app\Models\Brand;
use Backpack\Store\app\Models\Category;

use Backpack\Store\app\Services\Catalog\CatalogQueryService;

use Backpack\Store\app\Services\Catalog\AbstractFilterService;

class CatalogFilterService extends AbstractFilterService
{
    protected $query;
    protected Request $request;
    protected string $country;
    protected string $storefront;
    protected $product_service;

    protected string $itemsTableName = 'c';

    public function __construct(Request $request, CatalogQueryService $productService)
    {
        $this->country = \Store::context()->country;
        $this->storefront = \Store::storefront();

        $this->product_service = $productService;
        $this->request = $request;
        
        $this->query = $this->product_service
            ->startQuery()
            ->filterByCategories()
            ->getQuery();
    }


    protected function countSelections(): array
    {
        $qBase = $this->product_service->applyAllFiltersExcept('selections')->getQuery();

        $percent  = 10;
        $salesMin = 3;

        $selRow = (clone $qBase)
            ->selectRaw("
                COUNT(DISTINCT IF(c.old_price > 0, c.group_id, NULL))                              as with_sales,
                COUNT(DISTINCT IF((c.old_price - c.price) > c.price / ?, c.group_id, NULL))        as top_price,
                COUNT(DISTINCT IF(c.reviews > 0, c.group_id, NULL))                                 as with_rating,
                COUNT(DISTINCT IF(c.in_stock > 0, c.group_id, NULL))                                as in_stock
            ", [$percent])
            ->first();

        // FIX: считаем top_sales по GROUP, а не по VARIANT, и без алиасной ошибки
        $topSales = (clone $qBase)
            ->join('ak_order_product as op', 'op.product_id', '=', 'c.product_id')
            ->selectRaw('c.group_id, SUM(op.amount) as amt')
            ->groupBy('c.group_id')
            ->having('amt', '>=', $salesMin)
            ->count();

        return [
            'with_sales'  => (int) ($selRow->with_sales  ?? 0),
            'top_price'   => (int) ($selRow->top_price   ?? 0),
            'top_sales'   => (int) $topSales,
            'with_rating' => (int) ($selRow->with_rating ?? 0),
            'in_stock'    => (int) ($selRow->in_stock    ?? 0),
        ];
    }

    protected function countBrands(): array
    {
        return $this->product_service->applyAllFiltersExcept('brands')->getQuery()
            ->whereNotNull('c.brand_id')
            ->groupBy('c.brand_id')
            ->selectRaw('c.brand_id, COUNT(DISTINCT c.group_id) as cnt')
            ->pluck('cnt', 'brand_id')
            ->toArray();
    }

    protected function countPrices(): array
    {
        $row = $this->product_service->applyAllFiltersExcept('price')->getQuery()
                    ->selectRaw('MIN(c.price) as min_p, MAX(c.price) as max_p')->first();

        return [
            'min' => $row ? (float)$row->min_p : 0.0,
            'max' => $row ? (float)$row->max_p : 0.0,
        ];
    }

    protected function calculateAllAttributes($product_query): array
    {
        $country = $this->country;
        $storefront = $this->storefront;

        // Срез текущих вариантов и групп из каталога (alias: c)
        $pSub = DB::query()->fromSub(
            (clone $product_query)->select('c.product_id')->whereNotNull('c.product_id')->distinct(),
            'p'
        );
        $gSub = DB::query()->fromSub(
            (clone $product_query)->select('c.group_id')->distinct(),
            'g'
        );

        $out = [];

        // ---------- ДИСКРЕТНЫЕ (all attrs at once) ----------
        $discVar = DB::table('ak_catalog_attr as a')
            ->joinSub($pSub, 'p', 'p.product_id', '=', 'a.product_id')
            ->where('a.country_code', $country)
            ->where('a.storefront_code', $storefront)
            ->whereNotNull('a.attribute_value_id')
            ->select([
                'a.attribute_id',
                'a.attribute_value_id',
                'a.group_id',
            ]);

        $discGrp = DB::table('ak_catalog_attr as a')
            ->joinSub($gSub, 'g', 'g.group_id', '=', 'a.group_id')
            ->where('a.country_code', $country)
            ->where('a.storefront_code', $storefront)
            ->whereNull('a.product_id') // групповые
            ->whereNotNull('a.attribute_value_id')
            ->select([
                'a.attribute_id',
                'a.attribute_value_id',
                'a.group_id',
            ]);

        $discAgg = DB::query()->fromSub(
            $discVar->unionAll($discGrp),
            'x'
        )->select([
            'x.attribute_id',
            'x.attribute_value_id',
            DB::raw('COUNT(DISTINCT x.group_id) AS cnt'),
        ])
        ->groupBy('x.attribute_id', 'x.attribute_value_id')
        ->get();

        foreach ($discAgg as $r) {
            $aid = (int)$r->attribute_id;
            $vid = (int)$r->attribute_value_id;
            $out[$aid] ??= [];
            $out[$aid][$vid] = (int)$r->cnt;
        }

        // ---------- ЧИСЛОВЫЕ (all attrs at once) ----------
        $numVar = DB::table('ak_catalog_attr as a')
            ->joinSub($pSub, 'p', 'p.product_id', '=', 'a.product_id')
            ->where('a.country_code', $country)
            ->where('a.storefront_code', $storefront)
            ->whereNull('a.attribute_value_id')
            ->whereNotNull('a.value')
            ->select([
                'a.attribute_id',
                'a.value',
            ]);

        $numGrp = DB::table('ak_catalog_attr as a')
            ->joinSub($gSub, 'g', 'g.group_id', '=', 'a.group_id')
            ->where('a.country_code', $country)
            ->where('a.storefront_code', $storefront)
            ->whereNull('a.product_id')
            ->whereNull('a.attribute_value_id')
            ->whereNotNull('a.value')
            ->select([
                'a.attribute_id',
                'a.value',
            ]);

        $numAgg = DB::query()->fromSub(
            $numVar->unionAll($numGrp),
            'y'
        )->select([
            'y.attribute_id',
            DB::raw('MIN(y.value) AS min_val'),
            DB::raw('MAX(y.value) AS max_val'),
        ])
        ->groupBy('y.attribute_id')
        ->get();

        foreach ($numAgg as $r) {
            $aid = (int)$r->attribute_id;
            $out[$aid] ??= [];
            $out[$aid]['min'] = $r->min_val !== null ? (float)$r->min_val : null;
            $out[$aid]['max'] = $r->max_val !== null ? (float)$r->max_val : null;
        }

        return $out;
    }

    protected function calculateSingleAttribute($product_query, $active_attr): array
    {
        $country = $this->country;
        $storefront = $this->storefront;
        $attrId  = (int)($active_attr['attr_id'] ?? 0);

        $pSub = DB::query()->fromSub(
            (clone $product_query)->select('c.product_id')->whereNotNull('c.product_id')->distinct(),
            'p'
        );
        $gSub = DB::query()->fromSub(
            (clone $product_query)->select('c.group_id')->distinct(),
            'g'
        );

        // ЧИСЛОВОЙ?
        if (array_key_exists('from', $active_attr) || array_key_exists('to', $active_attr)) {
            $numVar = DB::table('ak_catalog_attr as a')
                ->joinSub($pSub, 'p', 'p.product_id', '=', 'a.product_id')
                ->where('a.country_code', $country)
                ->where('a.storefront_code', $storefront)
                ->where('a.attribute_id', $attrId)
                ->whereNull('a.attribute_value_id')
                ->whereNotNull('a.value')
                ->select(['a.value']);

            $numGrp = DB::table('ak_catalog_attr as a')
                ->joinSub($gSub, 'g', 'g.group_id', '=', 'a.group_id')
                ->where('a.country_code', $country)
                ->where('a.storefront_code', $storefront)
                ->whereNull('a.product_id')
                ->where('a.attribute_id', $attrId)
                ->whereNull('a.attribute_value_id')
                ->whereNotNull('a.value')
                ->select(['a.value']);

            $row = DB::query()->fromSub(
                $numVar->unionAll($numGrp),
                'z'
            )->selectRaw('MIN(z.value) AS min_val, MAX(z.value) AS max_val')->first();

            return [
                $attrId => [
                    'min' => $row && $row->min_val !== null ? (float)$row->min_val : null,
                    'max' => $row && $row->max_val !== null ? (float)$row->max_val : null,
                ],
            ];
        }

        // ДИСКРЕТНЫЙ
        $discVar = DB::table('ak_catalog_attr as a')
            ->joinSub($pSub, 'p', 'p.product_id', '=', 'a.product_id')
            ->where('a.country_code', $country)
            ->where('a.storefront_code', $storefront)
            ->where('a.attribute_id', $attrId)
            ->whereNotNull('a.attribute_value_id')
            ->select(['a.attribute_value_id','a.group_id']);

        $discGrp = DB::table('ak_catalog_attr as a')
            ->joinSub($gSub, 'g', 'g.group_id', '=', 'a.group_id')
            ->where('a.country_code', $country)
            ->where('a.storefront_code', $storefront)
            ->whereNull('a.product_id')
            ->where('a.attribute_id', $attrId)
            ->whereNotNull('a.attribute_value_id')
            ->select(['a.attribute_value_id','a.group_id']);

        $rows = DB::query()->fromSub(
            $discVar->unionAll($discGrp),
            'x'
        )->select([
            'x.attribute_value_id',
            DB::raw('COUNT(DISTINCT x.group_id) AS cnt'),
        ])
        ->groupBy('x.attribute_value_id')
        ->get();

        $out = [$attrId => []];
        foreach ($rows as $r) {
            $out[$attrId][(int)$r->attribute_value_id] = (int)$r->cnt;
        }
        return $out;
    }

    protected function getAttributes(): array
    {
        $q   = $this->query;; // страна + категория + бренд + поиск + цена + attrs + selections
        $loc = app()->getLocale();
        $fb  = config('app.fallback_locale');

        // подмножества из текущего среза каталога (alias c)
        $pSub = DB::query()->fromSub(
            (clone $q)->select('c.product_id')->whereNotNull('c.product_id')->distinct(),
            'p'
        );
        $gSub = DB::query()->fromSub(
            (clone $q)->select('c.group_id')->distinct(),
            'g'
        );

        // ---- присутствующие (attr_id, attr_value_id) для дискретных ----
        $discVar = DB::table('ak_catalog_attr as ca')
            ->joinSub($pSub, 'p', 'p.product_id', '=', 'ca.product_id')
            ->where('ca.country_code', $this->country)
            ->where('ca.storefront_code', $this->storefront)
            ->whereNotNull('ca.attribute_value_id')
            ->select('ca.attribute_id', 'ca.attribute_value_id', 'ca.group_id');

        $discGrp = DB::table('ak_catalog_attr as ca')
            ->joinSub($gSub, 'g', 'g.group_id', '=', 'ca.group_id')
            ->where('ca.country_code', $this->country)
            ->where('ca.storefront_code', $this->storefront)
            ->whereNull('ca.product_id') // групповые
            ->whereNotNull('ca.attribute_value_id')
            ->select('ca.attribute_id', 'ca.attribute_value_id', 'ca.group_id');

        // уникальные пары (attr, value) на текущем срезе
        $discPairs = DB::query()->fromSub(
            $discVar->unionAll($discGrp),
            'u'
        )->select('u.attribute_id', 'u.attribute_value_id')->distinct();

        // подтянем описания атрибутов и значений
        $discRows = DB::table('ak_attributes as a')
            ->joinSub($discPairs, 'u', 'u.attribute_id', '=', 'a.id')
            ->leftJoin('ak_attribute_values as v', 'v.id', '=', 'u.attribute_value_id')
            ->where('a.is_active', 1)
            ->where('a.in_filters', 1)
            ->whereIn('a.type', ['checkbox', 'radio']) // только дискретные тут
            ->selectRaw("
                a.id   as attr_id,
                a.type as attr_type,
                COALESCE(
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.name, '$.\"{$loc}\"')), ''),
                JSON_UNQUOTE(JSON_EXTRACT(a.name, '$.\"{$fb}\"'))
                ) as attr_name,
                COALESCE(
                  NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.extras_trans, '$.\"{$loc}\".si')), ''),
                  JSON_UNQUOTE(JSON_EXTRACT(a.extras_trans, '$.\"{$fb}\".si'))
                ) as attr_si,
                v.id as value_id,
                COALESCE(
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v.value, '$.\"{$loc}\"')), ''),
                JSON_UNQUOTE(JSON_EXTRACT(v.value, '$.\"{$fb}\"'))
                ) as value_name
            ")
            ->get();

        $byAttr = [];
        foreach ($discRows as $r) {
            $id = (int)$r->attr_id;
            if (!isset($byAttr[$id])) {
                $byAttr[$id] = [
                    'id'     => $id,
                    'name'   => $r->attr_name,
                    'type'   => $r->attr_type,
                    'si'     => $r->attr_si ?: null,
                    'values' => [],
                ];
            }
            if ($r->value_id) {
                $byAttr[$id]['values'][(int)$r->value_id] = [
                    'id'    => (int)$r->value_id,
                    'value' => $r->value_name,
                ];
            }
        }

        // ---- числовые: attr_id с value IS NOT NULL, attribute_value_id IS NULL ----
        $numVar = DB::table('ak_catalog_attr as ca')
            ->joinSub($pSub, 'p', 'p.product_id', '=', 'ca.product_id')
            ->where('ca.country_code', $this->country)
            ->where('ca.storefront_code', $this->storefront)
            ->whereNull('ca.attribute_value_id')
            ->whereNotNull('ca.value')
            ->select('ca.attribute_id');

        $numGrp = DB::table('ak_catalog_attr as ca')
            ->joinSub($gSub, 'g', 'g.group_id', '=', 'ca.group_id')
            ->where('ca.country_code', $this->country)
            ->where('ca.storefront_code', $this->storefront)
            ->whereNull('ca.product_id')    // групповые
            ->whereNull('ca.attribute_value_id')
            ->whereNotNull('ca.value')
            ->select('ca.attribute_id');

        $numAttrs = DB::query()->fromSub(
            $numVar->unionAll($numGrp),
            'n'
        )->select('n.attribute_id')->distinct();

        $numRows = DB::table('ak_attributes as a')
            ->joinSub($numAttrs, 'n', 'n.attribute_id', '=', 'a.id')
            ->where('a.is_active', 1)
            ->where('a.in_filters', 1)
            ->where('a.type', 'number')
            ->selectRaw("
                a.id   as attr_id,
                a.type as attr_type,
                COALESCE(
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.name, '$.\"{$loc}\"')), ''),
                JSON_UNQUOTE(JSON_EXTRACT(a.name, '$.\"{$fb}\"'))
                ) as attr_name,
                COALESCE(
                  NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.extras_trans, '$.\"{$loc}\".si')), ''),
                  JSON_UNQUOTE(JSON_EXTRACT(a.extras_trans, '$.\"{$fb}\".si'))
                ) as attr_si
            ")
            ->get();

        foreach ($numRows as $r) {
            $id = (int)$r->attr_id;
            if (!isset($byAttr[$id])) {
                $byAttr[$id] = [
                    'id'     => $id,
                    'name'   => $r->attr_name,
                    'type'   => $r->attr_type, // 'number'
                    'si'     => $r->attr_si ?: null,
                    'values' => null,
                ];
            } else {
                // если атрибут встречается и как дискретный, и как числовой (аномалия) — считаем числовым
                $byAttr[$id]['type']   = 'number';
                $byAttr[$id]['values'] = null;
            }
        }

        // нормализуем values в массив
        foreach ($byAttr as &$a) {
            if (is_array($a['values'])) {
                $a['values'] = array_values($a['values']);
            }
        }

        return array_values($byAttr);
    }


    // protected function getAttributes(): array
    // {
    //     $q = $this->query; // страна + категория + бренд + поиск + цена + attrs + selections
    //     $loc = app()->getLocale();
    //     $fb  = config('app.fallback_locale');

    //     // товары текущего среза (product_id)
    //     $productsSub = (clone $q)->select('c.product_id')->distinct();

    //     $rows = DB::table('ak_catalog_attr as ca')
    //         ->join('ak_attributes as a', 'a.id','=','ca.attribute_id')
    //         ->leftJoin('ak_attribute_values as v','v.id','=','ca.attribute_value_id')
    //         ->where('ca.country_code', $this->country)
    //         ->whereIn('ca.product_id', $productsSub)
    //         ->where('a.is_active', 1)
    //         ->where('a.in_filters', 1)
    //         ->whereIn('a.type', ['checkbox','radio','number'])
    //         ->selectRaw("
    //             a.id as attr_id,
    //             a.type as attr_type,
    //             COALESCE(
    //               NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.name, '$.\"{$loc}\"')), ''),
    //               JSON_UNQUOTE(JSON_EXTRACT(a.name, '$.\"{$fb}\"'))
    //             ) as attr_name,
    //             COALESCE(
    //               NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.extras_trans, '$.\"{$loc}\".si')), ''),
    //               JSON_UNQUOTE(JSON_EXTRACT(a.extras_trans, '$.\"{$fb}\".si'))
    //             ) as attr_si,
    //             v.id  as value_id,
    //             COALESCE(
    //               NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v.value, '$.\"{$loc}\"')), ''),
    //               JSON_UNQUOTE(JSON_EXTRACT(v.value, '$.\"{$fb}\"'))
    //             ) as value_name
    //         ")
    //         ->distinct()
    //         ->get();

    //     $byAttr = [];
    //     foreach ($rows as $r) {
    //         $id = (int) $r->attr_id;
    //         if (!isset($byAttr[$id])) {
    //             $byAttr[$id] = [
    //                 'id'     => $id,
    //                 'name'   => $r->attr_name,
    //                 'type'   => $r->attr_type,
    //                 'si'     => $r->attr_si ?: null,
    //                 'values' => $r->attr_type === 'number' ? null : [],
    //             ];
    //         }
    //         if ($r->attr_type !== 'number' && $r->value_id) {
    //             $byAttr[$id]['values'][$r->value_id] = [
    //                 'id'    => (int)$r->value_id,
    //                 'value' => $r->value_name,
    //             ];
    //         }
    //     }

    //     // нормализуем values в массив
    //     foreach ($byAttr as &$a) {
    //         if (is_array($a['values'])) $a['values'] = array_values($a['values']);
    //     }

    //     // вернуть в виде списка
    //     return array_values($byAttr);
    // }
}
