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

  /**
   * The index function retrieves and filters attributes based on certain criteria and returns a JSON
   * response if specified.
   * 
   * @param Request request The `index` function you provided seems to be a controller method in a
   * Laravel application. It retrieves attributes based on certain conditions and returns a JSON
   * response if the `` parameter is set to `true`.
   * @param bool json_response The `json_response` parameter in the `index` function is a boolean
   * parameter that determines whether the response should be returned as JSON or not. If
   * `json_response` is set to `true`, the function will return a JSON response. If it is set to
   * `false`, the function will return
   * 
   * @return The `index` function returns a JSON response containing a collection of attributes. The
   * attributes are fetched based on certain conditions such as active status, filter status, category
   * filtering, and brand filtering. The response includes attributes that meet the specified criteria.
   * If the `` parameter is set to `true`, the response is returned as a JSON object.
   * Otherwise, the response is returned as a collection of
   */
  public function index(Request $request, bool $json_response = true) {

    // $node_ids = Category::getCategoryNodeIdList($request->input('category_slug'), $request->input('category_id'));
    $node_ids = Category::getParentNodeIds(
      $request->input('category_slug'),
      $request->input('category_id'),
      $request->input('country') ?? \Store::country()
    );

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
      ->when($request->input('brand_slug'), function($query) {
        $query->leftJoin('ak_attribute_product as ap', 'ap.attribute_id', '=', 'ak_attributes.id');
        
        $query->leftJoin('ak_products as pr', 'pr.id', '=', 'ap.product_id');
        $query->where('pr.is_active', 1);

        $query->leftJoin('ak_brands as br', 'br.id', '=', 'pr.brand_id');
        $query->where('br.slug', $request->input('brand_slug'));
      })

      ->orderBy('lft')
      
      ->get();
      

    if($json_response) {
      $attributes = self::$resources['attribute']['large']::collection($attributes);
      return response()->json($attributes);
    }else {
      return self::$resources['attribute']['large']::collection($attributes);
    }
  }

  public function show(Request $request, $id) {
    $attribute = Attribute::findOrFail($id);
    return new self::$resources['attribute']['large']($attribute);
  }
}
