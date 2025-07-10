<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

use Backpack\Store\app\Models\Catalog;
use Backpack\Store\app\Models\Brand;
use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\AttributeProduct;
use Backpack\Store\app\Http\Resources\ProductCollection;

use Backpack\Store\app\Services\ProductFilterService;
use Backpack\Store\app\Services\ProductQueryService;

class ProductController extends \App\Http\Controllers\Controller
{
  use \Backpack\Store\app\Traits\Resources;

  protected $product_class;

  protected $is_with_sales = false;
  protected $is_top_price = false;
  protected $is_top_sales = false;
  protected $is_with_rating = false;
  protected $is_in_stock = false;

  protected $top_price_sale_percent = 10;
  
  protected $filterService;
  protected $productService;

  protected $filters_data_exclude = ['with_filter_count', 'with_products', 'with_sorting', 'page', 'per_page', 'order_by', 'order_dir', 'cache'];
  protected $filters_count_exclude = ['with_filter', 'with_products', 'with_sorting', 'page', 'per_page', 'order_by', 'order_dir', 'cache'];
  protected $products_exclude = ['with_filter', 'with_filter_count', 'with_products', 'with_sorting', 'cache'];

  protected $non_filters_exclude = ['page', 'per_page', 'order_by', 'order_dir', 'category_slug', 'brand_slug', 'with_filter', 'with_filter_count', 'with_products', 'with_sorting', 'cache'];

  function __construct() {

    self::resources_init();

    // Product model can be overwritten. For this you have to: 
    //  - create own Product Model,
    //  - extends it from Backpack\Store\app\Models\Product
    //  - set path to your Product Model in config "backpack.store.product.class"
    $this->product_class = config('backpack.store.product.class', 'Backpack\Store\app\Models\Product');
  }
  

  public function cache(Request $request, ProductFilterService $filterService, ProductQueryService $productService) {
    $with_filters_data = $request->input('with_filter', []);
    $with_filters_count = $request->input('with_filter_count', []);
    $with_products = $request->input('with_products', false);

    // Filters data
    if(!empty($with_filters_data)) {
      $specCacheKey = $this->getCacheKey($request, 'filters-data', $this->filters_data_exclude);
      $data = $filterService->getFiltersData();
      Cache::put($specCacheKey, $data);
    }

    // Filters count
    if(!empty($with_filters_count)) {
      $specCacheKey = $this->getCacheKey($request, 'filters-count', $this->filters_count_exclude);
      $data = $filterService->getFiltersCount();
      Cache::put($specCacheKey, $data);
    }

    // Products
    if($with_products) {
      $specCacheKey = $this->getCacheKey($request, 'products', $this->products_exclude);
      $data = $productService
        ->startQuery(true)
        ->filterByCategories()
        ->filterByBrandSlug()
        ->filterByBrands()
        ->filterByPrice()
        ->filterByAttributes()
        ->filterBySelections()
        ->filterBySearch()
        ->sorting()
        ->getProducts();
      Cache::put($specCacheKey, $data);
    }
  }
  /**
   * Method catalog
   *
   * @param Request $request [explicite description]
   * @param ProductFilterService $filterService [explicite description]
   * @param ProductQueryService $productService [explicite description]
   *
   * @return void
   */
  public function catalog(Request $request, ProductFilterService $filterService, ProductQueryService $productService) {
    $response = [];

    $order_by = $request->input('order_by', null);
    $order_dir = $request->input('order_dir', 'desc');

    $with_filters_data = $request->input('with_filter', []);
    $with_filters_count = $request->input('with_filter_count', []);
    $with_products = $request->input('with_products', true);

    $sorting_data = $request->input('with_sorting', false);
    $cache = $request->input('cache', []);

    $filter_params = $this->getFilterParams($request);

    // Filters data
    if(!empty($with_filters_data)){
      $specCacheKey = $this->getCacheKey($request, 'filters-data', $this->filters_data_exclude);

      if(Cache::has($specCacheKey) && in_array('with_filter', $cache)) {
        $response['filters']['data'] = Cache::get($specCacheKey);
      }else {
        $response['filters']['data'] = $filterService->getFiltersData();
        if(in_array('with_filter', $cache)) Cache::put($specCacheKey, $response['filters']['data']);
      }
    }

    // Filters count
    if(!empty($with_filters_count)){
      $specCacheKey = $this->getCacheKey($request, 'filters-count', $this->filters_count_exclude);

      if(Cache::has($specCacheKey) && in_array('with_filter_count', $cache) && empty($filter_params)) {
        $response['filters']['count'] = Cache::get($specCacheKey);
      }else {
        $response['filters']['count'] = $filterService->getFiltersCount();
        if(in_array('with_filter_count', $cache) && empty($filter_params)) Cache::put($specCacheKey, $response['filters']['count']);
      }
    }

    // Sorting
    if($sorting_data){
      $response['sorting'] = Catalog::getSortingDataWithActive($order_by, $order_dir);
    }

    // Products
    if($with_products) {
      $specCacheKey = $this->getCacheKey($request, 'products', $this->products_exclude);

      // dd($specCacheKey);
      if(Cache::has($specCacheKey) && in_array('with_products', $cache)){
        $response['products'] = Cache::get($specCacheKey);
      }else {
        $response['products'] = $productService
          ->startQuery(true)
          ->filterByCategories()
          ->filterByBrandSlug()
          ->filterByBrands()
          ->filterByPrice()
          ->filterByAttributes()
          ->filterBySelections()
          ->filterBySearch()
          ->sorting()
          ->getProducts();

        if(in_array('with_products', $cache)) Cache::put($specCacheKey, $response['products']);
      }
    }

    return response()->json($response);
  }
  
  /**
   * Method index
   *
   * @param Request $request [explicite description]
   * @param ProductQueryService $productService [explicite description]
   *
   * @return void
   */
  public function index(Request $request, ProductQueryService $productService) {
    $response = $productService
      ->startQuery(true, $request)
      ->filterByCategories()
      ->filterByBrandSlug()
      ->filterByBrands()
      ->filterByPrice()
      ->filterByAttributes()
      ->filterBySelections()
      ->filterBySearch()
      ->sorting()
      ->getProducts();

    return $response;
  }


  /**
   * random
   * 
   * Get random products
   *
   * @param  mixed $request
   * @return void
   */
  public function random(Request $request) {
    $limit = $request->input('limit') ?? 4;
    
    $products = $this->product_class::base()
                ->active()
                ->when($request->input('not_id'), function($query) use($request) {
                  $query->where('id', '!=', $request->input('not_id'));
                })
                ->inRandomOrder()
                ->limit($limit)
                ->get();

    $products = self::$resources['product']['small']::collection($products);

    return $products;
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
    $product = $this->product_class::where('slug', $slug)->where('is_active', 1)->firstOrFail();
    $product_resource = new self::$resources['product']['large']($product);
    return response()->json($product_resource);
  }
  
  /**
   * getByIds
   * 
   * Get products using array of their ids
   *
   * @param  mixed $request
   *    [
   *      ids => int[] - array of product ids
   *    ]
   * @return void
   */
  public function getByIds(Request $request){
    
    if(empty($request->ids))
      return response()->json(['products' => []]);
      
    $products = $this->product_class::whereIn('id', $request->ids)->get();
    
    $collection = self::$resources['product']['large']::collection($products); 

    return $collection;
  }

    
  
  /**
   * Method getCacheKey
   *
   * @param Request $request [explicite description]
   *
   * @return void
   */
  private function getCacheKey(Request $request, String $prefix = '', Array $exclude_keys = []) {
    $queryParams = $request->query();
    $exclude_map = array_flip($exclude_keys);
    $filtered_params = array_diff_key($queryParams, $exclude_map);
    ksort($filtered_params);
    $cacheKey = http_build_query($filtered_params);

    return !empty($prefix)? $prefix . '-' . $cacheKey: $cacheKey;
  }

  
  /**
   * Method getFilterParams
   *
   * @param Request $request [explicite description]
   *
   * @return void
   */
  private function getFilterParams(Request $request) {
    $queryParams = $request->query();
    $exclude_keys = $this->non_filters_exclude;
    $exclude_map = array_flip($exclude_keys);
    $filtered_params = array_diff_key($queryParams, $exclude_map);

    return $filtered_params;
  }
}
