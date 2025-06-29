<?php
namespace Backpack\Store\app\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\Brand;
use Backpack\Store\app\Models\Attribute;
use Backpack\Store\app\Models\AttributeProduct;
use Backpack\Store\app\Models\AttributeValue;

use Backpack\Store\app\Services\ProductQueryService;

use Backpack\Store\app\Http\Resources\ProductCollection;

class ProductFilterService
{
  use \Backpack\Store\app\Traits\Resources;

  protected $query;
  protected $request;

  protected $top_price_sale_percent = 10;
  protected $top_sales_count = 3;

  protected $product_class;
  protected $product_service;
  
  public function __construct(Request $request, ProductQueryService $productService)
  {

    self::resources_init();

    // Product model can be overwritten. For this you have to: 
    //  - create own Product Model,
    //  - extends it from Backpack\Store\app\Models\Product
    //  - set path to your Product Model in config "backpack.store.product.class"
    $this->product_class = config('backpack.store.product.class', 'Backpack\Store\app\Models\Product');

    $this->product_service = $productService;
    $this->request = $request;
    $this->query = $this->product_service
        ->startQuery()
        ->filterByCategories()
        ->getQuery();
  }
  
  
  /**
   * Method getFiltersData
   *
   * @return void
   */
  public function getFiltersData()
  {
    $with_filter = $this->request->input('with_filter', []);
    $filters = [];

    if(in_array('selections', $with_filter)) {
      $filters[] = [
        'id' => 'selections',
        'name' =>  __('backpack-store::filter.label.selections'),
        'si' => null,
        'isOpen' => true,
        'noSearch' => true,
        'isNarrowing' => true,
        'type' => 'checkbox',
        'values' => $this->getSelectionValues()
      ];
    }

    if(in_array('brands', $with_filter)) {
      $filters[] = [
        'id' => 'brand',
        'name' => __('backpack-store::filter.label.brand'),
        'si' => null,
        'isOpen' => true,
        'noMeta' => false,
        'type' => 'brand',
        'values' => $this->getBrandValues()
      ];
    }

    if(in_array('attributes', $with_filter)) {
      $attrs = $this->getAttributes();

      foreach($attrs as $attr){
        $filters[] = [
          'id' => $attr['id'],
          'name' => $attr['name'],
          'si' => $attr['si'] ?? null,
          'isOpen' => false,
          'type' => $attr['type'],
          'values' => $attr['values']
        ];
      }
    }
    
    if(in_array('price', $with_filter)) {
      $filters[] = [
        'id' => 'price',
        'name' => __('backpack-store::filter.label.price'),
        'si' => __('backpack-store::filter.label.grn'),
        'isOpen' => true,
        'type' => 'number'
      ];
    }

    return $filters;
  }

  
  /**
   * Method getFiltersCount
   *
   * @return void
   */
  public function getFiltersCount()
  {
    $with_filter = $this->request->input('with_filter_count', []);
    $filters = [];

    if(in_array('selections', $with_filter)) {
      $filters['selections'] = $this->countSelections();
    }

    if(in_array('brands', $with_filter)) {
      $filters['brand'] = $this->countBrands();
    }
    
    if(in_array('price', $with_filter)) {
      $filters['price'] = $this->countPrices();
    }

    if(in_array('attributes', $with_filter)) {
      $attributes = $this->countAttributes() ?? [];
      $filters = $filters + $attributes;
    }

    return $filters;
  }

  
  /**
   * Method countAttributes
   *
   * @return void
   */
  public function countAttributes() {
    $query_attrs = $this->product_service->prepareAttributes($this->request->input('attrs', []));
    $active_attr_ids = array_column($query_attrs, 'attr_id');
    $result = [];

    if(empty($query_attrs)) {
      $product_query = $this->product_service->applyAllFiltersExcept()->getQuery();
      $result = $this->calculateAllAttributes($product_query);
    }else {
      foreach ($query_attrs as $active_attr) {
        $attr_id = $active_attr['attr_id'];
        $product_query = $this->product_service->applyAllFiltersExcept($attr_id)->getQuery();
        $single_result = $this->calculateSingleAttribute($product_query, $active_attr);
        $result = array_merge($result, $single_result);
      }

      
      $product_query = $this->product_service->applyAllFiltersExcept()->getQuery();
      $all_attributes = $this->calculateAllAttributes($product_query);
      foreach ($all_attributes as $attr_id => $values) {
        if (!in_array($attr_id, $active_attr_ids)) {
          $result[$attr_id] = $values;
        }
      }
    }

    return $result;
  }

  
  /**
   * Method calculateAllAttributes
   *
   * @param $product_query $product_query [explicite description]
   *
   * @return void
   */
  private function calculateAllAttributes($product_query)
  {
    $attributes = AttributeValue::query()
        ->join('ak_attribute_product', 'ak_attribute_values.id', '=', 'ak_attribute_product.attribute_value_id')
        ->whereIn('ak_attribute_product.product_id', $product_query->select('ak_products.id'))
        ->groupBy('ak_attribute_product.attribute_id', 'ak_attribute_values.id')
        ->select(
            'ak_attribute_product.attribute_id as id',
            'ak_attribute_values.id as value_key',
            DB::raw('COUNT(DISTINCT ak_attribute_product.product_id) as products_count')
        )
        ->get();

    $result = [];
    foreach ($attributes->groupBy('id') as $attr_id => $values) {
        $result[$attr_id] = $values->pluck('products_count', 'value_key')->toArray();
    }

    return $result;
  }

  
  /**
   * Method calculateSingleAttribute
   *
   * @param $product_query $product_query [explicite description]
   * @param $active_attr $active_attr [explicite description]
   *
   * @return void
   */
  private function calculateSingleAttribute($product_query, $active_attr)
  {
      $attr_id = $active_attr['attr_id'];

      if (count($active_attr) == 3) {
          // Это "number" ([attr_id, to, from])
          $minMax = AttributeProduct::query()
              ->whereIn('product_id', $product_query->select('ak_products.id'))
              ->where('attribute_id', $attr_id)
              ->selectRaw('MIN(value) as min, MAX(value) as max')
              ->first();

          $values = [$minMax->min, $minMax->max];
      } else {
          // Это "checkbox" или "radio" ([attr_id, attr_value_id])
          $values = AttributeValue::query()
              ->join('ak_attribute_product', 'ak_attribute_values.id', '=', 'ak_attribute_product.attribute_value_id')
              ->whereIn('ak_attribute_product.product_id', $product_query->select('ak_products.id'))
              ->where('ak_attribute_product.attribute_id', $attr_id)
              ->groupBy('ak_attribute_values.id')
              ->select('ak_attribute_values.id as value_key', DB::raw('COUNT(DISTINCT ak_attribute_product.product_id) as products_count'))
              ->get()
              ->pluck('products_count', 'value_key')
              ->toArray();
      }

      return [$attr_id => $values];
  }

  /**
   * Method countBrands
   *
   * @return void
   */
  public function countBrands() {
    $brands = $this->product_service->applyAllFiltersExcept('brands')->getQuery()
        ->select('ak_products.brand_id', DB::raw('COUNT(DISTINCT ak_products.id) as count'))
        ->whereNotNull('ak_products.brand_id')
        ->groupBy('ak_products.brand_id')
        ->pluck('count', 'brand_id')
        ->toArray();

    return $brands;
  }
  
  /**
   * Method countPrices
   *
   * @return void
   */
  private function countPrices() {
    $query = $this->product_service->applyAllFiltersExcept('price')->getQuery()
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
    
  /**
   * Method countSelections
   *
   * @return void
   */
  private function countSelections() {
      $query = $this->product_service->applyAllFiltersExcept('selections')->getQuery()
        //Join reviews
        ->leftJoin('ak_reviews as r', function ($join) {
          $join->on('r.reviewable_id', '=', 'ak_products.id')
            ->where('r.reviewable_type', '=', 'Backpack\Store\app\Models\Product')
            ->where('r.is_moderated', '=', 1);
        })
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
   * Method getAttributes
   *
   * @return void
   */
  private function getAttributes() {
    $locale = app()->getLocale();
    $fallback = config('app.fallback_locale');
    $node_ids = Category::getParentNodeIds($this->request->input('category_slug'), $this->request->input('category_id'));

    $rows = DB::table($this->query->select('ak_products.id'), 'products')
      ->join('ak_attribute_product as ap', 'ap.product_id', '=', 'products.id')
      ->join('ak_attributes as a', 'a.id', '=', 'ap.attribute_id')
      ->when($node_ids, function($query) use($node_ids) {
          $query->join('ak_attribute_category as ac', 'ac.attribute_id', '=', 'a.id')
                ->whereIn('ac.category_id', $node_ids);
      })
      // <-- change here: leftJoin so number‐only attributes survive
      ->leftJoin('ak_attribute_values as v', 'v.id', '=', 'ap.attribute_value_id')
      ->where('a.is_active', 1)
      ->where('a.in_filters', 1)
      ->whereIn('a.type', ['checkbox', 'radio', 'number'])
      ->distinct()
      ->get([
          'a.id',
          DB::raw("COALESCE(
              NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.name, '$.\"{$locale}\"')), ''),
              JSON_UNQUOTE(JSON_EXTRACT(a.name, '$.\"{$fallback}\"'))
          ) AS name"),
          'a.type',
          DB::raw("COALESCE(
              NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.extras_trans, '$.\"{$locale}\".si')), ''),
              JSON_UNQUOTE(JSON_EXTRACT(a.extras_trans, '$.\"{$fallback}\".si'))
          ) AS si"),
          // keep the raw attribute_value_id so you can group the non-number ones
          'v.id AS value_id',
          // CASE: if it's a number pull ap.value, otherwise the JSON value from v
          DB::raw("
              CASE
                WHEN a.type = 'number' THEN ap.value
                ELSE COALESCE(
                    NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v.value, '$.\"{$locale}\"')), ''),
                    JSON_UNQUOTE(JSON_EXTRACT(v.value, '$.\"{$fallback}\"'))
                )
              END AS value
          "),
      ]);


    $attributes = $rows
        ->groupBy('id')
        ->map(function($group) {
            $first = $group->first();
            return [
                'id' => $first->id,
                'name' => $first->name,
                'type' => $first->type,
                'si' => $first->si,
                'values' => $first->type !== 'number' ? $group->map(function($row) {
                    return ['id' => $row->value_id, 'value' => $row->value];
                })->unique('id')->values()->all() : null,
            ];
        })
        ->values()
        ->all();

    return $attributes;
  } 
  // private function getAttributes() {
  //   $locale = app()->getLocale();
  //   $fallback = config('app.fallback_locale');

  //   $rows = DB::table('ak_attributes as a')
  //     ->join('ak_attribute_values as v', 'v.attribute_id', '=', 'a.id')
  //     ->join('ak_attribute_product as ap', 'ap.attribute_value_id', '=', 'v.id')
  //     ->joinSub(
  //         $this->query->select('ak_products.id'),  // подзапрос с фильтрованными товарами
  //         'products',
  //         function($join) { $join->on('products.id', '=', 'ap.product_id'); }
  //     )
  //     ->where('a.is_active', 1)
  //     ->where('a.in_filters', 1)
  //     ->whereIn('a.type', ['checkbox','radio','number'])
  //     ->distinct()
  //     ->get([
  //         'a.id',
  //         DB::raw("
  //             COALESCE(
  //               NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.name, '$.\"{$locale}\"')), ''),
  //               JSON_UNQUOTE(JSON_EXTRACT(a.name, '$.\"{$fallback}\"'))
  //             ) AS name
  //         "),
  //         'a.type',
  //         DB::raw("
  //             COALESCE(
  //               NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.extras_trans, '$.\"{$locale}\".si')), ''),
  //               JSON_UNQUOTE(JSON_EXTRACT(a.extras_trans, '$.\"{$fallback}\".si'))
  //             ) AS si
  //         "),
  //         'v.id AS value_id',
  //         DB::raw("
  //             COALESCE(
  //               NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v.value, '$.\"{$locale}\"')), ''),
  //               JSON_UNQUOTE(JSON_EXTRACT(v.value, '$.\"{$fallback}\"'))
  //             ) AS value
  //         "),
  //     ]);

  //   $attributes = $rows
  //       ->groupBy('id')
  //       ->map(function($group) {
  //           $first = $group->first();
  //           return [
  //               'id' => $first->id,
  //               'name' => $first->name,
  //               'type' => $first->type,
  //               'si' => $first->si,
  //               'values' => $group->map(function($row) {
  //                   return ['id' => $row->value_id, 'value' => $row->value];
  //               })->unique('id')->values()->all(),
  //           ];
  //       })
  //       ->values()
  //       ->all();
    
  //   return $attributes;
  // }
  

  /**
   * Method getBrandValues
   *
   * @return void
   */
  private function getBrandValues() {
    $query = (clone $this->query)
      ->select('br.id', 'br.name', 'br.slug', 'br.images')
      ->join('ak_brands as br', 'ak_products.brand_id', '=', 'br.id')
      ->groupBy('br.id')
      ->get();

    $sortBy = 'name';

    $brands = Brand::hydrate($query->sortBy($sortBy)->all());
    
    return self::$resources['brand']['filter']::collection($brands);
  }

  /**
   * Method getSelectionValues
   *
   * @return void
   */
  private function getSelectionValues() {
    return [
      'with_sales' => [
        'id' => 'with_sales',
        'value' => __('backpack-store::filter.selections.with_sales')
      ],
      'top_price' => [
        'id' => 'top_price',
        'value' => __('backpack-store::filter.selections.top_price')
      ],
      'top_sales' => [
        'id' => 'top_sales',
        'value' => __('backpack-store::filter.selections.top_sales')
      ],
      'with_rating' => [
        'id' => 'with_rating',
        'value' => __('backpack-store::filter.selections.with_rating')
      ],
      'in_stock' => [
        'id' => 'in_stock',
        'value' => __('backpack-store::filter.selections.in_stock')
      ]
    ];
  }

}