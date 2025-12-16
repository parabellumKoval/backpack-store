<?php
namespace Backpack\Store\app\Services\Catalog;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\Brand;
use Backpack\Store\app\Models\Attribute;
use Backpack\Store\app\Models\AttributeProduct;
use Backpack\Store\app\Models\AttributeValue;

use Backpack\Store\app\Services\Catalog\ProductQueryService;

use Backpack\Store\app\Http\Resources\ProductCollection;

use Backpack\Store\app\Services\Catalog\AbstractFilterService;

class ProductFilterService extends AbstractFilterService
{
  use \Backpack\Store\app\Traits\Resources;

  protected $query;
  protected Request $request;

  protected $top_price_sale_percent = 10;
  protected $top_sales_count = 3;

  protected $product_class;
  protected $product_service;

  protected string $itemsTableName = 'ak_products';
  
  public function __construct(Request $request, ProductQueryService $productService)
  {

    self::resources_init();

    // Product model can be overwritten. For this you have to: 
    //  - create own Product Model,
    //  - extends it from Backpack\Store\app\Models\Product
    //  - set path to your Product Model in config "backpack.store.product.class"
    $this->product_class = \Settings::get('dress.product.model', 'Backpack\Store\app\Models\Product');

    $this->product_service = $productService;
    $this->request = $request;
    $this->query = $this->product_service
        ->startQuery()
        ->filterByCategories()
        ->getQuery();
  }

  
  /**
   * Method calculateAllAttributes
   *
   * @param $product_query $product_query [explicite description]
   *
   * @return void
   */
  protected function calculateAllAttributes($product_query)
  {
      // 1) Дискретные значения: агрегируем по attribute_value_id
      $discrete = AttributeProduct::query()
          ->whereIn('product_id', $product_query->select('ak_products.id'))
          ->whereNotNull('attribute_value_id')
          ->leftJoin('ak_attribute_values as v', 'v.id', '=', 'ak_attribute_product.attribute_value_id')
          ->groupBy('ak_attribute_product.attribute_id', 'v.id')
          ->select([
              'ak_attribute_product.attribute_id as id',
              'v.id as value_key',
              DB::raw('COUNT(DISTINCT ak_attribute_product.product_id) as products_count'),
          ])
          ->get();

      // 2) Диапазоны: агрегируем по NULL-значениям attribute_value_id
      $ranges = AttributeProduct::query()
          ->whereIn('product_id', $product_query->select('ak_products.id'))
          ->whereNull('attribute_value_id')
          ->groupBy('attribute_id')
          ->select([
              'attribute_id as id',
              DB::raw('MIN(value) as min'),
              DB::raw('MAX(value) as max'),
          ])
          ->get();

      // 3) Сборка результата
      $result = [];

      // Заполним дискретные
      foreach ($discrete as $row) {
          $result[$row->id][$row->value_key] = $row->products_count;
      }

      // Заполним диапазоны
      foreach ($ranges as $row) {
          $result[$row->id] = [
              'min' => $row->min,
              'max' => $row->max,
          ];
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
  protected function calculateSingleAttribute($product_query, $active_attr)
  {
      $attr_id = $active_attr['attr_id'];

      if (count($active_attr) == 3) {
          // Это "number" ([attr_id, to, from])
          $minMax = AttributeProduct::query()
              ->whereIn('product_id', $product_query->select('ak_products.id'))
              ->where('attribute_id', $attr_id)
              ->selectRaw('MIN(value) as min, MAX(value) as max')
              ->first();

          $values = ['min' => $minMax->min, 'max' => $minMax->max];
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
  protected function countBrands(): array {
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
  protected function countPrices(): array {
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
  protected function countSelections(): array {
      $query = $this->product_service->applyAllFiltersExcept('selections')->getQuery()
        //Join reviews
        ->leftJoin('ak_reviews as r', function ($join) {
          $baseIdExpr = DB::raw('COALESCE(ak_products.parent_id, ak_products.id)');
          $join->on($baseIdExpr, '=', 'r.reviewable_id')
            ->whereIn('r.reviewable_type', $this->getProductReviewableTypes())
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
  protected function getAttributes() {
    $locale = app()->getLocale();
    $fallback = config('app.fallback_locale');
    $node_ids = Category::getParentNodeIds(
      $this->request->input('category_slug'),
      $this->request->input('category_id'),
      \Store::country()
    );

    $rows = DB::table($this->query->select('ak_products.id'), 'products')
      ->join('ak_attribute_product as ap', 'ap.product_id', '=', 'products.id')
      ->join('ak_attributes as a', 'a.id', '=', 'ap.attribute_id')
      ->when($node_ids, function($query) use($node_ids) {
          $query->join('ak_attribute_category as ac', 'ac.attribute_id', '=', 'a.id')
                ->whereIn('ac.category_id', $node_ids);
      })
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
                'values' => $first->type !== 'number' ? $group->map(function($row) {
                    return ['id' => $row->value_id, 'value' => $row->value];
                })->unique('id')->values()->all() : null,
            ];
        })
        ->values()
        ->all();

    return $attributes;
  } 
  
  protected function getProductReviewableTypes(): array
  {
      $types = [
          \App\Models\Product::class,
          \Backpack\Store\app\Models\Product::class,
          \Backpack\Store\app\Models\Catalog::class,
      ];

      $configured = \Settings::get('backpack.reviews.reviewable_types_list', []);
      $configuredType = data_get($configured, 'product.model');

      if ($configuredType) {
          $types[] = ltrim($configuredType, '\\');
      }

      return array_values(array_unique($types));
  }



}
