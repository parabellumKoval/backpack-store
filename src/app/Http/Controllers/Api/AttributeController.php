<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Illuminate\Http\Request;

use Backpack\Store\app\Models\Category;
use Backpack\Store\app\Models\Attribute;

use Backpack\Store\app\Http\Resources\AttributeLargeResource;

class AttributeController extends \App\Http\Controllers\Controller
{ 
  public function index(Request $request) {

    $attributes = Attribute::query()
              ->select('ak_attributes.*')
              ->distinct('ak_attributes.id')
              ->orderBy('lft')
              ->get();
    
    $attributes = AttributeLargeResource::collection($attributes);

    return response()->json($attributes);
  }
}
