<?php

namespace Backpack\Store\app\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

// FACTORY
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Backpack\Store\database\factories\OrderFactory;

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
    // protected $fillable = [];
    // protected $hidden = [];
    // protected $dates = [];
    protected $casts = [
      'info' => 'array'
    ];


    public static $fields = [
      'provider' => [
        'rules' => 'required|in:auth,data,outer',
        'store_in' => 'info'
      ],

      'payment' => [
        'rules' => 'array:method,status',
        'store_in' => 'info',
        'method' => [
          'rules' => 'required|in:liqpay,cash'
        ]
      ],
      
      'delivery' => [
        'rules' => 'array:city,address,zip,method,warehouse',
        'store_in' => 'info',
        'method' => [
          'rules' => 'required|in:address,warehouse,pickup'
        ],
        'warehouse' => [
          'rules' => 'required_if:delivery.method,warehouse|string|min:1|max:500'
        ],
        'city' => [
          'rules' => 'required_if:delivery.method,address,warehouse|string|min:2|max:255'
        ],
        'address' => [
          'rules' => 'required_if:delivery.method,address|string|min:2|max:255'
        ],
        'zip' => [
          'rules' => 'required_if:delivery.method,address|string|min:5|max:255'
        ],
      ],
      
      'products' => [
        'rules' => 'required|array',
        'hidden' => true,
      ],
      
      'bonusesUsed' => [
        'rules' => 'nullable|numeric',
        'store_in' => 'info'
      ],

      'user' => [
        'rules' => 'array:uid,firstname,lastname,phone,email',
        'store_in' => 'info',
        'uid' => [
          'rules' => 'nullable|string|min:2|max:200'
        ],
        'firstname' => [
          'rules' => 'required_if:provider,data|string|min:2|max:150'
        ],
        'lastname' => [
          'rules' => 'nullable|string|min:2|max:150'
        ],
        'phone' => [
          'rules' => 'required_if:provider,data|string|min:2|max:80'
        ],
        'email' => [
          'rules' => 'required_if:provider,data|email|min:2|max:150'
        ],
      ]
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
     *  Get validation rules from fields array
     * @param Array|String $fields
     * @return Array
    */
    public static function getRules($fields = null, $type = 'fields') {
      $node = $fields? $fields: static::$$type;

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
      $keys = array_keys(static::$$type);
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
      return $this->belongsToMany('Backpack\Store\app\Models\Product', 'ak_order_product');
    }

    public function user()
    {
      return $this->belongsTo(config('backpack.store.user_model', 'Backpack\Profile\app\Models\Profile'));
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
}
