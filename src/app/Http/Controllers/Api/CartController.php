<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Illuminate\Http\Request;
use \Illuminate\Database\Eloquent\ModelNotFoundException;

use Illuminate\Support\Facades\Validator;

use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Cart;

class CartController extends \App\Http\Controllers\Controller
{ 

  public function index(Request $request) {
    $auth_user_id = 1;
    $user = config('backpack.store.user_model', 'Backpack\Profile\app\Models\Profile')::findOrFail($auth_user_id);
    return response()->json($user->cart);
  }

  public function updateOrCreate(Request $request) {
    $data = $request->only(['product_id', 'amount']);

    $validator = Validator::make($data, [
      'product_id' => 'required|integer',
      'amount' => 'nullable|integer',
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
    }

    $auth_user_id = 1;
    $user = config('backpack.store.user_model', 'Backpack\Profile\app\Models\Profile')::findOrFail($auth_user_id);

    try {
      $product = Product::findOrFail($data['product_id']);
    }catch(ModelNotFoundException $e) {
      return response()->json($e->getMessage(), 404);
    }

    $cart = Cart::updateOrCreate([
      'user_id' => $user->id,
      'product_id' => $product->id,
    ],[
      'user_id' => $user->id,
      'product_id' => $product->id,
      'amount' => isset($data['amount'])? $data['amount']: 1
    ]);

    return response()->json($cart);
  }

  public function delete(Request $request, $id) {
    try {
      $cart = Cart::findOrFail($id);
    }catch(ModelNotFoundException $e) {
      return response()->json($e->getMessage(), 404);
    }

    $cart->delete();

    return response()->json($cart);
  }

}
