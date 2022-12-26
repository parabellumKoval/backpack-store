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
      return $this->belongsTo(config('backpack.store.user_model', 'app\Models\User'));
    }
    
    // public function transactions() {
    //   return $this->hasMany('Aimix\Account\app\Models\Transaction');
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
    
    
    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
