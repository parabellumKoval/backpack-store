<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Illuminate\Http\Request;

//
use Backpack\Store\app\Models\Brand;

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
    
    $brands = self::$resources['brand']['small']::collection($brands);

    return $brands;
  }

  public function show(Request $request, $slug) {
    $brand = Brand::where('slug', $slug)->first();

    return new self::$resources['brand']['large']($brand);
  }
}
