<?php
namespace Backpack\Store\app\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\Brand;
use Backpack\Store\app\Models\Attribute;
use Backpack\Store\app\Models\AttributeProduct;
use Backpack\Store\app\Models\AttributeValue;

use Backpack\Store\app\Http\Resources\ProductCollection;

class ProductFilterService
{
  use \Backpack\Store\app\Traits\Resources;

  protected $query;
  protected $request;

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
    $this->product_class = config('backpack.store.product.class', 'Backpack\Store\app\Models\Product');


    $this->request = $request;
  }
    
  /**
   * Method startQuery
   *
   * @return self
   */
  public function startQuery(): self {
    $this->query = DB::table('ak_products')
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
      $query->where('sp.in_stock', '>', 0);
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
    $priceMin = $this->request->input('price.min', 0);
    $priceMax = $this->request->input('price.max', PHP_INT_MAX);
    
    $this->query->whereBetween('sp.price', [$priceMin, $priceMax]);

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
   * Method getProducts
   *
   * @return void
   */
  public function getProducts()
  {

    // Make pagination
    $per_page = $this->request->input('per_page', config('backpack.store.per_page', 12));

    $products = $this->query->select('ak_products.*')->distinct()->paginate($per_page);
    // $products = new ProductCollection($products);

    return $products;
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
    
    if(in_array('price', $with_filter)) {
      $filters[] = [
        'id' => 'price',
        'name' => __('backpack-store::filter.label.price'),
        'si' => __('backpack-store::filter.label.grn'),
        'isOpen' => true,
        'type' => 'number'
      ];
    }

    if(in_array('attributes', $with_filter)) {
      $attrs = $this->getAttributes();

      foreach($attrs as $attr){
        $filters[] = [
          'id' => $attr['id'],
          'name' => $attr['name'],
          'si' => $attr['si'],
          'isOpen' => true,
          'type' => $attr['type'],
          'values' => $attr['values']
        ];
      }
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
      $filters['brands'] = $this->countBrands();
    }
    
    if(in_array('price', $with_filter)) {
      $filters['price'] = $this->countPrices();
    }

    if(in_array('attributes', $with_filter)) {
      $filters['attributes'] = $this->countAttributes();
    }

    return $filters;
  }

  
  /**
   * Method countAttributes
   *
   * @return void
   */
  public function countAttributes() {
    $query_attrs = $this->prepareAttributes($this->request->input('attrs', []));
    $active_attr_ids = array_column($query_attrs, 'attr_id');
    $result = [];

    if(empty($query_attrs)) {
      $product_query = $this->applyAllFiltersExcept();
      $result = $this->calculateAllAttributes($product_query);
    }else {
      foreach ($query_attrs as $active_attr) {
        $attr_id = $active_attr['attr_id'];
        $product_query = $this->applyAllFiltersExcept($attr_id);
        $single_result = $this->calculateSingleAttribute($product_query, $active_attr);
        $result = array_merge($result, $single_result);
      }

      
      $product_query = $this->applyAllFiltersExcept();
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
    $brands = $this->applyAllFiltersExcept('brands')
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
    $query = $this->applyAllFiltersExcept('price')
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
      $query = $this->applyAllFiltersExcept('selections')
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
   * Применение всех фильтров за исключением указанных
   *
   * @param array|string $excludeFilters
   * @return self
   */
  public function applyAllFiltersExcept($excludeFilters = []): \Illuminate\Database\Query\Builder
  {
    // Приводим $excludeFilters к массиву
    $excludeFilters = is_string($excludeFilters)? (array) $excludeFilters: $excludeFilters;

    // Список всех доступных фильтров
    $availableFilters = [
        'startQuery' => 'startQuery',
        'categories' => 'filterByCategories',
        'brandSlug' => 'filterByBrandSlug',
        'brands' => 'filterByBrands',
        'price' => 'filterByPrice',
        'attributes' => 'filterByAttributes',
        'selections' => 'filterBySelections',
        'search' => 'filterBySearch',
    ];

    // Применяем все фильтры, кроме исключенных
    foreach ($availableFilters as $filterKey => $method) {
      if(is_int($excludeFilters)) {
        $this->$method($filterKey === 'attributes'? $excludeFilters: null);
      }elseif (is_array($excludeFilters) && !in_array($filterKey, $excludeFilters)) {
        $this->$method();
      }
    }

    return $this->query;
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
  
  private function getAttributes() {
    $locale = app()->getLocale();
    $fallback = config('app.fallback_locale');

    $rows = DB::table('ak_attributes as a')
      ->join('ak_attribute_values as v', 'v.attribute_id', '=', 'a.id')
      ->join('ak_attribute_product as ap', 'ap.attribute_value_id', '=', 'v.id')
      ->joinSub(
          $this->query->select('ak_products.id'),  // подзапрос с фильтрованными товарами
          'products',
          function($join) { $join->on('products.id', '=', 'ap.product_id'); }
      )
      ->where('a.is_active', 1)
      ->where('a.in_filters', 1)
      ->whereIn('a.type', ['checkbox','radio'])
      ->distinct()
      ->get([
          'a.id',
          DB::raw("
              COALESCE(
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.name, '$.\"{$locale}\"')), ''),
                JSON_UNQUOTE(JSON_EXTRACT(a.name, '$.\"{$fallback}\"'))
              ) AS name
          "),
          'a.type',
          DB::raw("
              COALESCE(
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(a.extras_trans, '$.\"{$locale}\".si')), ''),
                JSON_UNQUOTE(JSON_EXTRACT(a.extras_trans, '$.\"{$fallback}\".si'))
              ) AS si
          "),
          'v.id AS value_id',
          DB::raw("
              COALESCE(
                NULLIF(JSON_UNQUOTE(JSON_EXTRACT(v.value, '$.\"{$locale}\"')), ''),
                JSON_UNQUOTE(JSON_EXTRACT(v.value, '$.\"{$fallback}\"'))
              ) AS value
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
                'values' => $group->map(function($row) {
                    return ['id' => $row->value_id, 'value' => $row->value];
                })->unique('id')->values()->all(),
            ];
        })
        ->values()
        ->all();
    
    return $attributes;
  }
  
  /**
   * Method getAttributes
   *
   * @return void
   */
  // private function getAttributes() {
  //   // Получаем активные атрибуты для фильтрации (исключая type = 'string')
    
  //   // $attributes = DB::table('ak_attributes')
  //   $attributes = Attribute::query()
  //       ->where('is_active', 1)
  //       ->where('in_filters', 1)
  //       ->where('type', '!=', 'string')
  //       ->select('id', 'name', 'type', 'extras_trans')
  //       ->get();

  //   $result = [];

  //   foreach ($attributes as $attribute) {
  //       $attributeId = $attribute->id;
  //       $attributeType = $attribute->type;

  //       // Подзапрос для отфильтрованных товаров
  //       $filteredProducts = $this->query->select('ak_products.id');

  //       if ($attributeType == 'checkbox' || $attributeType == 'radio') {
  //           // Для checkbox и radio: получаем уникальные [id, value]
  //           $valuesQuery = AttributeValue::query()
  //             ->join('ak_attribute_product', 'ak_attribute_values.id', '=', 'ak_attribute_product.attribute_value_id')
  //             ->whereIn('ak_attribute_product.product_id', $filteredProducts)
  //             ->where('ak_attribute_product.attribute_id', $attributeId)
  //             ->select('ak_attribute_values.id', 'ak_attribute_values.value')
  //             ->distinct();

  //           $values = $valuesQuery->get()->map(function ($item) {
  //               return ['id' => $item->id, 'value' => $item->getTranslation('value', app()->getLocale())];
  //           })->toArray();
  //       } elseif ($attributeType == 'number') {
  //           // Для number: получаем min и max
  //           $minMaxQuery = AttributeProduct::query()
  //               ->whereIn('product_id', $filteredProducts)
  //               ->where('attribute_id', $attributeId)
  //               ->selectRaw('MIN(value) as min, MAX(value) as max');

  //           $minMax = $minMaxQuery->first();
  //           $values = ['min' => $minMax->min, 'max' => $minMax->max];
  //       } else {
  //           continue;
  //       }

  //       $result[] = [
  //           'id' => $attributeId,
  //           'name' => $attribute->name,
  //           'type' => $attributeType,
  //           'si' => $attribute->si,
  //           'values' => $values,
  //       ];
  //   }

  //   return $result;
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
  

    
  /**
   * attributesCount
   *
   * @param  mixed $attributes
   * @return void
   */
  // public function attributesCount($attributes) {
  //   $uniq_attrs = [];

  //   for($a = 0; $a < $attributes->count(); $a++) {
  //     $attr = $attributes[$a];
  //     $attr_id = $attr->attribute_id;
  //     $attr_value_id = $attr->attribute_value_id;
  //     $attr_value = $attr->value;

  //     if(!isset($uniq_attrs[$attr_id])){
  //       $uniq_attrs[$attr_id] = [];
  //     }

  //     // If attribute type is checkbox or radio 
  //     if($attr_value_id !== null) {
  //       if(!isset($uniq_attrs[$attr_id][$attr_value_id])){
  //         $uniq_attrs[$attr_id][$attr_value_id] = 0;
  //       }

  //       $uniq_attrs[$attr_id][$attr_value_id] += 1;
  //     }

  //     // If attribute type is number
  //     if($attr_value !== null) {
  //       if(!isset($uniq_attrs[$attr_id]['min']) && !isset($uniq_attrs[$attr_id]['max'])){
  //         $uniq_attrs[$attr_id]['min'] = $uniq_attrs[$attr_id]['max'] = $attr_value;
  //       }

  //       // renew max limit
  //       if($attr_value > $uniq_attrs[$attr_id]['max']) {
  //         $uniq_attrs[$attr_id]['max'] = $attr_value;
  //       }

  //       // renew min limit
  //       if($attr_value < $uniq_attrs[$attr_id]['min']) {
  //         $uniq_attrs[$attr_id]['min'] = $attr_value;
  //       }
  //     }

  //   }

  //   return $uniq_attrs;
  // }


  /**
   * filterValuesCount
   *
   * @param  mixed $products
   * @return void
   */
  // public function filterValuesCount($products){
    
  //   //define empty array
  //   $uniq_attrs = [
  //     'price' => [
  //       'min' => null,
  //       'max' => null
  //     ]
  //   ];
    
  //   // for each product
  //   for($p = 0; $p < $products->count(); $p++){

  //     // Set initial price
  //     if($uniq_attrs['price']['min'] === null || $uniq_attrs['price']['max'] === null) {
  //       $uniq_attrs['price']['min'] = $uniq_attrs['price']['max'] = $products[$p]->price;
  //     }

  //     // Set lower price limit
  //     if($products[$p]->price < $uniq_attrs['price']['min']) {
  //       $uniq_attrs['price']['min'] = $products[$p]->price;
  //     }

  //     // Set upper price limit
  //     if($products[$p]->price > $uniq_attrs['price']['max']) {
  //       $uniq_attrs['price']['max'] = $products[$p]->price;
  //     }

  //     $attributes = $products[$p]->ap;

  //     for($a = 0; $a < $attributes->count(); $a++) {
  //       $attr = $attributes[$a];
  //       $attr_id = $attr->attribute_id;
  //       $attr_value_id = $attr->attribute_value_id;
  //       $attr_value = $attr->value;

  //       if(!isset($uniq_attrs[$attr_id])){
  //         $uniq_attrs[$attr_id] = [];
  //       }

  //       // If attribute type is checkbox or radio 
  //       if($attr_value_id !== null) {
  //         if(!isset($uniq_attrs[$attr_id][$attr_value_id])){
  //           $uniq_attrs[$attr_id][$attr_value_id] = 0;
  //         }

  //         $uniq_attrs[$attr_id][$attr_value_id] += 1;
  //       }

  //       // If attribute type is number
  //       if($attr_value !== null) {
  //         if(!isset($uniq_attrs[$attr_id]['min']) && !isset($uniq_attrs[$attr_id]['max'])){
  //           $uniq_attrs[$attr_id]['min'] = $uniq_attrs[$attr_id]['max'] = $attr_value;
  //         }

  //         // renew max limit
  //         if($attr_value > $uniq_attrs[$attr_id]['max']) {
  //           $uniq_attrs[$attr_id]['max'] = $attr_value;
  //         }

  //         // renew min limit
  //         if($attr_value < $uniq_attrs[$attr_id]['min']) {
  //           $uniq_attrs[$attr_id]['min'] = $attr_value;
  //         }
  //       }

  //     }
  //   }

  //   return $uniq_attrs;
  // }

    
  /**
   * category
   *Request $request
   * @param  mixed $request
   * @param  mixed $slug
   * @return void
   */
  // public function category(Request $request) {

  //   $fake_request = new \Illuminate\Http\Request();
  //   $fake_request->replace(['category_slug' => $request->input('category_slug')]);

  //   // First page products and all filters meta
  //   $products_page_1 = $this->index($fake_request, false);

  //   // Brands
  //   $brands = $this->index($fake_request, false);

  //   // Category
  //   $category_controller = new \Backpack\Store\app\Http\Controllers\Api\CategoryController;
  //   $category = $category_controller->show($fake_request, $request->input('category_slug'));

  //   // Attributes
  //   $attributes_controller = new \Backpack\Store\app\Http\Controllers\Api\AttributeController;
  //   $attributes = $attributes_controller->index($fake_request, false);


  //   return response()->json([
  //     'products' => $products_page_1['products'] ?? null,
  //     'filters' => $products_page_1['filters'] ?? null,
  //     'brands' => $brands,
  //     'category' => $category,
  //     'attributes' => $attributes
  //   ]);
  // }



  /**
   * filters
   *
   * @param  mixed $request
   * @return void
   */
  // public function filters(Request $request) {

  //   $products_query = $this->getQuery($request, false, 'or');
  //   $selections_count = $this->calculateSelectionsCount($products_query);
    
  //   // Get filters count
  //   $products_collection = $products_query
  //     ->select('ak_ap.*')
  //     ->join('ak_attribute_product as ak_ap', 'ak_products.id', '=', 'ak_ap.product_id')
  //     ->when($this->is_top_sales, function($query) {
  //       $query->groupBy('ak_ap.id');
  //     })
  //     ->get();

  //   $attributes_count = $this->attributesCount($products_collection);


  //   $products_query_for_brands = $this->getQuery($request, false, 'or', 'brand');
  //   $attributes_count['brand'] = $this->brandsCount($products_query_for_brands);

  //   $products_query_for_price = $this->getQuery($request, false, 'or', 'price');
  //   $attributes_count['price'] = $this->calculatePriceCount($products_query_for_price);

  //   $attributes_count['selections'] = $selections_count;

  //   return $attributes_count;
  // }
}