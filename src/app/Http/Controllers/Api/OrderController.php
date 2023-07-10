<?php

namespace Backpack\Store\app\Http\Controllers\Api;

use Illuminate\Http\Request;
use \Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

// MODELS
use Backpack\Store\app\Models\Product;
use Backpack\Store\app\Models\Order;
use Backpack\Store\app\Models\Promocode;

// RESOURCES
use Backpack\Store\app\Http\Resources\ProductCartResource;

// EVENTS
use Backpack\Store\app\Events\ProductAttachedToOrder;
use Backpack\Store\app\Events\PromocodeApplied;

class OrderController extends \App\Http\Controllers\Controller
{ 

  private $ORDER_MODEL = '';
  private $USER_MODEL = '';
  private $ORDER_LARGE_RESOURCE = '';

  public function __construct() {
    $this->ORDER_MODEL = config('backpack.store.order_model', 'Backpack\Store\app\Models\Order');
    $this->USER_MODEL = config('backpack.store.user_model', 'Backpack\Profile\app\Models\Profile');

    $this->ORDER_LARGE_RESOURCE = config('backpack.store.order.large_resource', 'Backpack\Store\app\Http\Resources\OrderLargeResource');
  }

  public function index(Request $request) {

    $profile = Auth::guard(config('backpack.store.auth_guard', 'profile'))->user();

    $orders = $this->ORDER_MODEL::query()
              ->select('ak_orders.*')
              ->distinct('ak_orders.id')
              ->where('ak_orders.orderable_id', $profile->id)
              ->where('ak_orders.orderable_type', $this->USER_MODEL)
              ->when(request('status'), function($query) {
                $query->where('ak_orders.category_id', request('status'));
              })
              ->when(request('price'), function($query) {
                $query->where('ak_orders.price', request('price'));
              })
              ->orderBy('created_at', 'desc');
    
    $per_page = request('per_page', config('backpack.store.order.per_page', 12));
    
    $orders = $orders->paginate($per_page);
    $orders = $this->ORDER_LARGE_RESOURCE::collection($orders);

    return $orders;
  }

  public function all(Request $request) {

    $orders = $this->ORDER_MODEL::query()
              ->select('ak_orders.*')
              ->distinct('ak_orders.id')
              ->when(request('orderable_id'), function($query) {
                $query->where('ak_orders.orderable_id', request('orderable_id'));
              })
              ->when(request('orderable_type'), function($query) {
                $query->where('ak_orders.orderable_type', request('orderable_type'));
              })
              ->when(request('status'), function($query) {
                $query->where('ak_orders.category_id', request('status'));
              })
              ->when(request('pay_status'), function($query) {
                $query->where('ak_orders.pay_status', request('pay_status'));
              })
              ->when(request('delivery_status'), function($query) {
                $query->where('ak_orders.delivery_status', request('delivery_status'));
              })
              ->when(request('price'), function($query) {
                $query->where('ak_orders.price', request('price'));
              })
              ->orderBy('created_at', 'desc');
    
    $per_page = request('per_page', config('backpack.store.order.per_page', 12));
    
    $orders = $orders->paginate($per_page);
    $orders = $this->ORDER_LARGE_RESOURCE::collection($orders);

    return $orders;
  }

  public function show(Request $request, $code) {

    try {
      $order = $this->ORDER_MODEL::where('code', $code)->firstOrFail();
    }catch(ModelNotFoundException $e) {
      return response()->json($e->getMessage(), 404);
    }

    return response()->json(new $this->ORDER_LARGE_RESOURCE($order));
  }

  private function assignArrayByPath(&$arr, $path, $value, $separator='.') {
    $keys = explode($separator, $path);

    foreach ($keys as $key) {
        $arr = &$arr[$key];
    }

    $arr = $value;
  }

  public function validateData(Request $request) {
    // Get only allowed fields
    $data = $request->only($this->ORDER_MODEL::getFieldKeys());

    // Apply validation rules to data
    $validator = Validator::make($data, $this->ORDER_MODEL::getRules());

    if ($validator->fails()) {
      $errors = $validator->errors()->toArray();
      $errors_array = [];

      foreach($errors as $key => $error){
        $this->assignArrayByPath($errors_array, $key, $error);
      }

      return response()->json($errors_array, 400);
    }

    return $data;
  }

  public function create(Request $request){
    
    // Get only allowed fields
    $data = $request->only($this->ORDER_MODEL::getFieldKeys());

    // Apply validation rules to data
    $validator = Validator::make($data, $this->ORDER_MODEL::getRules());

    if ($validator->fails()) {
      $errors = $validator->errors()->toArray();
      $errors_array = [];

      foreach($errors as $key => $error){
        $this->assignArrayByPath($errors_array, $key, $error);
      }

      return response()->json($errors_array, 400);
    }

    // Create new empty Order 
    $order = new $this->ORDER_MODEL;

    // Set common fields
    foreach($data as $field_name => $field_value){
      $config_fields = $this->ORDER_MODEL::getFields();
      $field = $config_fields[$field_name] ?? $config_fields[$field_name.'.*'];
      
      if(isset($field['hidden']) && $field['hidden'])
        continue;

      if(isset($field['store_in'])) {
        $field_old_value = $order->{$field['store_in']};
        $field_old_value[$field_name] = $field_value;
        $order->{$field['store_in']} = $field_old_value;
      }else {
        $order->{$field_name} = $field_value;
      }
    }

    // Generate order code
    $order->code = random_int(100000, 999999);

    // Generate order code
    $order->status = config('backpack.store.order.status.default', 'new');
    
    // Generate order code
    $order->pay_status = config('backpack.store.order.pay_status.default', 'waiting');
    
    // Generate order code
    $order->delivery_status = config('backpack.store.order.delivery_status.default', 'waiting');

    // Get products collection
    $products = Product::whereIn('id', array_keys($data['products']))->get();

    if(!$products || !$products->count()) {
      return response()->json("There are no products found in cart or products does not exist in the database.", 404);
    }

    // Set products to info
    foreach($products as $key => $product) {
      $product->amount = $data['products'][$product->id];
      $info = $order->info;
      $info['products'][$key] = new ProductCartResource($product);
      $order->info = $info;
    }

    // Set order total price
    $order->price = round($products->reduce(function($carry, $item) {
      return $carry + $item->price * $item->amount;
    }, 0), 2);

    // GET USER MODEL IF AUTHED
    if($data['provider'] === 'auth') {

      if(!Auth::guard(config('backpack.store.auth_guard', 'profile'))->check()){
        return response()->json('User not authenticated', 401);
      }

      $user_model = Auth::guard(config('backpack.store.auth_guard', 'profile'))->user();
      $user_data = $user_model->infoData;

      // add user data to info field (json)
      $info = $order->info;
      $info['user'] = $user_data;
      $order->info = $info;

      $order->orderable_id = isset($user_model)? $user_model->id: null;
      $order->orderable_type = isset($user_model)? config('backpack.store.user_model', 'Backpack\Profile\app\Models\Profile'): null;
    }

    // Try validate and apply promocode to order
    $order = $this->usePromocode($order, $data);

    try {
      $order->save();

      foreach($products as $product) {
        $order->products()->attach($product, ['amount' => $data['products'][$product->id]]);
      }

      // Dispatch event to change product in_stock etc.
      ProductAttachedToOrder::dispatch($order);
      
      if($order->promocode)
        PromocodeApplied::dispatch($order);

    }catch(\Exception $e){
      return response()->json($e->getMessage(), 400);
    }

    return response()->json(new $this->ORDER_LARGE_RESOURCE($order));
  }

  protected function usePromocode($order, $data) {
    if(!isset($data['promocode']) || empty($data['promocode']) || !$order)
      return $order;
    
    $promocode = Promocode::whereRaw('LOWER(`code`) LIKE ? ',[trim(strtolower($data['promocode'])).'%'])->first();
    
    if(!$promocode || !$promocode->isValid)
      return $order;
    

    // Set info about promocode to order's info
    $info = $order->info;
    $info['promocode'] = $promocode;
    $order->info = $info;

    if($promocode->type === 'value')
      $order->price = $order->price - $promocode->value;

    if($promocode->type === 'percent')
      $order->price = $order->price - ($order->price * $promocode->value / 100);
    
    return $order;
  }

  public function copy(Request $request) {
    if(!$request->id)
      throw new Exception('The ID of the base record was not pass.');
  
    $base = Order::findOrFail($request->id);
    
    $order = $base->replicate();

    try {
      $order->code = random_int(100000, 999999);

      $info = $order->info;
      $info['bonusesUsed'] = 0;
      $order->info = $info;

      $order->save();
    }catch(\Exception $e) {
      throw new Exception('An error has occurred. Failed to create reorder');
    }

    return $order;
  }
}
