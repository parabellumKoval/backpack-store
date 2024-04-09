<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Illuminate\Http\Request;
use Carbon\Carbon;

use Backpack\Store\app\Models\Promocode;

class PromocodeController extends \App\Http\Controllers\Controller
{   
  
  /**
   * index
   *
   * @param  mixed $request
   * @return void
   */
  public function index(Request $request) {
    $promocodes = Promocode::orderBy('created_at')->get();

    $per_page = request('per_page', 12);
    $promocodes = $promocodes->paginate($per_page);

    return response()->json($promocodes);
  }

  /**
   * findAndCheck
   *
   * @param  mixed $request
   * @param  mixed $code
   * @return void
   */
  public function findAndCheck(Request $request, $code) {
    
    if(!$code)
      return response()->json(['message' => __('promocode.not_found')], 404);
    
    try {  
      $promocode = Promocode::whereRaw('LOWER(`code`) LIKE ? ',[trim(strtolower($code)).'%'])->firstOrFail();
    }catch(\Exception $e) {
      return response()->json(['message' => __('promocode.not_found')], 404);
    }

    if($promocode->limit !== 0 && $promocode->used_times >= $promocode->limit) {
      return response()->json(['message' => __('promocode.limit')], 400);
    }

    if(!$promocode->is_active) {
      return response()->json(['message' => __('promocode.not_active')], 400);
    }

    if(Carbon::now()->gt($promocode->valid_until)) {
      return response()->json(['message' => __('promocode.expired')], 400);
    }

    return $promocode;

  }
}
