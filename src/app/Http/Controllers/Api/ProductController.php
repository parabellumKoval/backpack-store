<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Illuminate\Http\Request;

// use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Category;

// use Backpack\Store\app\Http\Resources\ProductSmallResource;
// use Backpack\Store\app\Http\Resources\ProductLargeResource;

class ProductController extends \App\Http\Controllers\Controller
{ 

  protected $product_class;
  protected $product_tiny_resource_class;
  protected $product_small_resource_class;
  protected $product_medium_resource_class;
  protected $product_large_resource_class;

  function __construct() {
    $this->product_tiny_resource_class = config('backpack.store.product_tiny_resource', 'Backpack\Store\app\Http\Resources\ProductTinyResource');
    $this->product_small_resource_class = config('backpack.store.product_small_resource', 'Backpack\Store\app\Http\Resources\ProductSmallResource');
    $this->product_medium_resource_class = config('backpack.store.product_medium_resource', 'Backpack\Store\app\Http\Resources\ProductMediumResource');
    $this->product_large_resource_class = config('backpack.store.product_large_resource', 'Backpack\Store\app\Http\Resources\ProductLargeResource');
    
    // Product model can be overwritten. For this you have to: 
    //  - create own Product Model,
    //  - extends it from Backpack\Store\app\Models\Product
    //  - set path to your Product Model in config "backpack.store.product.class"
    $this->product_class = config('backpack.store.product.class', 'Backpack\Store\app\Models\Product');
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
  *         "attr" => (string) - Filters by attributes using array with this structure:
  *           [
  *              attr_id (int) => attr_value (mixed) - attribute id as key, attribute value as value
  *           ]
  *      ]
   * @return string JSON
   */
  public function index(Request $request) {

    try{
      if(request('category_id')){
        $node_ids = Category::find(request('category_id'))->nodeIds;
      }elseif(request('category_slug')){
        $category = Category::where('slug', request('category_slug'))->firstOrFail();
        $node_ids = $category->nodeIds;
      }
    }catch(\Exception $e){
      $node_ids = isset($node_ids) && count($node_ids)? $node_ids: [];
    }finally {
      $node_ids = isset($node_ids) && count($node_ids)? $node_ids: [];
    }

    $products = $this->product_class::query()
              ->select('ak_products.*')
              // Getting only unique rows
              ->distinct('ak_products.id')
              // Getting only products that have not "parent_id" param
              ->base()
              // Getting only products that "is_active" param set to true
              ->active()
              
              // filtering by category if "category_id" or "category_slug" is presented in request
              ->when((request('category_id') || request('category_slug')), function($query) use($node_ids){
                $query->leftJoin('ak_category_product as cp', 'cp.product_id', '=', 'ak_products.id');
                $query->whereIn('cp.category_id', $node_ids);
              })

              // filtering by attributes if "attrs" is presented in request
              ->when(request('attrs'), function($query) {
                $attrs = request('attrs');
                
                $query->join('ak_attribute_product as ap', 'ap.product_id', '=', 'ak_products.id');

                foreach($attrs as $attr_id => $attr_value) {
                  $query->where('ap.attribute_id', $attr_id)
                        ->where('ap.value', 'like', '%' . $attr_value . '%');
                }
              })

              // filtering by search query if "q" is presented in request
              ->when(request('q'), function($query) {
                $query->where(\DB::raw('lower(ak_products.name)'), 'like', '%' . strtolower(request('q')) . '%')
                      ->orWhere(\DB::raw('lower(ak_products.short_name)'), 'like', '%' . strtolower(request('q')) . '%')
                      ->orWhere(\DB::raw('lower(ak_products.code)'), 'like', '%' . strtolower(request('q')) . '%');
              })

              // Setting order by 
              ->orderBy('created_at', 'desc');
                  
    
    $per_page = request('per_page', config('backpack.store.per_page', 12));
    
    $products = $products->paginate($per_page);

    // Get values using collection resource (Resource configurates by backpack.store config)
    $products = $this->product_small_resource_class::collection($products);

    return $products;
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
    $limit = request('limit') ?? 4;
    
    $products = $this->product_class::base()
                ->active()
                ->when(request('not_id'), function($query) {
                  $query->where('id', '!=', request('not_id'));
                })
                ->inRandomOrder()
                ->limit($limit)
                ->get();

    $products = $this->product_small_resource_class::collection($products);

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
    $product = $this->product_class::where('slug', $slug)->firstOrFail();
    $product_resource = new $this->product_large_resource_class($product);
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
    
    return response()->json($products); 
  }
}
