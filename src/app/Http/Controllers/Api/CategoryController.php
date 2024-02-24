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

    $categories = Category::query()
              ->select('ak_product_categories.*')
              ->distinct('ak_product_categories.id')
              ->root()
              ->active()
              ->orderBy('lft')
              ->get();
    
    // $per_page = request('per_page', config('backpack.store.category.per_page', 12));
    
    // $categories = $categories->paginate($per_page);

    $categories = self::$resources['category']['small']::collection($categories);

    return $categories;
  }

  public function show(Request $request, $slug) {
    $category = Category::where('slug', $slug)->first();

    return new self::$resources['category']['large']($category);
  }
}
