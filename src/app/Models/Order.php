<?php

namespace Backpack\Store\app\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Backpack\Store\database\factories\OrderFactory;

// TRAITS
use App\Models\Traits\OrderModel as OrderModelTrait;

// Arr
use Illuminate\Support\Arr;

// EVENTS
use Backpack\Store\app\Events\PromocodeApplied;
use Backpack\Store\app\Events\OrderCreated;
use Backpack\Store\app\Models\Promocode;

class Order extends Model
{
    use CrudTrait;
    use HasFactory;

    use OrderModelTrait;
    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_orders';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    protected $fillable = ['price', 'productsRelated', 'extras', 'delivery_status', 'pay_status', 'status'];
    // protected $hidden = [];
    // protected $dates = [];
    protected $casts = [
      'info' => 'array',
      'productsRelated' => 'array',
      'extras' => 'array',
      'user' => 'array'
    ];


    public static $fields = null;
    public $products_to_synk = null;

    protected $dispatchesEvents = [
      'created' => OrderCreated::class
    ];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
      return OrderFactory::new();
    }

    
    /**
     * getFields
     *
     * @param  mixed $type
     * @return void
     */
    public static function getFields($type = 'fields') {
      $fields = config("backpack.store.order.{$type}");
      
      if(!$fields)
        throw new \Exception('Please set fields in backpack.store.order config');
      else
        return $fields;
    }

    /** 
     *  Get validation rules from fields array
     * @param Array|String $fields
     * @return Array
    */
    public static function getRules($fields = null, $type = 'fields') {
      //$node = $fields? $fields: static::$$type;
      $node = $fields? $fields: config("backpack.store.order.{$type}");

      $rules = [];
      
      if(is_string($node)) {
        return $node;
      }

      if(is_array($node)) {
        
        foreach($node as $field => $value) {
          if(in_array($field, ['store_in']))
            continue;
          
          $selfRules = static::getRules($value);

          if(is_array($selfRules))
            foreach($selfRules as $k => $v) {
              if($k === 'rules') {
                $rules[$field] = $v;
              }else {
                $name = implode('.', [$field, $k]);
                $rules[$name] = $v;
              }
            }
          else
            $rules[$field] = $selfRules;
        }

      }

      return $rules;
    }
    
    /**
     * getFieldKeys
     *
     * @param  mixed $type
     * @return void
     */
    public static function getFieldKeys($type = 'fields') {
      //$keys = array_keys(static::$$type);
      $keys = array_keys(config("backpack.store.order.{$type}"));
      $keys = array_map(function($item) {
        return preg_replace('/[\*\.]/u', '', $item);
      }, $keys);

      return $keys;
    }
    
    /**
     * resetCopy
     * 
     * Reset some fields during coping order
     *
     * @return void
     */
    public function resetCopy() {
      // Get current info data
      $info = $this->info;

      // Remove used bonuses data
      $info['bonusesUsed'] = 0;

      // Reset promocode
      $info['promocode'] = null;

      // Generate new order code 
      $this->code = random_int(100000, 999999);

      // Reset total order price (without promocodes and bonuses)
      $this->price = $this->getProductsPrice();

      // Reset statuses
      $this->status = config("backpack.store.order.status.default");
      $this->pay_status = config("backpack.store.order.pay_status.default");
      $this->delivery_status = config("backpack.store.order.delivery_status.default");

      // Write clear info
      $this->info = $info;
    }
    
    /**
     * getProductsPrice
     * 
     * Calculate order total price from info array
     * without tax, promocodes, bonuses etc. Using only product price and product amount
     *
     * @return float $price
     */
    public function getProductsPrice() {
      $products = $this->info['products'];
      
      // Calculate price
      $price = array_reduce($products, function($carry, $item) {
        return $carry + $item['price'] * $item['amount'];
      }, 0);

      // return order total price
      return round($price, 2);
    }
    
    /**
     * getTotalPrice
     * 
     * Total price using products price, promocodes etc.
     *
     * @return void
     */
    public function getTotalPrice() {
      // Get sum of products with amount
      $price = $this->getProductsPrice();
      // If no promocode return regular price
      if(!isset($this->info['promocode']) || empty($this->info['promocode'])) {
        return $price;
      }

      // Try find promocode data in info JSON
      $promocode = $this->info['promocode'];

      // Making correction to order price
      // if promocode is expressed in currency 
      if($promocode['type'] === 'value') {
        $price = $price - $promocode['value'];
      }

      // if promocode is expressed in percent
      if($promocode['type'] === 'percent')
        $price = $price - ($price * $promocode['value'] / 100);

      return round($price, 2);
    }
    
    /**
     * usePromocode
     * 
     * Making 2 things:
     * -- Set promocode info to info JSON
     * -- Set total order price
     *
     * @param  mixed $promocode
     * @return void
     */
    public function usePromocode(string $promocode): void {
      
      // Set promocode to info JSON
      $this->promocode = $promocode;
      
      // Refresh price (using promocode sale)
      $this->price = $this->getTotalPrice();
    }
    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function products()
    {
      return $this->belongsToMany('Backpack\Store\app\Models\Product', 'ak_order_product')->withPivot('amount');
    }

    // Owner/User Model/ Profile Model etc.
    public function orderable()
    {
      return $this->morphTo();
    }
    
    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */
    
    /**
     * getUserAttribute
     * 
     * Return user data array from order info JSON
     *
     * @return array|null
     */
    public function getUserAttribute() {
      if(isset($this->info['user']) && $this->info['user'] && count($this->info['user']))
        return $this->info['user'];
      else
        return null;
    }
    
    /**
     * getDeliveryAttribute
     *
     * Return delivery data array from order info JSON
     * 
     * @return array|null
     */
    public function getDeliveryAttribute() {
      if(isset($this->info['delivery']) && $this->info['delivery'] && count($this->info['delivery']))
        return $this->info['delivery'];
      else
        return null;
    }
    
    /**
     * getPaymentAttribute
     *
     * Return payment data array from order info JSON
     *
     * @return array|null
     */
    public function getPaymentAttribute() {
      if(isset($this->info['payment']) && $this->info['payment'] && count($this->info['payment']))
        return $this->info['payment'];
      else
        return null;
    }
    
    /**
     * getProductsAnywayAttribute
     * 
     * Return products from info JSON or from relations otherwise
     *
     * @return array
     */
    public function getProductsAnywayAttribute() {
      $PRODUCT_CART_RESOURCE = config('backpack.store.product.product_cart_resource', 'Backpack\Store\app\Http\Resources\ProductCartResource');

      // Try get products from static info field
      if(isset($this->info['products']) && $this->info['products'] && count($this->info['products'])) {
        return $this->info['products'];
      }
      // try get products from relations
      elseif($this->products) {
        // Get products collection resource 
        $products_collection = $PRODUCT_CART_RESOURCE::collection($this->products);
        
        // Convert to array and return
        return json_decode($products_collection->toJson(), true);
      }
      // else return empty array
      else {
        return [];
      }
    }
    
    
    /**
     * getPromocodeAttribute
     *
     * Get promocode info from order info
     * 
     * @return array|null
     */
    public function getPromocodeAttribute() {
      if(!isset($this->info['promocode']) || empty($this->info['promocode']))
        return null;

      return $this->info['promocode'];
    }
        
    /**
     * getPromocodeSaleStringAttribute
     * 
     * Necessary for email-letters and dashboard
     * Return sale value in currency or in percents
     * Fx: -50$ or -5%
     * 
     * @return string
     */
    public function getPromocodeSaleStringAttribute() {
      if(!$this->promocode)
        return '';
      
      // Get currency symbol from config
      $currency_symbol = config('backpack.store.currency.symbol', '$'); 

      switch($this->promocode['type']) {
        // If regular return in currency
        case 'value':
          return "-{$currency_symbol}{$this->promocode['value']}";
        // If percents
        case 'percent':
          return "-{$this->promocode['value']}%";
        default:
          return $this->promocode['value'];
      }
    }

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */

    public function setPromocodeAttribute($value = null) {
      // Checking if promocode data isset in request
      if(empty($value))
        return;
      
      // Checking if promocode really excists in DB and getting it. 
      $promocode = Promocode::whereRaw('LOWER(`code`) LIKE ? ',[trim(strtolower($value)).'%'])->first();
      
      // Check if promocode exists
      if(!$promocode) {
        throw new \Exception('Promocode does not exist.', 404);
      }

      // Check if promocode valid by used times, date and is_active property
      if(!$promocode->isValid) {
        throw new \Exception('Promocode is not valid.', 401);
      }
      
      // Setting promocode info to order's info
      $info = $this->info;
      $info['promocode'] = $promocode;
      $this->info = $info;
    }
    
    /**
     * setProductsRelatedAttribute
     * 
     * Auxiliary field for data processing in Observer, Listeners etc.
     *
     * @param  mixed $v
     * @return void
     */
    public function setProductsRelatedAttribute($v) {
      $this->products_to_synk = $v;
    }
    
    /**
     * setExtrasAttribute
     *
     * @param  array $value
     * @return void
     */
    public function setExtrasAttribute($value) {
      // Getting current info data
      $info_array = $this->info ?? [];

      // New extrat data
      $extras_array = [];

      // For each item 
      foreach ($value as $k => $v) {
        // Undash and set to extras_array by link
        static::undash($extras_array, $k, $v);
      }

      // Merging old and new extras data
      $this->info = array_merge($info_array, $extras_array);
    }
    
    /**
     * undash
     *
     * @param  array $array
     * @param  mixed $key
     * @param  mixed $value
     * @return array
     */
    public static function undash(&$array, $key, $value)
    {
        if (is_null($key)) {
            return $array = $value;
        }

        $keys = explode('-', $key);

        foreach ($keys as $i => $key) {
            if (count($keys) === 1) {
                break;
            }

            unset($keys[$i]);

            // If the key doesn't exist at this depth, we will just create an empty array
            // to hold the next value, allowing us to create the arrays to hold final
            // values at the correct depth. Then we'll keep digging into the array.
            if (! isset($array[$key]) || ! is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }
}
