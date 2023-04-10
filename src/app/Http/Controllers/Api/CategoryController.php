<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Illuminate\Http\Request;

use Backpack\Store\app\Models\Category;

use Backpack\Store\app\Http\Resources\CategorySmallResource;
use Backpack\Store\app\Http\Resources\CategoryLargeResource;

class CategoryController extends \App\Http\Controllers\Controller
{ 

  public function index(Request $request) {

    $categories = Category::query()
              ->select('ak_product_categories.*')
              ->distinct('ak_product_categories.id')
              ->root()
              ->active()
              ->orderBy('lft');
    
    $per_page = request('per_page', config('backpack.store.category_per_page', 12));
    
    $categories = $categories->paginate($per_page);

    $categories = CategorySmallResource::collection($categories);

    return $categories;
  }

  public function show(Request $request, $slug) {
    $category = Category::where('slug', $slug)->first();

    return new CategoryLargeResource($category);
  }
}
