<?php

namespace Backpack\Store\app\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Backpack\Store\database\factories\OrderFactory;

// Arr
use Illuminate\Support\Arr;

use Backpack\Store\app\Events\OrderCreated;

class Order extends Model
{
    use CrudTrait;
    use HasFactory;

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

    public static function getFieldKeys($type = 'fields') {
      //$keys = array_keys(static::$$type);
      $keys = array_keys(config("backpack.store.order.{$type}"));
      $keys = array_map(function($item) {
        return preg_replace('/[\*\.]/u', '', $item);
      }, $keys);

      return $keys;
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

    public function orderable()
    {
      return $this->morphTo();
    }
    // public function user()
    // {
    //   return $this->belongsTo(config('backpack.store.user_model', 'Backpack\Profile\app\Models\Profile'));
    // }
    
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

    public function getUserAttribute() {
      if(isset($this->info['user']) && $this->info['user'] && count($this->info['user']))
        return $this->info['user'];
    }

    public function getDeliveryAttribute() {
      if(isset($this->info['delivery']) && $this->info['delivery'] && count($this->info['delivery']))
        return $this->info['delivery'];
    }

    public function getPaymentAttribute() {
      if(isset($this->info['payment']) && $this->info['payment'] && count($this->info['payment']))
        return $this->info['payment'];
    }

    public function getProductsAnywayAttribute() {
      if(isset($this->info['products']) && $this->info['products'] && count($this->info['products']))
        return $this->info['products'];
      elseif($this->products)
        return $this->products;
      else [];
    }

    public function getStatusStringAttribute(){
	    if($this->status == 'new' || $this->status == 'pending' || $this->status == 'paid' || $this->status == 'sent')
	    	return '<span class="icon-sent order-history-icon"></span><span class="text">'.$this->status.'</span>';
	    elseif($this->status == 'canceled')
	    	return '<span class="icon-canceled order-history-icon"></span><span class="text" style="color: #EB5757;">CANCELED</span>';
	    else
	    	return '<span class="icon-delivered order-history-icon"></span><span class="text" style="color: #ACDA53;">delivered</span>';
    }
    
    public function getAddressStringAttribute(){
      if(!isset($this->info['address']) || !count($this->info['address']))
        return null;
      
      return implode(', ', $this->info['address']);
    }

    public function getUserStringAttribute() {
      if(!isset($this->info['user'])  || !count($this->info['user']))
        return null;

      $arr = array_filter($this->info['user'], function($item) {
        return !empty($item);
      });
        
      return implode(', ', $arr);
    }
    
    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */

    public function setProductsRelatedAttribute($v) {
      $this->products_to_synk = $v;
    }

    public function setExtrasAttribute($value) {
      $info_array = $this->info ?? [];

      $extras_array = [];

      foreach ($value as $k => $v) {
          static::undash($extras_array, $k, $v);
      }

      $this->info = array_merge($info_array, $extras_array);
      //dd($results);
    }

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
