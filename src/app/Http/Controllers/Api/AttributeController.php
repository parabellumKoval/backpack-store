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

    $start = microtime(true);
    
    $attributes = Attribute::query()
      ->select('ak_attributes.*')

      ->distinct('ak_attributes.id')

      // Getting only products that "is_active" param set to true
      ->active()
      
      // filtering by category if "category_id" or "category_slug" is presented in request
      ->when((request('category_id') || request('category_slug')), function($query) use($node_ids){
        $query->leftJoin('ak_attribute_category as ac', 'ac.category_id', '=', 'ak_attributes.id');
        $query->whereIn('ac.category_id', $node_ids);
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
