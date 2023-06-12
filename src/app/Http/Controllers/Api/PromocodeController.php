<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Illuminate\Http\Request;

use Backpack\Store\app\Models\Promocode;
use Backpack\Store\app\Models\Order;
use Carbon\Carbon;

class PromocodeController extends \App\Http\Controllers\Controller
{ 
  public function findAndCheck(Request $request, $code) {
    
    if(!$code)
      throw new \Exception(__('promocode.not_found'));
    
    try {  
      $promocode = Promocode::whereRaw('LOWER(`code`) LIKE ? ',[trim(strtolower($code)).'%'])->firstOrFail();
    }catch(\Exception $e) {
      return response()->json(__('promocode.not_found'), 404);
    }

    if($promocode->limit !== 0 && $promocode->used_times >= $promocode->limit) {
      return response()->json(__('promocode.limit'), 400);
    }

    if(!$promocode->is_active) {
      return response()->json(__('promocode.not_active'), 400);
    }

    if(Carbon::now()->gt($promocode->valid_until)) {
      return response()->json(__('promocode.expired'), 400);
    }

    return $promocode;

  }
}
