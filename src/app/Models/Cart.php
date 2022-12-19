<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Model;

use Backpack\Store\app\Http\Resources\ProductCartResource;


class Cart extends Model
{

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $table = 'ak_carts';
    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];
    // protected $fillable = [];
    // protected $hidden = [];
    // protected $dates = [];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */
    public function toArray() {
      return [
        'id' => $this->id,
        'user' => $this->user,
        'product' => new ProductCartResource($this->product),
        'amount' => $this->amount
      ];
    }
    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */
    public function product()
    {
      return $this->belongsTo('Backpack\Store\app\Models\Product', 'product_id');
    }

    public function user()
    {
      return $this->belongsTo(config('backpack.store.user_model', 'Backpack\Profile\app\Models\Profile'), 'user_id');
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
