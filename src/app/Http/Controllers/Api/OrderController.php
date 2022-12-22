<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Illuminate\Http\Request;
use \Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Order;

use Backpack\Store\app\Http\Resources\ProductCartResource;

class OrderController extends \App\Http\Controllers\Controller
{ 

  public function index(Request $request) {

    $orders = Order::query()
              ->select('ak_orders.*')
              ->distinct('ak_orders.id')
              ->active()
              ->when(request('user_id'), function($query) {
                $query->where('ak_orders.user_id', request('user_id'));
              })
              ->when(request('status'), function($query) {
                $query->where('ak_orders.category_id', request('status'));
              })
              ->when(request('is_paid'), function($query) {
                $query->where('ak_orders.is_paid', request('is_paid'));
              })
              ->when(request('price'), function($query) {
                $query->where('ak_orders.price', request('price'));
              });
    
    $per_page = request('per_page', config('backpack.store.order_per_page', 12));
    
    $orders = $orders->paginate($per_page);

    return response()->json($orders);
  }

  public function show(Request $request, $id) {

    try {
      $order = Order::findOrFail($id);
    }catch(ModelNotFoundException $e) {
      return response()->json($e->getMessage(), 404);
    }

    return response()->json($order);
  }

  public function create(Request $request){
    $data = $request->only(['user', 'products', 'address', 'delivery', 'payment']);

    $validator = Validator::make($data, [
      'products' => 'required',
      'payment' => 'required|string|min:2|max:255',
      'delivery' => 'required|string|min:2|max:255',
      'address.country' => 'required|string|min:2|max:255',
      'address.city' => 'required|string|min:2|max:255',
      'address.state' => 'required|string|min:2|max:255',
      'address.street' => 'required|string|min:2|max:255',
      'address.apartment' => 'required|string|min:2|max:255',
      'address.zip' => 'required|string|min:2|max:255',
      'user.id' => 'nullable|integer',
      'user.firstname' => 'required|string|min:2|max:100',
      'user.lastname' => 'required|string|min:2|max:100',
      'user.email' => 'required|email',
      'user.phone' => 'nullable|string',
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
    }
    
    if(isset($data['user']['id'])) {
      try {
        $user_model = config('backpack.store.user_model', 'Backpack\Profile\app\Models\Profile')::findOrFail($data['user']['id']);
      }catch(ModelNotFoundException $e) {
        return response()->json($e->getMessage(), 404);
      }
    }

    $products = Product::whereIn('id', array_keys($data['products']))->get();

    if(!$products || !$products->count()) {
      return response()->json("There are no products found in cart or products does not exist in the database.", 404);
    }

    $info = [];

    // Address
    foreach($data['address'] as $key => $item) {
      $info['address'][$key] = $item;
    }

    // User
    foreach($data['user'] as $key => $item) {
      $info['user'][$key] = $item;
    }

    // Products
    foreach($products as $key => $product) {
      $product->amount = $data['products'][$product->id];
      $info['products'][$key] = new ProductCartResource($product);
    }

    // Delivery
    $info['delivery'] = $data['delivery'];

    // Payment
    $info['payment'] = $data['payment'];


    $order = Order::create([
      'user_id' => isset($user_model)? $user_model->id: null,
      'code' => random_int(100000, 999999),
      'price' => round($products->reduce(function($carry, $item) {
        return $carry + $item->price * $item->amount;
      }, 0), 2),
      'info' => $info
    ]);

    foreach($products as $product) {
      $order->products()->attach($product, ['amount' => $data['products'][$product->id]]);
    }

    return response()->json($order); 
  }
}
