<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Illuminate\Http\Request;

use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Services\Category\CategoryAvailability;

class CategoryController extends \App\Http\Controllers\Controller
{ 
  use \Backpack\Store\app\Traits\Resources;

  public function __construct() {
    self::resources_init();
  }

  public function index(Request $request) {

    $is_root = $request->input('is_root', true);
    $is_active = $request->input('is_active', true);
    $country = $request->input('country') ?? \Store::country();
    $extras = $request->input('extras');

    $categories = Category::query()
              ->select('ak_product_categories.*')
              ->with('tags')
              ->forCountry($country, true)
              
              ->distinct('ak_product_categories.id')
              
              ->when($is_root, function($query) {
                $query->root();
              })

              ->when($is_active, function($query) {
                $query->active();
              })

              // Filter by extras field
              ->when($extras, function($query, $extras) {
                foreach($extras as $key => $value) {
                  // $value = is_numeric($value)? floatval($value): $value;
                  $query->whereJsonContains("extras->{$key}", $value);
                }
              })
              
              ->orderBy('lft')

              ->get();
    
    // default resource
    $resource = self::$resources['category']['small'];

    if($request->input('resource')) {
      $resource = self::$resources['category'][$request->input('resource')];
    }

    $categories = $resource::collection($categories);

    return $categories;
  }

  public function main(Request $request) {
    $request->merge([
      'is_root' => false,
      'extras' => ['on_main' => '1']
    ]);

    return $this->index($request);
  }


  // public function indexSimple(Request $request) {

  //   $is_root = $request->input('is_root', true);
  //   $is_active = $request->input('is_active', true);
  //   $country = $request->input('country') ?? \Store::country();

  //    $categories = Category::query()
  //             ->select('ak_product_categories.*')
  //             ->forCountry($country, true)
  //             ->distinct('ak_product_categories.id')
  //             ->active()
  //             ->orderBy('lft')
  //             ->get();
  // }

  public function show(Request $request, $slug) {
    $country = $request->input('country') ?? \Store::country();
    $category = Category::query()
      ->forCountry($country, true)
      ->with('tags')
      ->where('slug', $slug)
      ->first();

    $category = CategoryAvailability::ensure($category, $country, true, true);
    $resource = new self::$resources['category']['large']($category);
    // return new self::$resources['category']['large']($category);
    return $resource;
  }
}
