<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Illuminate\Http\Request;

use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Category;

use Backpack\Store\app\Http\Resources\ProductSmallResource;
use Backpack\Store\app\Http\Resources\ProductLargeResource;

class ProductController extends \App\Http\Controllers\Controller
{ 
  public function index(Request $request) {

    try{
      if(request('category_id')){
        $node_ids = Category::find(request('category_id'))->nodeIds;
      }elseif(request('category_slug')){
        $category = Category::where('slug', request('category_slug'))->firstOrFail();
        $node_ids = $category->nodeIds;
      }
    }catch(\Exception $e){
      $node_ids = count($node_ids)? $node_ids: [];
    }finally {
      $node_ids = count($node_ids)? $node_ids: [];
    }

    $products = Product::query()
              ->select('ak_products.*')
              ->distinct('ak_products.id')
              ->active()
              ->when(request('category_id'), function($query) use($node_ids){
                $query->whereIn('ak_products.category_id', $node_ids);
              })
              ->when(request('category_slug'), function($query) use($node_ids){
                $query->whereIn('ak_products.category_id', $node_ids);
              });
    
    $per_page = request('per_page', config('backpack.store.per_page', 12));
    
    $products = $products->paginate($per_page);

    $products = ProductSmallResource::collection($products);

    return $products;
  }

  public function show(Request $request, $slug) {
    $product = Product::where('slug', $slug)->first();

    return new ProductLargeResource($product);
  }

  public function getByIds(Request $request){
    
    if(empty($request->ids))
      return response()->json(['products' => []]);
      
    $products = Product::whereIn('id', $request->ids)->get();
    
    return response()->json($products); 
  }
}
