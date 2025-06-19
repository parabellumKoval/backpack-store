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
  
  private function getSelections() {
    return [
      'with_sales' => [
        'id' => 'with_sales',
        'name' => __('backpack-store::filter.selections.with_sales'),
        'count' => 0
      ],
      'top_price' => [
        'id' => 'top_price',
        'name' => __('backpack-store::filter.selections.top_price'),
        'count' => 0
      ],
      'top_sales' => [
        'id' => 'top_sales',
        'name' => __('backpack-store::filter.selections.top_sales'),
        'count' => 0
      ],
      'with_rating' => [
        'id' => 'with_rating',
        'name' => __('backpack-store::filter.selections.with_rating'),
        'count' => 0
      ],
      'in_stock' => [
        'id' => 'in_stock',
        'name' => __('backpack-store::filter.selections.in_stock'),
        'count' => 0
      ]
    ];
  }
  
  /**
   * Method toFrontendFormat
   *
   * @param $data $data [explicite description]
   *
   * @return void
   */
  private function toFrontendFormat($data) {
    $filters = [];

    if(isset($data['selections'])) {
      $filters[] = [
        'id' => 'selections',
        'name' =>  __('backpack-store::filter.label.selections'),
        'si' => null,
        'isOpen' => true,
        'noSearch' => true,
        'isNarrowing' => true,
        'type' => 'checkbox',
        'values' => $data['selections']
      ];
    }

    if(isset($data['brands'])) {
      $filters[] = [
        'id' => 'brand',
        'name' => __('backpack-store::filter.label.brand'),
        'si' => null,
        'isOpen' => true,
        'noMeta' => false,
        'type' => 'brand',
        'values' => $data['brands']
      ];
    }
    
    if(isset($data['price'])) {
      $filters[] = [
        'id' => 'price',
        'name' => __('backpack-store::filter.label.price'),
        'si' => __('backpack-store::filter.label.grn'),
        'isOpen' => true,
        'type' => 'number',
        'values' => $data['price']
      ];
    }

    return $filters;
  } 

  /**
   * getQuery
   *
   * @param  mixed $isQuery
   * @param  mixed $includeAvailable
   * @return void
   */
  public function getQuery(Request $request, $isQuery = true, $where = 'and', $exclude = null) {

    // Array of category id and all offspring ids
    $node_ids = Category::getCategoryNodeIdList($request->input('category_slug'), $request->input('category_id'));
    
    // ak_attribute_product subquery
    $ap = $this->getAttributesQuery($request->input('attrs'), $where);

    if($isQuery) {
      $products = $this->product_class::query();
      // $products = \DB::table('ak_products');
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
      
      // joint with supplier  with in_stock > 0 and lowest price
      ->leftJoin(DB::raw('(
            SELECT 
                product_id,
                old_price,
                FIRST_VALUE(price) OVER (PARTITION BY product_id ORDER BY in_stock DESC, price ASC) as price,
                FIRST_VALUE(in_stock) OVER (PARTITION BY product_id ORDER BY in_stock DESC, price ASC) as in_stock
            FROM ak_supplier_product
        ) as sp'), 'ak_products.id', '=', 'sp.product_id')

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
      ->when($request->input('brands') && $exclude !== 'brand', function($query) use($request) {
        $query->leftJoin('ak_brands as brnd', 'ak_products.brand_id', '=', 'brnd.id');
        $query->whereIn('brnd.id', $request->input('brands'));
      })

      // only with sales
      ->when($this->is_with_sales, function($query) {
        // $query->where('ak_products.old_price', '>', 0);
        $query->where('sp.old_price', '>', 0);
      })

      // only in stock
      ->when($this->is_in_stock, function($query) use($isQuery) {
        // $query->where('ak_products.in_stock', '>', 0);
        if($isQuery) {
          $query->whereHas('sp', function($query) {
            $query->where('in_stock', '>', 0);
          });
        }else {
          $query->where('sp.in_stock', '>', 0);
        }
      })

      //Join reviews
      ->leftJoin('ak_reviews as r', function ($join) {
          $join->on('r.reviewable_id', '=', 'ak_products.id')
              ->where('r.reviewable_type', '=', 'Backpack\Store\app\Models\Product')
              ->where('r.is_moderated', '=', 1);
      })
      
      // only with rating 
      ->when($this->is_with_rating, function($query) {
        // $query->where('ak_products.rating', '!=', null);
        $query->whereExists(function($subquery) {
            $subquery->select(DB::raw(1))
                ->from('ak_reviews')
                ->whereColumn('ak_reviews.reviewable_id', 'ak_products.id')
                ->where('ak_reviews.reviewable_type', 'Backpack\Store\app\Models\Product')
                ->where('ak_reviews.is_moderated', 1);
        });
      })

      // only top sales 
      ->when($this->is_top_sales, function($query) {
        $query->rightJoin('ak_order_product as op', 'ak_products.id', '=', 'op.product_id');
        $query->havingRaw("SUM(op.amount) >= ?", [5]);
      })

      // only top price 
      ->when($this->is_top_price, function($query) {
        $query->whereRaw("(sp.old_price - sp.price) > sp.price / ?", [$this->top_price_sale_percent]);
        // $query->whereRaw("ak_products.old_price - ak_products.price > ak_products.price / ?", [$this->top_price_sale_percent]);
      })

      // Price filter
      ->when($request->input('price') && is_array($request->input('price'))  && $exclude !== 'price', function($query) use($request) {
        $query->whereBetween('sp.price', array_values($request->input('price')));
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
   * filters
   *
   * @param  mixed $request
   * @return void
   */
  public function filters(Request $request) {

    $products_query = $this->getQuery($request, false, 'or');
    $selections_count = $this->calculateSelectionsCount($products_query);
    
    // Get filters count
    $products_collection = $products_query
      ->select('ak_ap.*')
      ->join('ak_attribute_product as ak_ap', 'ak_products.id', '=', 'ak_ap.product_id')
      ->when($this->is_top_sales, function($query) {
        $query->groupBy('ak_ap.id');
      })
      ->get();

    $attributes_count = $this->attributesCount($products_collection);


    $products_query_for_brands = $this->getQuery($request, false, 'or', 'brand');
    $attributes_count['brand'] = $this->brandsCount($products_query_for_brands);

    $products_query_for_price = $this->getQuery($request, false, 'or', 'price');
    $attributes_count['price'] = $this->calculatePriceCount($products_query_for_price);

    $attributes_count['selections'] = $selections_count;

    return $attributes_count;
  }



  /**
   * Method catalog
   *
   * @param Request $request [explicite description]
   *
   * @return void
   */
  public function catalog(Request $request) {
    $data = [];
    $settings = $request->input('settings', ['selections', 'brands', 'prices', 'attributes']);

    $this->setSelections($request);
    $products_query = $this->getQuery($request, false);

    if(in_array('prices', $settings)) {
      $data['price'] = $this->calculatePriceCount($products_query);
    }

    if(in_array('selections', $settings)) {
      $selections = $this->getSelections();
      $selections_count =$this->calculateSelectionsCount($products_query);

      $selections_with_counts = array_map(function($item) use($selections_count) {
        if(isset($selections_count[$item['id']])) {
          $item['count'] = $selections_count[$item['id']];
        }

        return $item;
      }, $selections);

      $data['selections'] = $selections_with_counts;
    }

    if(in_array('brands', $settings)) {
      // $data['brands'] = $this->brandsCount($products_query);
      $data['brands'] = $this->brands($request);
    }
    
    //
    $response = $this->toFrontendFormat($data);

    return response()->json($response);
  }

  private function calculatePriceCount($products_query) {
    $query = (clone $products_query)
        ->select([
            DB::raw('MAX(sp.price) as max_price'),
            DB::raw('MIN(sp.price) as min_price'),
        ]);

    // Get result
    $result = $query->first();

    return [
      'min' => $result->min_price,
      'max' => $result->max_price,
    ];
  }

  // private function calculatePriceAndSelections($products_query) {
  //   $query = (clone $products_query)
  //       ->select([
  //           DB::raw('MAX(sp.price) as max_price'),
  //           DB::raw('MIN(sp.price) as min_price'),
  //           DB::raw('COUNT(DISTINCT CASE WHEN sp.old_price > 0 THEN ak_products.id END) as with_sales'),
  //           DB::raw('COUNT(DISTINCT CASE WHEN (sp.old_price - sp.price) > sp.price / ' . $this->top_price_sale_percent . ' THEN ak_products.id END) as top_price'),
  //           DB::raw('COUNT(DISTINCT CASE WHEN EXISTS (
  //               SELECT 1 FROM ak_order_product op 
  //               WHERE op.product_id = ak_products.id 
  //               GROUP BY op.product_id
  //               HAVING SUM(op.amount) >= 5
  //           ) THEN ak_products.id END) as top_sales'),
  //           DB::raw('COUNT(DISTINCT r.reviewable_id) as with_rating'),
  //           DB::raw('COUNT(DISTINCT CASE WHEN sp.in_stock > 0 THEN ak_products.id END) as in_stock')
  //       ]);

  //   // Get result
  //   $result = $query->first();

  //   return [
  //     'price' => [
  //       'min' => $result->min_price,
  //       'max' => $result->max_price,
  //     ],
  //     'selections' => [
  //       'with_sales' => (int)($result->with_sales ?? 0),
  //       'top_price' => (int)($result->top_price ?? 0),
  //       'top_sales' => (int)($result->top_sales ?? 0),
  //       'with_rating' => (int)($result->with_rating ?? 0),
  //       'in_stock' => (int)($result->in_stock ?? 0)
  //     ]
  //   ];
  // }
  
  private function calculateSelectionsCount($products_query) {
    // Add debugging to see raw SQL
    $query = (clone $products_query)
        ->select([
            DB::raw('COUNT(DISTINCT CASE WHEN sp.old_price > 0 THEN ak_products.id END) as with_sales'),
            DB::raw('COUNT(DISTINCT CASE WHEN (sp.old_price - sp.price) > sp.price / ' . $this->top_price_sale_percent . ' THEN ak_products.id END) as top_price'),
            DB::raw('COUNT(DISTINCT CASE WHEN EXISTS (
                SELECT 1 FROM ak_order_product op 
                WHERE op.product_id = ak_products.id 
                GROUP BY op.product_id
                HAVING SUM(op.amount) >= 5
            ) THEN ak_products.id END) as top_sales'),
            DB::raw('COUNT(DISTINCT r.reviewable_id) as with_rating'),
            DB::raw('COUNT(DISTINCT CASE WHEN sp.in_stock > 0 THEN ak_products.id END) as in_stock')
        ]);

    // Get result
    $result = $query->first();

    return [
        'with_sales' => (int)($result->with_sales ?? 0),
        'top_price' => (int)($result->top_price ?? 0),
        'top_sales' => (int)($result->top_sales ?? 0),
        'with_rating' => (int)($result->with_rating ?? 0),
        'in_stock' => (int)($result->in_stock ?? 0)
    ];
}

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
   * brands
   *
   * @return void
   */
  public function brands(Request $request, bool $json_response = true) {
    $sortBy = $request->input('sort_by', 'name');

    $this->setSelections($request);
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


  public function brandsCount($products_query) {
    $brands = (clone $products_query)
        ->select('ak_products.brand_id', DB::raw('COUNT(DISTINCT ak_products.id) as count'))
        ->whereNotNull('ak_products.brand_id')
        ->groupBy('ak_products.brand_id')
        ->pluck('count', 'brand_id')
        ->toArray();

    return $brands;
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
