<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// MODELS
use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\Attribute;
use Backpack\Store\app\Models\AttributeProduct;
use Backpack\Store\app\Models\AttributeValue;

// RESOURCES
use Backpack\Store\app\Http\Resources\AttributeLargeResource;

class AttributeController extends \App\Http\Controllers\Controller
{ 

  use \Backpack\Store\app\Traits\Resources;

  public function __construct() {
    self::resources_init();
  }

  public function index(Request $request) {

    $node_ids = Category::getCategoryNodeIdList(request('category_slug'), request('category_id'));

    // dd(request('brand_slug'));
    // $start = microtime(true);
    
    $attributes = Attribute::query()
      ->select('ak_attributes.*')

      ->distinct('ak_attributes.id')

      // Getting only attributes that "is_active" param set to true
      ->where('ak_attributes.is_active', 1)

      // Getting only attributes that "is_filters" param set to true
      ->where('ak_attributes.in_filters', 1)
      
      // filtering by category if "category_id" or "category_slug" is presented in request
      ->when($node_ids, function($query) use($node_ids){
        $query->leftJoin('ak_attribute_category as ac', 'ac.attribute_id', '=', 'ak_attributes.id');
        $query->whereIn('ac.category_id', $node_ids);
      })

      // Get by brand
      ->when(request('brand_slug'), function($query) {
        $query->leftJoin('ak_attribute_product as ap', 'ap.attribute_id', '=', 'ak_attributes.id');
        
        $query->leftJoin('ak_products as pr', 'pr.id', '=', 'ap.product_id');
        $query->where('pr.is_active', 1);

        $query->leftJoin('ak_brands as br', 'br.id', '=', 'pr.brand_id');
        $query->where('br.slug', request('brand_slug'));
      })

      ->orderBy('lft')
      
      ->get();
      
    // dd(microtime(true) - $start);
    $attributes = self::$resources['attribute']['large']::collection($attributes);

    return response()->json($attributes);
  }

  public function show(Request $request, $id) {
    $attribute = Attribute::findOrFail($id);
    return new self::$resources['attribute']['large']($attribute);
  }
}
