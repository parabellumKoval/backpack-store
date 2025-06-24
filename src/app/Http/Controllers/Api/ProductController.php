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
   * index
   * 
   * Get collection of products. Filtering by category, attributes, search query is available.
   * Also you can setup per_page and ordering parametrs.
   *
   * @param Illuminate\Http\Request $request
  *      [
  *         "q" => (string) - Search query string makes searching by product name/short_name/code
  *         "per_page" => (int) - Items per each page
  *         "category_id" => (int) - Filters by category using category id
  *         "category_slug" => (string) - Filters by category using category slug
  *         "attrs" => (array) - Filters by attributes using array with this structure:
  *           [
  *              attr_id => (int) - Attribute id,
  *              attr_value_id ??? => (int) attribute_value_id for checkbox/radio,
  *              value ??? => (double) Strait value for numbers,
  *              from ??? => (double) range from value for numbers,
  *              to ??? => (double) range to value for numbers,
  *           ], 
  *           [...]
  *      ]
   * @return string JSON
   */
  public function index(Request $request, bool $json_response = true) {

    $order_by = $request->input('order_by', null);
    $order_dir = $request->input('order_dir', 'desc');

    $this->setSelections($request);

    // Get filters count meta
    $attributes_count = null;

    if($request->input('with_filters', true)) {
      $attributes_count = $this->filters($request);
    }

    // Make pagination
    $per_page = $request->input('per_page', config('backpack.store.per_page', 12));

    // Base query
    $products = $this->getQuery($request);
    
    // and ordering to query
    if($order_by) {
      if($order_by === 'in_stock') {
        if(config('backpack.store.supplier.enable', false)) 
        {
          $products = $products
            ->orderByRaw('IF(SUM(sp.in_stock) > ?, ?, ?) ' . $order_dir, [0, 1, 0])
            ->groupBy('ak_products.id');
        }
        else 
        {
          $products = $products
            ->orderByRaw('IF(ak_products.in_stock > ?, ?, ?) ' . $order_dir, [0, 1, 0]);
        }
      }
      elseif($order_by === 'sales')
      {
        $products = $products
          ->leftJoin('ak_order_product as op', 'ak_products.id', '=', 'op.product_id')
          ->orderByRaw('CASE WHEN COALESCE(SUM(sp.in_stock), 0) > 0 THEN 1 ELSE 0 END DESC')
          ->orderByRaw('SUM(op.amount) ' . $order_dir)
          ->groupBy('ak_products.id');
      }
      elseif($order_by === 'sale') 
      {
        // ATTANTION NOW price IN SUPPLIER_PRODUCT
        // At first with bigger sale
        $products = $products->orderByRaw('ak_products.old_price - ak_products.price ' . $order_dir);
      }
      else 
      {
        $products = $products
            ->orderByRaw('CASE WHEN COALESCE(SUM(sp.in_stock), 0) > 0 THEN 1 ELSE 0 END DESC')
            ->orderBy($order_by, $order_dir)
            ->groupBy('ak_products.id');
      }
    }
    else 
    { 
      $products = $products
        ->orderByRaw('CASE WHEN COALESCE(SUM(sp.in_stock), 0) > 0 THEN 1 ELSE 0 END DESC')
        // at first with images
        // ->orderBy('images', 'desc')
        // new at first
        ->orderBy('created_at', 'desc')
        ->groupBy('ak_products.id');
    }

    // Finish query
    $products = $products
      // Grouping for top sales
      ->when($this->is_top_sales, function($query) {
        $query->groupBy('ak_products.id');
      })
      // Pagination
      ->paginate($per_page);

    // Get values using collection resource (Resource configurates by backpack.store config)
    $products = new ProductCollection($products);

    if($json_response)
      return response()->json(['products' => $products, 'filters' => $attributes_count]);
    else
      return ['products' => $products, 'filters' => $attributes_count];
  }
    



  /**
   * Method catalog
   *
   * @param Request $request [explicite description]
   *
   * @return void
   */
  // public function catalog(Request $request) {
  //   $data = [];
  //   $settings = $request->input('settings', ['selections', 'brands', 'prices', 'attributes']);

  //   $this->setSelections($request);
  //   $products_query = $this->getQuery($request, false);

  //   // Prices
  //   if(in_array('prices', $settings)) {
  //     $data['price'] = $this->calculatePriceCount($products_query);
  //   }

  //   // Selections
  //   if(in_array('selections', $settings)) {
  //     $selections = $this->getSelections();
  //     $selections_count =$this->calculateSelectionsCount($products_query);

  //     $selections_with_counts = array_map(function($item) use($selections_count) {
  //       if(isset($selections_count[$item['id']])) {
  //         $item['count'] = $selections_count[$item['id']];
  //       }

  //       return $item;
  //     }, $selections);

  //     $data['selections'] = $selections_with_counts;
  //   }

  //   // Brands
  //   if(in_array('brands', $settings)) {
  //     // $data['brands'] = $this->brandsCount($products_query);
  //     $data['brands'] = $this->brands($request);
  //   }
    
  //   // Attributes
  //   if(in_array('attributes', $settings)) {
  //     $data['attributes'] = null;
  //   }

  //   //
  //   $response = $this->toFrontendFormat($data);

  //   return response()->json($response);
  // }


  /**
   * Method prices
   *
   * @param Request $request [explicite description]
   *
   * @return void
   */
  public function prices(Request $request) {

    $this->setSelections($request);

    $products_query = $this->getQuery($request, false);

    $prices = $products_query
      ->select(DB::raw('MAX(sp.price) as max_price'), DB::raw('MIN(sp.price) as min_price'))
      ->get()
      ->all();

    if($prices) {
      ['max_price' => $max_price, 'min_price' => $min_price] = (array)($prices[0]);
    }else {
      $max_price = 0;
      $min_price = 0;
    }

    return [
      'min' => $min_price,
      'max' => $max_price
    ];
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
