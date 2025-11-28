<?php

namespace Backpack\Store\app\Models;

use Illuminate\Database\Eloquent\Model;
use Backpack\Helpers\Traits\FormatsUniqAttribute;

class Cart extends Model
{
    use FormatsUniqAttribute;

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
      return $this->belongsTo(\Settings::get('dress.product.model', 'Backpack\Store\app\Models\Product'), 'product_id');
    }

    public function user()
    {
      return $this->belongsTo(\Settings::get('backpack.store.user_model', 'Backpack\Profile\app\Models\Profile'), 'user_id');
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
    public function getUniqStringAttribute(): string
    {
        $user = $this->relationLoaded('user') ? $this->getRelation('user') : null;
        $product = $this->relationLoaded('product') ? $this->getRelation('product') : null;

        return $this->formatUniqString([
            '#'.$this->id,
            $user->email ?? $user->name ?? sprintf('user #%s', $this->user_id ?? '?'),
            $product?->name ?? sprintf('product #%s', $this->product_id ?? '?'),
            sprintf('qty: %s', $this->amount ?? 0),
            $this->status ? 'status: '.$this->status : null,
        ]);
    }

    public function getUniqHtmlAttribute(): string
    {
        $user = $this->relationLoaded('user') ? $this->getRelation('user') : null;
        $product = $this->relationLoaded('product') ? $this->getRelation('product') : null;

        $headline = $this->formatUniqString([
            '#'.$this->id,
            $user->email ?? $user->name ?? sprintf('user #%s', $this->user_id ?? '?'),
        ]);

        return $this->formatUniqHtml($headline, [
            $product?->name ?? sprintf('product #%s', $this->product_id ?? '?'),
            sprintf('qty: %s', $this->amount ?? 0),
            $this->status ? 'status: '.$this->status : null,
        ]);
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
