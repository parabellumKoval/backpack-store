<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Illuminate\Http\Request;
use \Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Order;

use Backpack\Store\app\Http\Resources\ProductCartResource;
use Backpack\Store\app\Http\Resources\OrderLargeResource;

class OrderController extends \App\Http\Controllers\Controller
{ 

  private $order_model = '';

  public function __construct() {
    $this->order_model = config('backpack.store.order_model', 'Backpack\Store\app\Models\Order');
  }

  public function index(Request $request) {

    $profile = Auth::guard(config('backpack.store.auth_guard', 'profile'))->user();

    $orders = $this->order_model::query()
              ->select('ak_orders.*')
              ->distinct('ak_orders.id')
              ->where('user_id', $profile->id)
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
    $orders = OrderLargeResource::collection($orders);

    return $orders;
  }

  public function all(Request $request) {

    $orders = $this->order_model::query()
              ->select('ak_orders.*')
              ->distinct('ak_orders.id')
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
    $orders = OrderLargeResource::collection($orders);

    return $orders;
  }

  public function show(Request $request, $id) {

    try {
      $order = $this->order_model::findOrFail($id);
    }catch(ModelNotFoundException $e) {
      return response()->json($e->getMessage(), 404);
    }

    return response()->json($order);
  }

  private function assignArrayByPath(&$arr, $path, $value, $separator='.') {
    $keys = explode($separator, $path);

    foreach ($keys as $key) {
        $arr = &$arr[$key];
    }

    $arr = $value;
  }

  public function create(Request $request){
    $data = $request->only(['user', 'products', 'address', 'delivery', 'payment', 'provider', 'bonusesUsed']);

    $validator = Validator::make($data, [
      'products' => 'required|array',
      'payment' => 'required|string|min:2|max:255',
      'delivery' => 'required|string|min:2|max:255',
      'address.country' => 'required|string|min:2|max:255',
      'address.city' => 'required|string|min:2|max:255',
      'address.state' => 'required|string|min:2|max:255',
      'address.street' => 'required|string|min:2|max:255',
      'address.apartment' => 'required|string|min:2|max:255',
      'address.zip' => 'required|string|min:2|max:255',
      'user' => 'required_if:provider,data',
      'provider' => 'required|in:auth,data'
    ]);

    if ($validator->fails()) {
      $errors = $validator->errors()->toArray();
      $errors_array = [];

      foreach($errors as $key => $error){
        $this->assignArrayByPath($errors_array, $key, $error);
      }

      return response()->json($errors_array, 400);
    }

    // GET USER MODEL IF AUTHED
    if($data['provider'] === 'auth') {

      if(!Auth::guard(config('backpack.store.auth_guard', 'profile'))->check()){
        return response()->json('User not authenticated', 401);
      }

      $user_model = Auth::guard(config('backpack.store.auth_guard', 'profile'))->user();
    }

    // GET PRODUCTS COLLECTION
    $products = Product::whereIn('id', array_keys($data['products']))->get();

    if(!$products || !$products->count()) {
      return response()->json("There are no products found in cart or products does not exist in the database.", 404);
    }

    $info = [];

    // Address
    foreach($data['address'] as $key => $item) {
      $info['address'][$key] = $item;
    }

    // User data
    if(isset($data['user']) && is_array($data['user']))
    {
      foreach($data['user'] as $key => $item) {
        $info['user'][$key] = $item;
      }
    }
    else
    {
      $info['user'] = $data['user'];
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

    // Bonuses used
    $info['bonusesUsed'] = $data['bonusesUsed'];

    $order = $this->order_model::create([
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
