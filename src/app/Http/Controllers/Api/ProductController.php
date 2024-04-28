<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\AttributeProduct;
use Backpack\Store\app\Http\Resources\ProductCollection;

class ProductController extends \App\Http\Controllers\Controller
{
  use \Backpack\Store\app\Traits\Resources;

  protected $product_class;
  protected $categories_node_ids = null;
  protected $attributes_query = null;

  function __construct() {
    self::resources_init();

    // Product model can be overwritten. For this you have to: 
    //  - create own Product Model,
    //  - extends it from Backpack\Store\app\Models\Product
    //  - set path to your Product Model in config "backpack.store.product.class"
    $this->product_class = config('backpack.store.product.class', 'Backpack\Store\app\Models\Product');
  }
    
  /**
   * getQuery
   *
   * @param  mixed $isQuery
   * @param  mixed $includeAvailable
   * @return void
   */
  public function getQuery($isQuery = true) {

    // Array of category id and all offspring ids
    $this->categories_node_ids = Category::getCategoryNodeIdList(request('category_slug'), request('category_id'));
    
    // ak_attribute_product subquery
    $this->attributes_query = $this->getAttributesQuery(request('attrs'));

    $node_ids = $this->categories_node_ids;

    $ap = $this->attributes_query;

    if($isQuery) {
      $products = $this->product_class::query();
    }else {
      $products = \DB::table('ak_products');
    }

    $products = $products
      ->selectRaw('ak_products.*')
      // Getting only unique rows
      // ->distinct('ak_products.id')
      // Getting only products that have not "parent_id" param
      // ->base()
      // Getting only products that "is_active" param set to true
      ->where('ak_products.is_active', 1)
      
      // filtering by category if "category_id" or "category_slug" is presented in request
      ->when($node_ids, function($query) use($node_ids){
        $query->leftJoin('ak_category_product as cp', 'cp.product_id', '=', 'ak_products.id');
        $query->whereIn('cp.category_id', $node_ids);
      })

      // filtering by attributes if "attrs" is presented in request
      ->when((request('attrs') && !empty($ap)), function($query) use($ap) {
        $query->rightJoinSub($ap, 'ap', function ($join) {
            $join->on('ap.product_id', '=', 'ak_products.id');
        });
      })

      // filtering by brand
      ->when(request('brand_slug'), function($query) {
        $query->leftJoin('ak_brands as br', 'ak_products.brand_id', '=', 'br.id');
        $query->where('br.slug', request('brand_slug'));
      })

      // filtering by search query if "q" is presented in request
      ->when(request('q'), function($query) {
        $query->where(\DB::raw('lower(ak_products.name)'), 'like', '%' . strtolower(request('q')) . '%')
              ->orWhere(\DB::raw('lower(ak_products.short_name)'), 'like', '%' . strtolower(request('q')) . '%')
              ->orWhere(\DB::raw('lower(ak_products.code)'), 'like', '%' . strtolower(request('q')) . '%');
      });

    return $products;
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
  public function index(Request $request) {

    // $start = microtime(true);
    // dd(microtime(true) - $start);

    // Get filters count meta
    $attributes_count = null;

    if(request('with_filters', true)) {
      $attributes_count = $this->filters();
    }

    // Make pagination
    $per_page = request('per_page', config('backpack.store.per_page', 12));

    $products = $this->getQuery()
      ->selectRaw('(ak_products.price - ak_products.old_price) as sale, IF(ak_products.in_stock > ?, ?, ?) as available', [0, 1, 0])
      // at first in_stock > 0
      ->orderBy('available', 'desc')
      // at first with images
      ->orderBy('images', 'desc')
      // At first with bigger sale
      ->orderBy('sale', 'asc')
      // Setting order by
      ->orderBy(request('order_by', 'created_at'), request('order_dir', 'desc'))
      ->paginate($per_page);

    // Get values using collection resource (Resource configurates by backpack.store config)
    $products = new ProductCollection($products);

    return response()->json(['products' => $products, 'filters' => $attributes_count]);
  }
    
  /**
   * filters
   *
   * @param  mixed $request
   * @return void
   */
  public function filters() {

    $products_query = $this->getQuery(false);

    // Get prices
    $prices = $products_query
      ->select(DB::raw('MAX(price) as max_price'), DB::raw('MIN(price) as min_price'))
      ->get()
      ->all();

    ['max_price' => $max_price, 'min_price' => $min_price] = (array)($prices[0]);
    
    // Get filters count
    $products_collection = $products_query
      ->select('ap.*')
      ->join('ak_attribute_product as ap', 'ak_products.id', '=', 'ap.product_id')
      ->get();
    
    $attributes_count = $this->attributesCount($products_collection);

    $attributes_count['price'] = [
      'min' => $min_price,
      'max' => $max_price
    ];

    return $attributes_count;
  }

  /**
   * getAttributesQuery
   *
   * @return void
   */
  public function getAttributesQuery($attrs) {
    if(!$attrs) return;

    $ap = DB::table('ak_attribute_product as ap')
                   ->selectRaw('ap.product_id, COUNT(DISTINCT id) as grouped_count');

    foreach($attrs as $index => $attr) {
      
      $whereFunction = $index === 0? 'where': 'orWhere';

      $ap->{$whereFunction}(function($query) use($attr) {
        $query->where('ap.attribute_id', $attr['attr_id'])
              ->when(
                (isset($attr['from']) && isset($attr['to'])), 
                function($query) use($attr) {
                  $query->where('ap.value', '>=', $attr['from'])
                        ->where('ap.value', '<=', $attr['to']);
                }
              )
              ->when((isset($attr['value']) && !empty($attr['value'])), function($query) use($attr) {
                $query->where('ap.value', $attr['value']);
              })
              ->when((isset($attr['attr_value_id']) && !empty($attr['attr_value_id'])), function($query) use($attr) {
                if(is_array($attr['attr_value_id'])) {
                  $query->whereIn('ap.attribute_value_id', $attr['attr_value_id']);
                }else {
                  $query->where('ap.attribute_value_id', $attr['attr_value_id']);
                }
              });
      });
    }
    
    $ap->groupBy('product_id');
    $ap->havingRaw("grouped_count = ?", [count($attrs)]);

    return $ap;
  }
  
  public function attributesCount($attributes) {
    $uniq_attrs = [];

    for($a = 0; $a < $attributes->count(); $a++) {
      $attr = $attributes[$a];
      $attr_id = $attr->attribute_id;
      $attr_value_id = $attr->attribute_value_id;
      $attr_value = $attr->value;

      if(!isset($uniq_attrs[$attr_id])){
        $uniq_attrs[$attr_id] = [];
      }

      // If attribute type is checkbox or radio 
      if($attr_value_id !== null) {
        if(!isset($uniq_attrs[$attr_id][$attr_value_id])){
          $uniq_attrs[$attr_id][$attr_value_id] = 0;
        }

        $uniq_attrs[$attr_id][$attr_value_id] += 1;
      }

      // If attribute type is number
      if($attr_value !== null) {
        if(!isset($uniq_attrs[$attr_id]['min']) && !isset($uniq_attrs[$attr_id]['max'])){
          $uniq_attrs[$attr_id]['min'] = $uniq_attrs[$attr_id]['max'] = $attr_value;
        }

        // renew max limit
        if($attr_value > $uniq_attrs[$attr_id]['max']) {
          $uniq_attrs[$attr_id]['max'] = $attr_value;
        }

        // renew min limit
        if($attr_value < $uniq_attrs[$attr_id]['min']) {
          $uniq_attrs[$attr_id]['min'] = $attr_value;
        }
      }

    }

    return $uniq_attrs;
  }

  /**
   * filterValuesCount
   *
   * @param  mixed $products
   * @return void
   */
  public function filterValuesCount($products){
    
    //define empty array
    $uniq_attrs = [
      'price' => [
        'min' => null,
        'max' => null
      ]
    ];
    
    // for each product
    for($p = 0; $p < $products->count(); $p++){

      // Set initial price
      if($uniq_attrs['price']['min'] === null || $uniq_attrs['price']['max'] === null) {
        $uniq_attrs['price']['min'] = $uniq_attrs['price']['max'] = $products[$p]->price;
      }

      // Set lower price limit
      if($products[$p]->price < $uniq_attrs['price']['min']) {
        $uniq_attrs['price']['min'] = $products[$p]->price;
      }

      // Set upper price limit
      if($products[$p]->price > $uniq_attrs['price']['max']) {
        $uniq_attrs['price']['max'] = $products[$p]->price;
      }

      $attributes = $products[$p]->ap;

      for($a = 0; $a < $attributes->count(); $a++) {
        $attr = $attributes[$a];
        $attr_id = $attr->attribute_id;
        $attr_value_id = $attr->attribute_value_id;
        $attr_value = $attr->value;

        if(!isset($uniq_attrs[$attr_id])){
          $uniq_attrs[$attr_id] = [];
        }

        // If attribute type is checkbox or radio 
        if($attr_value_id !== null) {
          if(!isset($uniq_attrs[$attr_id][$attr_value_id])){
            $uniq_attrs[$attr_id][$attr_value_id] = 0;
          }

          $uniq_attrs[$attr_id][$attr_value_id] += 1;
        }

        // If attribute type is number
        if($attr_value !== null) {
          if(!isset($uniq_attrs[$attr_id]['min']) && !isset($uniq_attrs[$attr_id]['max'])){
            $uniq_attrs[$attr_id]['min'] = $uniq_attrs[$attr_id]['max'] = $attr_value;
          }

          // renew max limit
          if($attr_value > $uniq_attrs[$attr_id]['max']) {
            $uniq_attrs[$attr_id]['max'] = $attr_value;
          }

          // renew min limit
          if($attr_value < $uniq_attrs[$attr_id]['min']) {
            $uniq_attrs[$attr_id]['min'] = $attr_value;
          }
        }

      }
    }

  return $uniq_attrs;
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
    $product = $this->product_class::where('slug', $slug)->firstOrFail();
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
    
    return self::$resources['product']['large']::collection($products); 
  }
}
