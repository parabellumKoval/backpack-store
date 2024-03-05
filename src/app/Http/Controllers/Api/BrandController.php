<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Illuminate\Http\Request;

//
use Backpack\Store\app\Models\Brand;
use Backpack\Store\app\Http\Resources\BrandCollection;

class BrandController extends \App\Http\Controllers\Controller
{ 
  use \Backpack\Store\app\Traits\Resources;

  public function __construct() {
    self::resources_init();
  }

  public function index(Request $request) {

    $brands = Brand::query()
              ->select('ak_brands.*')
              ->distinct('ak_brands.id')
              ->active()
              ->get();

    // Return Grouped by alpha collection or default
    if(request('alpha_grouped', false)) {
      $brands_collection = new BrandCollection($brands, [
        'resource_class' => self::$resources['brand']['small']
      ]);
    }else {
      $brands_collection = self::$resources['brand']['small']::collection($brands);
    }

    return $brands_collection;
  }

  public function show(Request $request, $slug) {
    $brand = Brand::where('slug', $slug)->first();

    return new self::$resources['brand']['large']($brand);
  }
  
}
