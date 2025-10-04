<?php
namespace Backpack\Store\app\Services\Catalog;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Backpack\Store\app\Models\Category;

use Backpack\Store\app\Http\Resources\ProductCollection;

use Backpack\Store\app\Services\Catalog\AbstractQueryService;

class ProductQueryService extends AbstractQueryService
{
  use \Backpack\Store\app\Traits\Resources;

  protected $query;
  protected Request $request;

  protected $top_price_sale_percent = 10;
  protected $top_sales_count = 3;

  protected $product_class;
  
  public function __construct(Request $request)
  {

    self::resources_init();

    // Product model can be overwritten. For this you have to: 
    //  - create own Product Model,
    //  - extends it from Backpack\Store\app\Models\Product
    //  - set path to your Product Model in config "backpack.store.product.class"
    $this->product_class = \Settings::get('dress.product.model', 'Backpack\Store\app\Models\Product');


    $this->request = $request;
  }
    
  /**
   * Method startQuery
   *
   * @return self
   */
  public function startQuery($use_model = false, Request $request = null): self {

    if($request) {
      $this->request = $request;
    }

    if($use_model) {
      $start = $this->product_class::query();
    }else {
      $start = DB::table('ak_products');
    }

    $this->query = $start
      ->when(\Settings::get('dress.product.modifications.show_only_base_product_in_catalog', false), function($query) {
        $query->whereNull('ak_products.parent_id');
      })

      // Getting only products that "is_active" param set to true
      ->where('ak_products.is_active', 1)
      
      // joint with supplier  with in_stock > 0 and lowest price
      ->leftJoin(DB::raw('(
          SELECT 
              product_id,
              old_price,
              FIRST_VALUE(price) OVER (PARTITION BY product_id ORDER BY 
                  CASE WHEN in_stock > 0 THEN 1 ELSE 0 END DESC,
                  price ASC
              ) as price,
              FIRST_VALUE(in_stock) OVER (PARTITION BY product_id ORDER BY 
                  CASE WHEN in_stock > 0 THEN 1 ELSE 0 END DESC,
                  price ASC
              ) as in_stock
          FROM ak_supplier_product
      ) as sp'), 'ak_products.id', '=', 'sp.product_id');
    
    return $this;
  }

  
  /**
   * Method filterBySelections
   *
   * @return self
   */
  public function filterBySelections(): self
  {
    // only with sales
    if(in_array('with_sales', $this->request->input('selections', []))) {
      $this->query->where('sp.old_price', '>', 0);
    }

    // only in stock
    if(in_array('in_stock', $this->request->input('selections', []))) {
      $this->query->where('sp.in_stock', '>', 0);
    }
    
    // only with rating 
    if(in_array('with_rating', $this->request->input('selections', []))) {
      $this->query->whereExists(function($subquery) {
          $subquery->select(DB::raw(1))
              ->from('ak_reviews')
              ->whereColumn('ak_reviews.reviewable_id', 'ak_products.id')
              ->where('ak_reviews.reviewable_type', 'Backpack\Store\app\Models\Product')
              ->where('ak_reviews.is_moderated', 1);
      });
    }

    // only top sales 
    if(in_array('top_sales', $this->request->input('selections', []))) {
      $this->query->rightJoin('ak_order_product as op', 'ak_products.id', '=', 'op.product_id')
                  ->havingRaw("SUM(op.amount) >= ?", [$this->top_sales_count]);
    }

    // only top price 
    if(in_array('top_price', $this->request->input('selections', []))) {
      $this->query->whereRaw("(sp.old_price - sp.price) > sp.price / ?", [$this->top_price_sale_percent]);
    }

    return $this;
  }
  
  /**
   * Method filterByAttributes
   *
   * @return self
   */
  public function filterByAttributes($except_attribute_id = null): self
  {
    if($attrs = $this->request->input('attrs')) {
      // ak_attribute_product subquery
      $ap = $this->getAttributesQuery($attrs, 'or', $except_attribute_id);

      $this->query->rightJoinSub($ap, 'ap', function ($join) {
        $join->on('ap.product_id', '=', 'ak_products.id');
      });
    }

    return $this;
  }
  
  /**
   * Method filterByCategories
   *
   * @return self
   */
  public function filterByCategories(): self
  {
    // Array of category id and all offspring ids
    $node_ids = Category::getCategoryNodeIdList($this->request->input('category_slug'), $this->request->input('category_id'));
    // $node_ids = Category::getParentNodeIds($this->request->input('category_slug'), $this->request->input('category_id'));
    
    // filtering by category if "category_id" or "category_slug" is presented in request
    if ($node_ids) {
      $this->query->leftJoin('ak_category_product as cp', 'cp.product_id', '=', 'ak_products.id')
                    ->whereIn('cp.category_id', $node_ids);
    }

    return $this;
  }

  
  /**
   * Method filterByPrice
   *
   * @return self
   */
  public function filterByPrice(): self
  {
    if($this->request->input('price')) {
      $priceMin = $this->request->input('price.min', 0);
      $priceMax = $this->request->input('price.max', PHP_INT_MAX);
      
      $this->query->whereBetween('sp.price', [$priceMin, $priceMax]);
    }

    return $this;
  }



  /**
   * Method filterByBrands
   *
   * @return self
   */
  public function filterByBrands(): self
  {
    if ($brands = $this->request->input('brands')) {
      $this->query->leftJoin('ak_brands as brnd', 'ak_products.brand_id', '=', 'brnd.id')
              ->whereIn('brnd.id', $brands);
    }

    return $this;
  }

  /**
   * Method filterByBrandSlug
   *
   * @return self
   */
  public function filterByBrandSlug(): self
  {
    if ($brandSlug = $this->request->input('brand_slug')) {
      $this->query->leftJoin('ak_brands as br', 'ak_products.brand_id', '=', 'br.id')
        ->where('br.slug', $brandSlug);
    }

    return $this;
  }
    
  /**
   * Method filterBySearch
   *
   * @return self
   */
  public function filterBySearch(): self
  {
    if ($q = $this->request->input('q')) {
      $this->query->where(\DB::raw('lower(ak_products.name)'), 'like', '%' . strtolower($q) . '%')
            ->orWhere(\DB::raw('lower(ak_products.short_name)'), 'like', '%' . strtolower($q) . '%')
            ->orWhere(\DB::raw('lower(ak_products.code)'), 'like', '%' . strtolower($q) . '%');
    }

    return $this;
  }


  /**
   * getAttributesQuery
   *
   * @return void
   */
  public function getAttributesQuery($values, $where = "and", $except_attribute_id = null) {
    if(!$values) return;

    $attrs = $this->prepareAttributes($values);
    $attrs_count = count($attrs);

    $ap = DB::table('ak_attribute_product as ap')
                   ->selectRaw('ap.product_id, COUNT(DISTINCT id) as grouped_count');

    foreach($attrs as $index => $attr) {
      
      if(is_int($except_attribute_id) && $except_attribute_id === $attr['attr_id']) {
        continue;
      }
      
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
   * Method sorting
   *
   * @return self
   */
  public function sorting(): self {

    $order_by = $this->request->input('order_by', null);
    $order_dir = $this->request->input('order_dir', 'desc');

    // and ordering to query
    if($order_by) {
      if($order_by === 'in_stock') {
        if(\Settings::get('dress.supplier.enable', false)) {
          $this->query
            ->orderByRaw('IF(SUM(sp.in_stock) > ?, ?, ?) ' . $order_dir, [0, 1, 0])
            ->groupBy('ak_products.id');
        }else {
          $this->query
            ->orderByRaw('IF(ak_products.in_stock > ?, ?, ?) ' . $order_dir, [0, 1, 0]);
        }
      }
      elseif($order_by === 'sales')
      {
        $this->query
          ->leftJoin('ak_order_product as op', 'ak_products.id', '=', 'op.product_id')
          ->orderByRaw('SUM(op.amount) ' . $order_dir)
          ->groupBy('ak_products.id');
      }
      elseif($order_by === 'sale') 
      {
        // ATTANTION NOW price IN SUPPLIER_PRODUCT
        // At first with bigger sale
        $this->query->orderByRaw('ak_products.old_price - ak_products.price ' . $order_dir);
      }
      else 
      {
        $this->query->orderBy($order_by, $order_dir);
      }
    }else {
      // at first in_stock > 0
      if(\Settings::get('dress.supplier.enable', false)) {
        $this->query
          ->orderByRaw('IF(SUM(sp.in_stock) > ?, ?, ?) DESC', [0, 1, 0])
          ->groupBy('ak_products.id');
      }else {
        $this->query
          ->orderByRaw('IF(ak_products.in_stock > ?, ?, ?) DESC', [0, 1, 0]);
      }

      $this->query
        // at first with images
        ->orderBy('images', 'desc')
        // new at first
        ->orderBy('created_at', 'desc');
    }


    return $this;
  }



  /**
   * Method getProducts
   *
   * @return void
   */
  public function getProducts()
  {

    // Make pagination
    $per_page = $this->request->input('per_page', \Settings::get('dress.store.per_page', 24));

    $products = $this->query->select('ak_products.*')->distinct()->paginate($per_page);
    $products = new ProductCollection($products);

    return $products;
  }
  
}