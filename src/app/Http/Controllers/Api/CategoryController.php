<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Illuminate\Http\Request;

use Backpack\Store\app\Models\Category;

class CategoryController extends \App\Http\Controllers\Controller
{ 
  use \Backpack\Store\app\Traits\Resources;

  public function __construct() {
    self::resources_init();
  }

  public function index(Request $request) {

    $is_root = request('is_root', true);
    $is_active = request('is_active', true);

    $categories = Category::query()
              ->select('ak_product_categories.*')
              
              ->distinct('ak_product_categories.id')
              
              ->when($is_root, function($query) {
                $query->root();
              })

              ->when($is_active, function($query) {
                $query->active();
              })

              // Filter by extras field
              ->when(request('extras'), function($query) {
                $extras = request('extras');
                foreach($extras as $key => $value) {
                  $value = is_numeric($value)? floatval($value): $value;
                  $query->whereJsonContains("extras->{$key}", $value);
                }
              })
              
              ->orderBy('lft')

              ->get();
    
    // default resource
    $resource = self::$resources['category']['small'];

    if(request('resource')) {
      $resource = self::$resources['category'][request('resource')];
    }

    $categories = $resource::collection($categories);

    return $categories;
  }

  public function show(Request $request, $slug) {
    $category = Category::where('slug', $slug)->first();

    return new self::$resources['category']['large']($category);
  }
}
