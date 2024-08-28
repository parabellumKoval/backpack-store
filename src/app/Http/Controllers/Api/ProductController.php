<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Backpack\Store\app\Models\Brand;
use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\AttributeProduct;
use Backpack\Store\app\Http\Resources\ProductCollection;

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

  function __construct() {
    self::resources_init();

    // Product model can be overwritten. For this you have to: 
    //  - create own Product Model,
    //  - extends it from Backpack\Store\app\Models\Product
    //  - set path to your Product Model in config "backpack.store.product.class"
    $this->product_class = config('backpack.store.product.class', 'Backpack\Store\app\Models\Product');
  }
    
  
  /**
   * setSelections
   *
   * @return void
   */
  public function setSelections(Request $request) {
    if($request->input('selections') && is_array($request->input('selections'))) {
      $this->is_with_sales = in_array('with_sales', $request->input('selections'));
      $this->is_top_price = in_array('top_price', $request->input('selections'));
      $this->is_top_sales = in_array('top_sales', $request->input('selections'));
      $this->is_with_rating = in_array('with_rating', $request->input('selections'));
      $this->is_in_stock = in_array('in_stock', $request->input('selections'));
    }
  }
    
  /**
   * category
   *Request $request
   * @param  mixed $request
   * @param  mixed $slug
   * @return void
   */
  public function category(Request $request) {

    $fake_request = new \Illuminate\Http\Request();
    $fake_request->replace(['category_slug' => $request->input('category_slug')]);

    // First page products and all filters meta
    $products_page_1 = $this->index($fake_request, false);

    // Brands
    $brands = $this->index($fake_request, false);

    // Category
    $category_controller = new \Backpack\Store\app\Http\Controllers\Api\CategoryController;
    $category = $category_controller->show($fake_request, $request->input('category_slug'));

    // Attributes
    $attributes_controller = new \Backpack\Store\app\Http\Controllers\Api\AttributeController;
    $attributes = $attributes_controller->index($fake_request, false);


    return response()->json([
      'products' => $products_page_1['products'] ?? null,
      'filters' => $products_page_1['filters'] ?? null,
      'brands' => $brands,
      'category' => $category,
      'attributes' => $attributes
    ]);
  }


  /**
   * getQuery
   *
   * @param  mixed $isQuery
   * @param  mixed $includeAvailable
   * @return void
   */
  public function getQuery(Request $request, $isQuery = true, $where = 'and') {

    // Array of category id and all offspring ids
    $node_ids = Category::getCategoryNodeIdList($request->input('category_slug'), $request->input('category_id'));
    
    // ak_attribute_product subquery
    $ap = $this->getAttributesQuery($request->input('attrs'), $where);

    if($isQuery) {
      $products = $this->product_class::query();
    }else {
      $products = \DB::table('ak_products');
    }

    $products = $products
      ->selectRaw('ak_products.*')
      // Getting only unique rows
      ->distinct('ak_products.id')
      // Getting only products that have not "parent_id" param
      ->when(config('backpack.store.product.modifications.show_only_base_product_in_catalog', false), function($query) {
        $query->whereNull('ak_products.parent_id');
      })
      // Getting only products that "is_active" param set to true
      ->where('ak_products.is_active', 1)
      
      // filtering by category if "category_id" or "category_slug" is presented in request
      ->when($node_ids, function($query) use($node_ids){
        $query->leftJoin('ak_category_product as cp', 'cp.product_id', '=', 'ak_products.id');
        $query->whereIn('cp.category_id', $node_ids);
      })

      // filtering by attributes if "attrs" is presented in request
      ->when(($request->input('attrs') && !empty($ap)), function($query) use($ap, $request) {
        $query->rightJoinSub($ap, 'ap', function ($join) {
            $join->on('ap.product_id', '=', 'ak_products.id');
        });
      })

      // filtering by brand
      ->when($request->input('brand_slug'), function($query) use($request) {
        $query->leftJoin('ak_brands as br', 'ak_products.brand_id', '=', 'br.id');
        $query->where('br.slug', $request->input('brand_slug'));
      })

      // filtering by brands id's list
      ->when($request->input('brands'), function($query) use($request) {
        $query->leftJoin('ak_brands as brnd', 'ak_products.brand_id', '=', 'brnd.id');
        $query->whereIn('brnd.id', $request->input('brands'));
      })

      // only with sales 
      ->when($this->is_with_sales, function($query) {
        $query->where('ak_products.old_price', '>', 0);
      })

      // only in stock
      ->when($this->is_in_stock, function($query) {
        $query->where('ak_products.in_stock', '>', 0);
      })

      // only with rating 
      ->when($this->is_with_rating, function($query) {
        $query->where('ak_products.rating', '!=', null);
      })

      // only top sales 
      ->when($this->is_top_sales, function($query) {
        $query->rightJoin('ak_order_product as op', 'ak_products.id', '=', 'op.product_id');
        $query->havingRaw("SUM(op.amount) >= ?", [5]);
      })

      // only top price 
      ->when($this->is_top_price, function($query) {
        // $query->havingRaw("(ak_products.price - ak_products.old_price) <= ?", [90000]);
        $query->whereRaw("ak_products.old_price - ak_products.price > ak_products.price / ?", [$this->top_price_sale_percent]);
      })

      // Price filter
      ->when($request->input('price') && is_array($request->input('price')), function($query) use($request) {
        $query->whereBetween('price', array_values($request->input('price')));
      })

      // filtering by search query if "q" is presented in request
      ->when($request->input('q'), function($query) use($request) {
        $query->where(\DB::raw('lower(ak_products.name)'), 'like', '%' . strtolower($request->input('q')) . '%')
              ->orWhere(\DB::raw('lower(ak_products.short_name)'), 'like', '%' . strtolower($request->input('q')) . '%')
              ->orWhere(\DB::raw('lower(ak_products.code)'), 'like', '%' . strtolower($request->input('q')) . '%');
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
      if($order_by === 'sales')
      {
        $products = $products
          ->leftJoin('ak_order_product as op', 'ak_products.id', '=', 'op.product_id')
          ->orderByRaw('SUM(op.amount) ' . $order_dir)
          ->groupBy('ak_products.id');
      }
      elseif($order_by === 'sale') 
      {
        // At first with bigger sale
        $products = $products->orderByRaw('ak_products.old_price - ak_products.price ' . $order_dir);
      }
      else 
      {
        $products = $products->orderBy($order_by, $order_dir);
      }
    }else {
      // at first in_stock > 0
      if(config('backpack.store.supplier.enable', false)) {
        $products = $products
          ->leftJoin('ak_supplier_product as sp', 'ak_products.id', '=', 'sp.product_id')
          ->orderByRaw('IF(SUM(sp.in_stock) > ?, ?, ?) DESC', [0, 1, 0])
          ->groupBy('ak_products.id');
      }else {
        $products = $products
          ->orderByRaw('IF(ak_products.in_stock > ?, ?, ?) DESC', [0, 1, 0]);
      }

      $products = $products
        // at first with images
        ->orderBy('images', 'desc')
        // new at first
        ->orderBy('created_at', 'desc');
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
   * filters
   *
   * @param  mixed $request
   * @return void
   */
  public function filters(Request $request) {

    $products_query = $this->getQuery($request, false, 'or');

    // Get prices
    $prices = $products_query
      ->select(DB::raw('MAX(price) as max_price'), DB::raw('MIN(price) as min_price'))
      ->get()
      ->all();

    if($prices) {
      ['max_price' => $max_price, 'min_price' => $min_price] = (array)($prices[0]);
    }else {
      $max_price = 0;
      $min_price = 0;
    }
    
    // Get filters count
    $products_collection = $products_query
      ->select('ak_ap.*')
      ->join('ak_attribute_product as ak_ap', 'ak_products.id', '=', 'ak_ap.product_id')
      ->when($this->is_top_sales, function($query) {
        $query->groupBy('ak_ap.id');
      })
      ->get();

    $attributes_count = $this->attributesCount($products_collection);

    $attributes_count['price'] = [
      'min' => $min_price,
      'max' => $max_price
    ];

    return $attributes_count;
  }
  

  /**
   * brands
   *
   * @return void
   */
  public function brands(Request $request, bool $json_response = true) {
    $sortBy = $request->input('sort_by', 'name');

    $products_query = $this->getQuery($request, false);
    $fields_array = [];

    if($request->input('only_meta')) {
      $fields_array = ['br.id', DB::raw('COUNT(br.id) as count')];
    }else {
      $fields_array = ['br.id', 'br.name', 'br.slug', 'br.images', DB::raw('COUNT(br.id) as count')];
    }

    // Get filters count
    $brands_collection = $products_query
      ->select($fields_array)
      ->join('ak_brands as br', 'ak_products.brand_id', '=', 'br.id')
      ->groupBy('br.id')
      ->get();
    
    if(!$brands_collection->count()) {
      return [];
    }

    // Convert array to collection
    $brands = Brand::hydrate($brands_collection->sortBy($sortBy)->all());

    return $request->input('only_meta')?
            self::$resources['brand']['filter_tiny']::collection($brands):
            self::$resources['brand']['filter']::collection($brands);
  }
  
  /**
   * prepareAttributes
   *
   * @param  mixed $values
   * @return void
   */
  private function prepareAttributes($data) {
    $attrs = [];
    $values = array_values($data);

    for($i = 0; $i < count($values); $i++) {
      $attr = $values[$i];

      // if attribute is not isset yet
      if(!isset($attrs[$attr['attr_id']])) {
        
        // if attribute type is number (range)
        if(isset($attr['from']) && isset($attr['to'])){
          $attrs[$attr['attr_id']] = [
            'attr_id' => (int)$attr['attr_id'],
            'to' => floatval($attr['to']),
            'from' => floatval($attr['from']),
          ];
        }
        // if attribute type is checkbox / radio
        elseif(isset($attr['attr_value_id'])) {
          $attrs[$attr['attr_id']] = [
            'attr_id' => (int)$attr['attr_id'],
            'attr_value_id' => [(int)$attr['attr_value_id']]
          ];
        
        }
        // if attribute type is number (strict)
        else {
          $attrs[$attr['attr_id']] = [
            'attr_id' => (int)$attr['attr_id'],
            'value' => floatval($attr['value']),
          ];
        }
      }
      // addding values to array
      else {
        if(isset($attr['attr_value_id'])) {
          $attrs[$attr['attr_id']]['attr_value_id'][] = (int)$attr['attr_value_id'];
        }else {
          // multiple values allowed only for checkbox / radio
          continue;
        }
      }
    }

    return array_values($attrs);
  }

  /**
   * getAttributesQuery
   *
   * @return void
   */
  public function getAttributesQuery($values, $where = "and") {
    if(!$values) return;

    $attrs = $this->prepareAttributes($values);
    $attrs_count = count($attrs);

    $ap = DB::table('ak_attribute_product as ap')
                   ->selectRaw('ap.product_id, COUNT(DISTINCT id) as grouped_count');

    foreach($attrs as $index => $attr) {
      
      $whereFunction = $index === 0? 'where': 'orWhere';

      $ap->{$whereFunction}(function($query) use($attr) {
        $query->where('ap.attribute_id', $attr['attr_id'])
              ->when((isset($attr['from']) && isset($attr['to'])), function($query) use($attr) {
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
    $ap->when($where === 'and', function($query) use($attrs_count) {
      $query->havingRaw("grouped_count = ?", [$attrs_count]);
    });

    return $ap;
  }
    
  /**
   * attributesCount
   *
   * @param  mixed $attributes
   * @return void
   */
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
    
    $collection = self::$resources['product']['large']::collection($products); 

    return $collection;
  }
}
