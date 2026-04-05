<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

use Backpack\Store\app\Http\Resources\ProductCollection;


use Backpack\Store\app\Services\Catalog\ProductFilterService;
use Backpack\Store\app\Services\Catalog\ProductQueryService;


use Backpack\Store\app\Contracts\FilterService;
use Backpack\Store\app\Contracts\QueryService;

// новые сервисы (кеш-режим)
use Backpack\Store\app\Services\Catalog\CatalogFilterService as CachedFilterService;
use Backpack\Store\app\Services\Catalog\CatalogQueryService  as CachedQueryService;

//
use Backpack\Store\app\Models\Catalog;

class CatalogController
{

    use \Backpack\Store\app\Traits\Resources;

    /** Ключи, которые НЕ относятся к фильтрам (для cache-keys) */
    protected array $nonFiltersExclude = [
        'page','per_page','order_by','order_dir',
        'with_filter','with_filter_count','with_products','with_sorting','cache'
    ];

    /** Прокладка под «исторический» ресурс продуктов */
    protected string $productCollectionClass = ProductCollection::class;

    public function __construct()
    {
        self::resources_init();
    }

    /**
     * Прегенерация кэша (как раньше в ProductController@cache).
     * Ничего не возвращает — только кладёт ключи в Cache.
     */
    public function cache(
        Request $request,
        ProductFilterService $legacyFilter,
        ProductQueryService  $legacyQuery
    ) {
        $useCachedTables = (bool) \Settings::get('dress.store.catalog_table_cache');

        // какие части прогреть
        $withFilterData  = filter_var($request->query('with_filter', false), FILTER_VALIDATE_BOOLEAN);
        $withFilterCount = filter_var($request->query('with_filter_count', false), FILTER_VALIDATE_BOOLEAN);
        $withProducts    = filter_var($request->query('with_products', false), FILTER_VALIDATE_BOOLEAN);
        $withSorting     = filter_var($request->query('with_sorting', false), FILTER_VALIDATE_BOOLEAN);

        if ($useCachedTables) {
            $filterService  = new CachedFilterService($request);
            $queryService   = new CachedQueryService($request);

        } else {
            $filterService  = $legacyFilter->setRequest($request)->setProductService(
                $legacyQuery->setRequest($request)->startQuery(true)
            );
            $queryService   = $legacyQuery->setRequest($request)->startQuery(true);
        }

        // filters.data
        if ($withFilterData) {
            $key = $this->cacheKey($request, 'filters-data');
            Cache::put($key, $filterService->getFiltersData(), now()->addMinutes(10));
        }
        // filters.count
        if ($withFilterCount) {
            $key = $this->cacheKey($request, 'filters-count');
            Cache::put($key, $filterService->getFiltersCount(), now()->addMinutes(10));
        }
        // sorting
        if ($withSorting) {
            $key = $this->cacheKey($request, 'sorting');
            Cache::put($key, $queryService->getSortingData(), now()->addMinutes(10));
        }
        // products
        if ($withProducts) {
            $key = $this->cacheKey($request, 'products');
            [$paginator, $items] = $queryService->getPaginated(); // paginator + коллекция моделей
            // упакуем как в ProductController
            $collectionClass = $this->productCollectionClass;
            $wrapped = new $collectionClass($paginator->setCollection($items));
            Cache::put($key, $wrapped, now()->addMinutes(10));
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Каталог (как ProductController@catalog) — вход/выход тот же.
     */
    public function catalog(
        Request $request,
        FilterService $filterService,
        QueryService  $queryService
    ) {
        $useCachedTables = (bool) \Settings::get('dress.store.catalog_table_cache');
        $response = [];

        $withFilterData  = $request->query('with_filter', []);
        $withFilterCount = $request->query('with_filter_count', []);
        $withProducts    = filter_var($request->query('with_products', false), FILTER_VALIDATE_BOOLEAN);
        $withSorting     = filter_var($request->query('with_sorting', false), FILTER_VALIDATE_BOOLEAN);
        $useCache        = filter_var($request->query('cache', false), FILTER_VALIDATE_BOOLEAN);

        // if ($useCachedTables) {
        //     dd(1);
        //     $filterService  = new CachedFilterService($request);
        //     $queryService   = new CachedQueryService($request);
        // } else {
        //     $filterService  = $legacyFilter->setRequest($request)->setProductService(
        //         $legacyQuery->setRequest($request)->startQuery(true)
        //     );
        //     $queryService   = $legacyQuery->setRequest($request)->startQuery(true);
        // }

        // filters.data
        if ($withFilterData) {
            $key = $this->cacheKey($request, 'filters-data');
            $response['filters']['data'] = $useCache && Cache::has($key)
                ? Cache::get($key)
                : $filterService->getFiltersData();
        }

        // filters.count
        if (!empty($withFilterCount)) {
            $key = $this->cacheKey($request, 'filters-count');
            $response['filters']['count'] = $useCache && Cache::has($key)
                ? Cache::get($key)
                : $filterService->getFiltersCount();
        }

        // sorting
        if ($withSorting) {
            $key = $this->cacheKey($request, 'sorting');
            $response['sorting'] = $useCache && Cache::has($key)
                ? Cache::get($key)
                : $queryService->getSortingData();
        }

        // products
        if ($withProducts) {
            $key = $this->cacheKey($request, 'products');
            if ($useCache && Cache::has($key)) {
                $response['products'] = Cache::get($key);
            } else {
                $response['products'] = $queryService->getProducts();
            }
        }

        return response()->json($response);
    }


    public function catalogProducts(Request $request) {
        $queryService = app(QueryService::class)->setRequest($request);
        $response = $queryService->getProducts(true);
        $p = \Backpack\Store\app\Http\Resources\ProductSimpleResource::collection($response);
        return $p;
    }

    /* ===== helpers ===== */

    /** Ключ кэша (тот же вход → тот же ключ). */
    protected function cacheKey(Request $request, string $suffix): string
    {
        $params = $this->filterParams($request);
        ksort($params);
        return 'catalog:'.$suffix.':'.md5(json_encode($params, JSON_UNESCAPED_UNICODE));
    }

    /** Извлекаем только «фильтровые» параметры (для стабильного cache-key). */
    protected function filterParams(Request $request): array
    {
        $query = $request->query();
        $map   = array_flip($this->nonFiltersExclude);
        $params = array_diff_key($query, $map);
        $context = \Store::context();
        $params['_country'] = $context->country ?? null;
        $params['_storefront'] = $context->storefront ?? null;
        $params['_locale'] = app()->getLocale();
        return $params;
    }

     
    /**
     * show
     * 
     * Get one product using it's slug 
     *
     * @param  mixed $request
     * @param  mixed $slug - Product slug
     * @return string JSON
     */
    public function show(Request $request, $slug) {

        $region = $request->input('country');
        $storefront = \Store::storefront();
        $visibleCategoryIds = \Backpack\Store\app\Models\Category::visibleIdsForContext($region, null, true);

        if (empty($visibleCategoryIds)) {
            abort(404);
        }

        $product = Catalog::query()
            ->where('slug', $slug)
            ->where('country_code', $region)
            ->where('storefront_code', $storefront)
            ->available()
            ->whereExists(function ($sub) use ($visibleCategoryIds) {
                $sub->selectRaw('1')
                    ->from('ak_category_product as cp')
                    ->whereIn('cp.category_id', $visibleCategoryIds)
                    ->where(function ($where) {
                        $where->whereColumn('cp.product_id', 'ak_catalog.product_id')
                            ->orWhereColumn('cp.product_id', 'ak_catalog.group_id');
                    });
            })
            ->firstOrFail();
        $availableRegions = Catalog::query()
            ->where('group_id', $product->group_id)
            ->where('storefront_code', $storefront)
            ->where('is_available', 1)
            ->pluck('country_code')
            ->map(function ($code) {
                return strtolower((string) $code);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
        if (empty($availableRegions) && $product->country_code) {
            $availableRegions = [strtolower((string) $product->country_code)];
        }

        $product->setAttribute('available_regions', $availableRegions);
        $product_resource = new self::$resources['product']['large']($product);
        return response()->json($product_resource);
    }
}
