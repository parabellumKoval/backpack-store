<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

  function __construct() {

    self::resources_init();

    // Product model can be overwritten. For this you have to: 
    //  - create own Product Model,
    //  - extends it from Backpack\Store\app\Models\Product
    //  - set path to your Product Model in config "backpack.store.product.class"
    $this->product_class = config('backpack.store.product.class', 'Backpack\Store\app\Models\Product');
  }
    

  public function catalog(Request $request, ProductFilterService $filterService, ProductQueryService $productService) {
    $response = [];

    $filters_data = $request->input('with_filter', []);
    $filters_count = $request->input('with_filter_count', []);

    if(!empty($filters_data)){
      $response['filters']['data'] = $filterService
        ->getFiltersData();
    }

    if(!empty($filters_count)){
      $response['filters']['count'] = $filterService
        ->getFiltersCount();
    }

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
}
