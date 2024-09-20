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

// EXCEPTIONS
use Backpack\Store\app\Exceptions\OrderException;

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
  
  /**
   * all
   * 
   * Get Orders collection. Filtering by owner, status, pay_status, delivery_status, price is available.
   * Also you can setup per_page and ordering parametrs.
   *
   * @param  Illuminate\Http\Request $request
   *    [
   *      "orderable_id" => (int) - owner ID
   *      "orderable_type" => (string) - Class/Model/Provider of the owner
   *      "status" => (string) - Common status
   *      "pay_status" => (string) - Payment status
   *      "delivery_status" => (string) - Delivery status
   *      "price" => (float) - total price of the order 
   *      "per_page" => (int) - Pagination, Rows per page 
   *    ]
   * @return string JSON
   */
  public function all(Request $request) {

    $orders = $this->ORDER_MODEL::query()
              ->select('ak_orders.*')
              ->distinct('ak_orders.id')
              // Owner of the order (user, account, persone etc.)
              ->when(request('orderable_id'), function($query) {
                $query->where('ak_orders.orderable_id', request('orderable_id'));
              })
              ->when(request('orderable_type'), function($query) {
                $query->where('ak_orders.orderable_type', request('orderable_type'));
              })
              //
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
  
  /**
   * show
   * 
   * Get one order using unique code.
   *
   * @param  mixed $request
   * @param  mixed $code
   * @return void
   */
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
  
  /**
   * validateData
   *
   * @param  array $data - Data from the order request
   * @return void
   */
  public function validateData($data) {
  
    $this->validateProductsInStock($data);

    // Apply validation rules to data
    $validator = Validator::make($data, $this->ORDER_MODEL::getRules());

    if ($validator->fails()) {
      $errors = $validator->errors()->toArray();
      $errors_array = [];

      foreach($errors as $key => $error){
        $this->assignArrayByPath($errors_array, $key, $error);
      }

      throw new OrderException('Order Validation Error', 403, null, $errors_array);
    }

    return $data;
  }
    
  /**
   * validateProductsInStock
   *
   * @param  mixed $data
   * @return void
   */
  private function validateProductsInStock($data) {
    if(empty($data['products'])){
      throw new OrderException('Order Validation Error', 403, null, [
        'products' => 'В заказе должен быть хотя бы один товар'
      ]);
    }

    $errors = ['products' => []];
    foreach($data['products'] as $id => $amount) {
      $in_stock = Product::findOrFail($id)->in_stock;
      if($in_stock < $amount) {
        $errors['products'][$id] = 'Максимальное кол-во для заказа: ' . $in_stock . 'шт.';
      }
    }

    if(!empty($errors['products'])) {
      throw new OrderException('Order Validation Error', 403, null, $errors);
    }
  }

  public function validateRequest(Request $request) {
    try {
      // Get only allowed fields
      $data = $this->validateData($request->only($this->ORDER_MODEL::getFieldKeys()));
    }catch(OrderException $e) {
      return response()->json($e->getMessage(), $e->getCode(), $e->getOptions());
    }

    return true;
  }

  /**
   * create
   * 
   * Store new product.
   *
   * @param  mixed $request
   * @return void
   */
  public function create(Request $request){
    
    try {
      // Get only allowed fields
      $data = $this->validateData($request->only($this->ORDER_MODEL::getFieldKeys()));
      
      // Create new empty Order 
      $order = new $this->ORDER_MODEL;

      // Set base data that independent from external sources (data from request)
      $order = $this->prepareOrder($order);

      // Set common fields
      $order = $this->setRequestFields($order, $data);

      // Set user data
      $order = $this->setUserData($order, $data);

      // Attach product to order and calculate order total price
      [$order, $products] = $this->setProductsToOrder($order, $data);

      // Try validate and apply promocode to order
      if(isset($data['promocode']) && !empty($data['promocode'])) {
        $order->promocode = $data['promocode'];
      }

      // Get price with products, promocodes etc.
      $order->price = $order->getTotalPrice();

      // Save order
      $order->save();

      // Try attach products to order after save()
      foreach($products as $product) {
        $order->products()->attach($product, ['amount' => $data['products'][$product->id]]);
      }

      // Dispatch event to change product in_stock etc.
      ProductAttachedToOrder::dispatch($order);
      
      // Dispatch promocode usage event
      if($order->promocode) {
        PromocodeApplied::dispatch($order);
      }

    }catch(OrderException $e) {
      return response()->json($e->getMessage(), $e->getCode(), $e->getOptions());
    }

    return response()->json(new $this->ORDER_LARGE_RESOURCE($order));
  }

  /**
   * setUserData
   * 
   * Set user data to order info field and attach user Model if possible.
   * 
   * @param  Backpack\Store\app\Models\Order $order - new Order model
   * @param  array $data - Order request data
   * @return Backpack\Store\app\Models\Order $order
   */
  protected function setUserData($order, array $data){
    // GET USER MODEL IF AUTHED
    if($data['provider'] === 'auth') {

      if(!Auth::guard(config('backpack.store.auth_guard', 'profile'))->check()){
        throw new OrderException('User not authenticated', 401);
      }

      $user_model = Auth::guard(config('backpack.store.auth_guard', 'profile'))->user();

      // User Model have to implement toOrderArray() method that gives:
      //    array {first_name: string, last_name: string, phone: string, email: string}
      $user_data = $user_model->toOrderArray();

      // add user data to info field (json)
      $info = $order->info;
      $info['user'] = $user_data;
      $order->info = $info;

      $order->orderable_id = isset($user_model)? $user_model->id: null;
      $order->orderable_type = isset($user_model)? config('backpack.store.user_model', 'Backpack\Profile\app\Models\Profile'): null;
    }

    return $order;
  }


  /**
   * setRequestFields
   * 
   * Automatycly setting all fields form request 
   * using structure from the config("backpack.store.order.fields").
   * 
   * 
   * @param  Backpack\Store\app\Models\Order $order - new Order model
   * @param  array $data - Order request data
   * @return Backpack\Store\app\Models\Order $order
   */
  protected function setRequestFields($order, array $data) {

    foreach($data as $field_name => $field_value){
      // Getting fields structure and rules from config
      $config_fields = $this->ORDER_MODEL::getFields();
      $field = $config_fields[$field_name] ?? $config_fields[$field_name.'.*'];
      
      // Skipping if filed is hidden
      if(isset($field['hidden']) && $field['hidden'])
        continue;

      // If JSON field 
      if(isset($field['store_in'])) {
        $field_old_value = $order->{$field['store_in']};
        $field_old_value[$field_name] = $field_value;
        $order->{$field['store_in']} = $field_old_value;
      }
      // if regular field
      else {
        $order->{$field_name} = $field_value;
      }
    }

    return $order;
  }

  /**
   * prepareOrder
   *
   * @param Backpack\Store\app\Models\Order $order
   * @return Backpack\Store\app\Models\Order $order
   */
  protected function prepareOrder($order) {
    // Generate order code
    $order->code = random_int(100000, 999999);

    // Generate order code
    $order->status = config('backpack.store.order.status.default', 'new');
    
    // Generate order code
    $order->pay_status = config('backpack.store.order.pay_status.default', 'waiting');
    
    // Generate order code
    $order->delivery_status = config('backpack.store.order.delivery_status.default', 'waiting');

    return $order;
  }
  
  /**
   * setProductsToOrder
   * 
   * @param  Backpack\Store\app\Models\Order $order - new Order model
   * @param  array $data - Order request data
   * @return array {$order: Backpack\Store\app\Models\Order, $products: Collection}
   */
  protected function setProductsToOrder($order, array $data){
    // Get products collection
    $products = Product::whereIn('id', array_keys($data['products']))->get();

    if(!$products || !$products->count()) {
      throw new OrderException("There are no products found in cart or products does not exist in the database.", 404);
    }

    // Set products to info
    foreach($products as $key => $product) {
      $product->amount = $data['products'][$product->id];
      $info = $order->info;
      $info['products'][$key] = new ProductCartResource($product);
      $order->info = $info;
    }

    return [$order, $products];
  }

  /**
   * usePromocode
   * 
   * Apply promocode to order after validation. 
   * The promocode affects the order price.
   *
   * @param  Backpack\Store\app\Models\Order $order - new order Model
   * @param  array $data - Data from the order request
   * @return Backpack\Store\app\Models\Order $order
   */
  // protected function usePromocode($order, $data) {
  //   // Checking if promocode data isset in request
  //   if(!isset($data['promocode']) || empty($data['promocode']) || !$order)
  //     return $order;
    
  //   // Checking if promocode really excists in DB and getting it. 
  //   $promocode = Promocode::whereRaw('LOWER(`code`) LIKE ? ',[trim(strtolower($data['promocode'])).'%'])->first();
    
  //   // Check if promocode valid by used times, date and is_active property
  //   if(!$promocode || !$promocode->isValid)
  //     return $order;
    
  //   // Setting promocode info to order's info
  //   $info = $order->info;
  //   $info['promocode'] = $promocode;
  //   $order->info = $info;

  //   // Making correction to order price
  //   // if promocode is expressed in currency 
  //   if($promocode->type === 'value')
  //     $order->price = $order->price - $promocode->value;

  //   // if promocode is expressed in percent
  //   if($promocode->type === 'percent')
  //     $order->price = $order->price - ($order->price * $promocode->value / 100);
    
  //   // return changed order
  //   return $order;
  // }
  
  /**
   * copy
   * 
   * Clone existing order to a new 
   *
   * @param  mixed $request
   *    [
   *      "id" => (int) Base order id
   *    ]
   * @return Backpack\Store\app\Models\Order $order
   */
  public function copy(Request $request) {
    // Getting base order id from request
    if(!$request->id)
      throw new \Exception('The ID of the base record was not pass.', 403);
  
    // Getting base order
    $base = Order::findOrFail($request->id);
    
    // Clone base order to variable
    $order = $base->replicate();

    try {
      // Reset total price, statuses, delete promocodes and bonuses
      $order->resetCopy();

      // Try to save order
      $order->save();
    }catch(\Exception $e) {
      throw new \Exception('An error has occurred. Failed to create copy: ' . $e->getMessage(), $e->getCode());
    }

    return $order;
  }
}
